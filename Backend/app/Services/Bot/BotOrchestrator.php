<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\DocumentType;
use App\Enums\DraftSessionStatus;
use App\Enums\PlanTier;
use App\Exceptions\DomainRuleException;
use App\Models\BotDraftSession;
use App\Models\ChannelIdentity;
use App\Models\Merchant;
use App\Models\Template;
use App\Models\User;
use App\Services\Billing\PlanEntitlements;
use App\Services\Billing\QuotaService;
use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\BotAction;
use App\Services\Bot\Actions\DeliverSlip;
use App\Services\Bot\Actions\SendText;
use App\Services\Bot\Actions\StaySilent;
use App\Services\Documents\DocumentIssuanceService;
use App\Services\Documents\InventoryService;
use App\Services\Documents\TotalsCalculator;
use App\Services\Security\Idempotency;
use App\Services\Slash\DraftPayload;
use App\Services\Slash\ParseResult;
use App\Services\Slash\SlashCommandParser;
use App\Support\Money;
use App\Support\Urls;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Channel-neutral conversation engine for every bot module.
 *
 * Cost model: partial /slash messages are buffered silently in bot_draft_sessions. The vendor gets
 * ONE consolidated confirmation when the draft first becomes complete; later field updates are
 * buffered silently too (Telegram edits the summary in place for free). Each silent buffer
 * increments saved_outbound_calls_count, surfaced as "API calls saved" on the dashboard.
 */
final class BotOrchestrator
{
    public function __construct(
        private readonly SlashCommandParser $parser,
        private readonly IdentityLinker $identities,
        private readonly Idempotency $idempotency,
        private readonly InventoryService $inventory,
        private readonly TotalsCalculator $calculator,
        private readonly DocumentIssuanceService $issuance,
        private readonly QuotaService $quota,
        private readonly PlanEntitlements $entitlements,
    ) {}

    public function handle(InboundMessage $message): BotAction
    {
        if (! $this->idempotency->claim('bot:'.$message->channel->value, $message->messageId)) {
            return new StaySilent('duplicate');
        }

        $parsed = $this->parser->parse($message->text);
        $identity = $this->identities->resolve($message->channel, $message->externalUserId);

        if ($parsed->command === 'link') {
            return $this->link($message, (string) $parsed->commandArgument);
        }
        if ($identity === null) {
            return new SendText(BotTexts::notLinked());
        }

        $user = $identity->user;
        $merchant = $user->merchant;
        if (! $user->is_active || $merchant === null || $merchant->id !== $identity->merchant_id) {
            return new SendText('This chat is no longer linked to an active NetSlip account. Ask your admin for a new link code.', true);
        }
        if ($merchant->isSuspended()) {
            return new SendText('Your NetSlip account is suspended. Contact support.', true);
        }
        if (! $user->can('documents.issue')) {
            return new SendText('Your role cannot issue slips. Ask the account owner.', true);
        }

        $session = $this->openSession($message, $merchant);
        $command = $message->action ?? $parsed->command;
        $argument = $parsed->commandArgument;

        // Plain "1 / 2 / 3" replies to a text-only confirmation prompt (WhatsApp without templates).
        if ($command === null && $session?->status === DraftSessionStatus::AWAITING_CONFIRMATION) {
            $command = match (mb_strtolower(trim($message->text))) {
                '1', 'yes', 'confirm', 'ok', 'y' => 'confirm',
                '2', 'edit' => 'edit',
                '3', 'cancel', 'no', 'n' => 'cancel',
                default => null,
            };
        }

        if ($command !== null) {
            return $this->command($command, $argument, $message, $identity, $user, $merchant, $session);
        }

        if (! $parsed->hasPayload()) {
            if ($parsed->unknownKeys !== []) {
                return new SendText("/{$parsed->unknownKeys[0]} isn't a NetSlip field. Send /help for the list.", true);
            }

            // Free text while a draft is open is ignored silently (no fee); otherwise show help once.
            return $session !== null ? $this->silent($session, 'free_text') : new SendText(BotTexts::help());
        }

        return $this->accumulate($message, $user, $merchant, $session, $parsed);
    }

    private function accumulate(InboundMessage $message, User $user, Merchant $merchant, ?BotDraftSession $session, ParseResult $parsed): BotAction
    {
        $session ??= BotDraftSession::query()->create([
            'merchant_id' => $merchant->id,
            'actor_user_id' => $user->id,
            'channel' => $message->channel,
            'external_chat_id' => $message->externalChatId,
            'external_user_id' => $message->externalUserId,
            'status' => DraftSessionStatus::COLLECTING,
            'draft_payload_json' => DraftPayload::empty()->toArray(),
            'expires_at' => now()->addMinutes((int) config('netslip.bots.draft_ttl_minutes', 1440)),
        ]);

        [$payload, $errors] = DraftPayload::fromArray($session->draft_payload_json)->apply($parsed);
        $payload = $payload->withItems($this->inventory->resolveDraftItems($merchant, $payload->items()));
        $wasAwaiting = $session->status === DraftSessionStatus::AWAITING_CONFIRMATION;

        $session->draft_payload_json = $payload->toArray();
        $session->expires_at = now()->addMinutes((int) config('netslip.bots.draft_ttl_minutes', 1440));
        $session->save();

        if ($errors !== []) {
            return new SendText(implode("\n", array_slice($errors, 0, 3)), true);
        }
        if (! $payload->isReady()) {
            return $this->silent($session, 'collecting');
        }
        if ($wasAwaiting) {
            // Already showed the summary once: keep buffering. Telegram adapters may edit it in place.
            $session->increment('saved_outbound_calls_count');

            return $this->confirmation($session, $merchant, $payload, isUpdate: true);
        }

        $session->status = DraftSessionStatus::AWAITING_CONFIRMATION;
        $session->save();

        return $this->confirmation($session, $merchant, $payload);
    }

    private function command(string $command, ?string $argument, InboundMessage $message, ChannelIdentity $identity, User $user, Merchant $merchant, ?BotDraftSession $session): BotAction
    {
        switch ($command) {
            case 'help':
                return new SendText(BotTexts::help());

            case 'credits':
                $remaining = $this->quota->remainingFree($merchant);

                return new SendText(sprintf(
                    "Plan: %s\nFree slips left this month: %s\nPaid credits: %d\nTop up: %s",
                    $merchant->effectiveTier()->label(),
                    $remaining === null ? 'Unlimited' : (string) $remaining,
                    $merchant->paid_credits_balance,
                    Urls::frontend('dashboard/billing'),
                ));

            case 'unlink':
                $identity->delete();

                return new SendText('This chat is unlinked from NetSlip.');

            case 'clear':
                $session?->update(['status' => DraftSessionStatus::CANCELLED]);

                return new SendText(BotTexts::cleared());

            case 'cancel':
                if ($session === null) {
                    return new SendText('Nothing to cancel.');
                }
                $session->update(['status' => DraftSessionStatus::CANCELLED]);

                return new SendText(BotTexts::cancelled());

            case 'edit':
                if ($session === null) {
                    return new SendText('No open draft. Start with /name = …');
                }
                $session->update(['status' => DraftSessionStatus::COLLECTING]);

                return new SendText(BotTexts::editing());

            case 'remove':
                if ($session === null) {
                    return new SendText('No open draft.');
                }
                $payload = DraftPayload::fromArray($session->draft_payload_json);
                if (! $payload->removeItem((int) $argument)) {
                    return new SendText('Send /remove 2 to remove item number 2.', true);
                }
                $session->update(['draft_payload_json' => $payload->toArray(), 'status' => DraftSessionStatus::COLLECTING]);

                return $payload->isReady()
                    ? $this->markAwaiting($session, $merchant, $payload)
                    : new SendText(BotTexts::missing($payload->missing()));

            case 'preview':
                if ($session === null) {
                    return new SendText('No open draft. Start with /name = …');
                }
                $payload = DraftPayload::fromArray($session->draft_payload_json);

                return $payload->isReady()
                    ? $this->markAwaiting($session, $merchant, $payload)
                    : new SendText(BotTexts::missing($payload->missing()));

            case 'confirm':
            case 'confirm_clean':
                $clean = $command === 'confirm_clean' || in_array(mb_strtolower((string) $argument), ['clean', 'credit', 'premium'], true);

                return $this->confirm($message, $user, $merchant, $session, $clean);

            case 'link':
                return new SendText('This chat is already linked to '.$merchant->name.'.');
        }

        return new SendText(BotTexts::help());
    }

    private function confirm(InboundMessage $message, User $user, Merchant $merchant, ?BotDraftSession $session, bool $clean): BotAction
    {
        if ($session === null) {
            return new SendText('No open draft to confirm. Start with /name = …');
        }

        // Lock the session so a double-tap on "Confirm" can never issue (and charge) twice.
        $claimed = DB::transaction(static function () use ($session): ?BotDraftSession {
            $locked = BotDraftSession::query()->whereKey($session->id)->lockForUpdate()->first();
            if ($locked === null || ! in_array($locked->status, [DraftSessionStatus::COLLECTING, DraftSessionStatus::AWAITING_CONFIRMATION], true)) {
                return null;
            }
            $locked->status = DraftSessionStatus::CONFIRMED;
            $locked->save();

            return $locked;
        });
        if ($claimed === null) {
            return new StaySilent('already_confirmed');
        }

        $payload = DraftPayload::fromArray($claimed->draft_payload_json);
        if (! $payload->isReady()) {
            $claimed->update(['status' => DraftSessionStatus::COLLECTING]);

            return new SendText(BotTexts::missing($payload->missing()), true);
        }

        try {
            $document = $this->issuance->issue(
                $merchant,
                $user,
                $payload->toDraftData($this->templateId($payload), $clean),
                $message->channel,
            );
        } catch (DomainRuleException $e) {
            $claimed->update(['status' => DraftSessionStatus::AWAITING_CONFIRMATION]);
            $suffix = $e->errorCode === 'quota_exceeded' ? "\nTop up: ".Urls::frontend('dashboard/billing') : '';

            return new SendText($e->getMessage().$suffix, true);
        } catch (Throwable $e) {
            report($e);
            $claimed->update(['status' => DraftSessionStatus::AWAITING_CONFIRMATION]);

            return new SendText('Something went wrong creating your slip. Please tap Confirm again.', true);
        }

        $claimed->update(['document_id' => $document->id]);
        $total = Money::format($document->total_minor, $document->currency);
        $label = $document->doc_type === DocumentType::INVOICE ? 'Invoice' : 'Receipt';
        $payUrl = $document->doc_type === DocumentType::INVOICE && $document->balance_due_minor > 0
            && $this->entitlements->has($merchant, PlanEntitlements::PAYMENT_LINKS)
            && ($merchant->hasFlutterwave() || $merchant->hasNowPayments())
            ? Urls::pay($document->public_uid) : null;

        $caption = "{$label} {$document->doc_number} · {$total}\nFor {$document->customer_name}\nVerify: ".Urls::verify($document->verification_hash);
        if ($payUrl !== null) {
            $caption .= "\nPay online: {$payUrl}";
        }

        return new DeliverSlip(
            session: $claimed->refresh(),
            document: $document,
            pdfUrl: URL::temporarySignedRoute('documents.public-pdf', now()->addMinutes((int) config('netslip.pdf.signed_url_minutes')), ['uid' => $document->public_uid]),
            verifyUrl: Urls::verify($document->verification_hash),
            payUrl: $payUrl,
            caption: $caption,
            filename: $document->doc_number.'.pdf',
        );
    }

    private function markAwaiting(BotDraftSession $session, Merchant $merchant, DraftPayload $payload): AskConfirmation
    {
        $session->update(['status' => DraftSessionStatus::AWAITING_CONFIRMATION]);

        return $this->confirmation($session, $merchant, $payload);
    }

    private function confirmation(BotDraftSession $session, Merchant $merchant, DraftPayload $payload, bool $isUpdate = false): AskConfirmation
    {
        $draft = $payload->toDraftData();
        $lines = array_values(array_map(static fn ($i): array => ['description' => $i->description, 'quantity' => $i->quantity, 'unit_price_minor' => $i->unit_price_minor], $draft->items));
        $vat = $draft->vat_rate_bps ?? $merchant->vat_rate_bps;
        $inclusive = $draft->vat_inclusive ?? $merchant->vat_inclusive;
        $totals = $this->calculator->calculate($lines, $draft->discount_minor, $draft->shipping_minor, $vat, $inclusive, null);
        $paid = $draft->amount_paid_minor ?? ($draft->type === DocumentType::RECEIPT ? $totals->totalMinor : 0);
        $paid = min($paid, $totals->totalMinor);

        $planClean = $this->entitlements->has($merchant, PlanEntitlements::REMOVE_WATERMARK);

        return new AskConfirmation(
            session: $session,
            docLabel: $draft->type === DocumentType::INVOICE ? 'Invoice' : 'Receipt',
            customerName: $draft->customer_name,
            customerPhone: $draft->customer_phone,
            customerAddress: $draft->customer_address,
            lines: array_map(static fn (array $l): array => [
                'description' => $l['description'], 'quantity' => $l['quantity'],
                'unit_minor' => $l['unit_price_minor'], 'total_minor' => $l['line_total_minor'],
            ], $totals->lines),
            subtotalMinor: $totals->subtotalMinor,
            discountMinor: $totals->discountMinor,
            taxMinor: $totals->taxMinor,
            taxLabel: 'VAT '.rtrim(rtrim(number_format($vat / 100, 2), '0'), '.').'%',
            shippingMinor: $totals->shippingMinor,
            totalMinor: $totals->totalMinor,
            paidMinor: $paid,
            balanceMinor: $totals->totalMinor - $paid,
            currency: $merchant->currency,
            willWatermark: ! $planClean,
            canUseCleanCredit: ! $planClean && $merchant->paid_credits_balance > 0 && $merchant->effectiveTier() === PlanTier::FREE,
            isUpdate: $isUpdate,
        );
    }

    private function silent(BotDraftSession $session, string $reason): StaySilent
    {
        $session->increment('saved_outbound_calls_count');

        return new StaySilent($reason);
    }

    private function openSession(InboundMessage $message, Merchant $merchant): ?BotDraftSession
    {
        return BotDraftSession::query()->forMerchant($merchant)
            ->where('channel', $message->channel->value)
            ->where('external_chat_id', $message->externalChatId)
            ->where('external_user_id', $message->externalUserId)
            ->whereIn('status', [DraftSessionStatus::COLLECTING->value, DraftSessionStatus::AWAITING_CONFIRMATION->value])
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    private function link(InboundMessage $message, string $code): BotAction
    {
        if ($code === '') {
            return new SendText('Send /link followed by the code from your dashboard, e.g. /link K7P2QX9A', true);
        }
        $identity = $this->identities->link($message->channel, $message->externalUserId, $message->displayName, $code);
        if ($identity === null) {
            return new SendText('That code is invalid or expired. Get a fresh one in Dashboard → Channels.', true);
        }
        $identity->load('user.merchant');

        return new SendText(BotTexts::linked((string) $identity->user->merchant?->name, $identity->user->name));
    }

    private function templateId(DraftPayload $payload): ?int
    {
        $slug = $payload->template();
        if ($slug === null) {
            return null;
        }

        return Template::query()->where('slug', $slug)
            ->orWhere('slug', 'netslip-'.$slug)->orWhereRaw('lower(name) = ?', [mb_strtolower($slug)])
            ->value('id');
    }
}

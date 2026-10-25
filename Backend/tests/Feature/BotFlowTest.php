<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DraftSessionStatus;
use App\Models\BotDraftSession;
use App\Models\ChannelIdentity;
use App\Models\Document;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

final class BotFlowTest extends TestCase
{
    private int $sid = 0;

    private function whatsapp(string $body, string $from = 'whatsapp:+2348031234567', array $extra = [])
    {
        $url = url('/api/v1/webhooks/whatsapp');
        $params = ['MessageSid' => 'SM'.(++$this->sid).uniqid(), 'From' => $from, 'To' => 'whatsapp:+14155238886', 'Body' => $body, 'ProfileName' => 'Tobi', ...$extra];
        $signature = (new RequestValidator('twilio-test-token'))->computeSignature($url, $params);

        return $this->withHeader('X-Twilio-Signature', $signature)->post($url, $params);
    }

    private function linkWhatsapp(): array
    {
        [$merchant, $user] = $this->merchantWithOwner(credits: 0);
        $this->actingAs($user);
        $code = $this->postJson('/api/v1/channels/link-code')->assertCreated()->json('code');
        auth()->guard('web')->logout();
        $this->whatsapp('/link '.$code)->assertOk()->assertSee('Linked');

        return [$merchant, $user];
    }

    public function test_rejects_bad_twilio_signature(): void
    {
        $this->withHeader('X-Twilio-Signature', 'forged')->post('/api/v1/webhooks/whatsapp', ['MessageSid' => 'SM1', 'From' => 'whatsapp:+1', 'Body' => 'hi'])
            ->assertForbidden();
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'webhook.twilio_bad_signature']);
    }

    public function test_unlinked_sender_gets_link_instructions(): void
    {
        $this->whatsapp('/name = Ada')->assertOk()->assertSee('/link YOURCODE', false);
    }

    public function test_silent_buffer_then_single_confirmation_then_pdf(): void
    {
        [$merchant] = $this->linkWhatsapp();
        $this->assertSame(1, ChannelIdentity::query()->count());

        // Partial message → empty TwiML (no outbound fee)
        $this->whatsapp('/name = Chief Emeka')->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->assertSee('<Response/>', false);

        // Completing message → one consolidated summary
        $summary = $this->whatsapp('/item = 2x Sneakers @ 15000')->assertOk();
        $summary->assertSee('Receipt for Chief Emeka', false)->assertSee('Reply 1', false)->assertSee('₦32,250.00', false);

        // Further edits stay silent
        $this->whatsapp('/address = 42 Wuse II, Abuja')->assertSee('<Response/>', false);
        $session = BotDraftSession::query()->firstOrFail();
        $this->assertSame(DraftSessionStatus::AWAITING_CONFIRMATION, $session->status);
        $this->assertSame(2, $session->saved_outbound_calls_count);

        // "1" confirms → PDF media
        $slip = $this->whatsapp('1')->assertOk();
        $slip->assertSee('<Media>', false)->assertSee('RCP-', false);
        $doc = Document::query()->firstOrFail();
        $this->assertSame('WHATSAPP', $doc->channel->value);
        $this->assertSame('42 Wuse II, Abuja', $doc->customer_address);
        $this->assertSame($merchant->id, $doc->merchant_id);
        $this->assertSame(DraftSessionStatus::CONFIRMED, $session->refresh()->status);

        // Double-confirm can never issue twice
        $this->whatsapp('/confirm')->assertOk();
        $this->assertSame(1, Document::query()->count());
    }

    public function test_quick_reply_button_payload_and_duplicate_delivery(): void
    {
        $this->linkWhatsapp();
        $this->whatsapp('/name = Ada, /amount = 5000, /type = invoice')->assertSee('Invoice for Ada', false);

        $url = url('/api/v1/webhooks/whatsapp');
        $params = ['MessageSid' => 'SM-dup', 'From' => 'whatsapp:+2348031234567', 'Body' => '✅ Confirm', 'ButtonPayload' => 'NS_CONFIRM'];
        $sig = (new RequestValidator('twilio-test-token'))->computeSignature($url, $params);
        $this->withHeader('X-Twilio-Signature', $sig)->post($url, $params)->assertSee('INV-', false);
        // Twilio retry of the same MessageSid is ignored
        $this->withHeader('X-Twilio-Signature', $sig)->post($url, $params)->assertSee('<Response/>', false);
        $this->assertSame(1, Document::query()->count());
    }

    public function test_quota_exhaustion_message_in_chat(): void
    {
        [$merchant] = $this->linkWhatsapp();
        $merchant->update(['free_quota_used' => 15, 'free_quota_period' => now('Africa/Lagos')->format('Y-m')]);
        $this->whatsapp('/name = Ada /amount = 1000');
        $this->whatsapp('/confirm')->assertSee('used all free slips', false)->assertSee('dashboard/billing', false);
        $this->assertSame(DraftSessionStatus::AWAITING_CONFIRMATION, BotDraftSession::query()->firstOrFail()->status);
    }

    public function test_telegram_secret_and_callback_confirm_flow(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);
        $this->postJson('/api/v1/webhooks/telegram', ['update_id' => 1])->assertUnauthorized();

        [, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $code = $this->postJson('/api/v1/channels/link-code')->json('code');
        auth()->guard('web')->logout();

        $tg = fn (array $update) => $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'tg-secret')->postJson('/api/v1/webhooks/telegram', $update);
        $msg = fn (int $id, string $text) => ['update_id' => $id, 'message' => ['message_id' => $id, 'text' => $text, 'from' => ['id' => 555, 'first_name' => 'Tobi'], 'chat' => ['id' => 555, 'type' => 'private']]];

        $tg($msg(10, '/start '.$code))->assertOk();
        $tg($msg(11, "/name = Ada\n/item = 2x Shoe @ 15000"))->assertOk();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendRichMessage')
            && $r['rich_message']['blocks'][1]['type'] === 'table'
            && $r['rich_message']['blocks'][1]['is_compact'] === true
            && $r['reply_markup']['inline_keyboard'][0][0]['callback_data'] === 'ns:confirm');
        $this->assertSame('77', BotDraftSession::query()->firstOrFail()->confirmation_message_id);

        $tg(['update_id' => 12, 'callback_query' => ['id' => 'cbq1', 'data' => 'ns:confirm', 'from' => ['id' => 555], 'message' => ['message_id' => 77, 'chat' => ['id' => 555, 'type' => 'private']]]])->assertOk();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/answerCallbackQuery') && $r['callback_query_id'] === 'cbq1');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/editMessageReplyMarkup') && ($r['reply_markup']['inline_keyboard'][0][0]['disabled'] ?? false) === true);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendRichMessageDraft') && $r['can_stop'] === false);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendRichMessage') && ($r['rich_message']['blocks'][1]['type'] ?? null) === 'document');
        $this->assertSame('TELEGRAM', Document::query()->firstOrFail()->channel->value);
    }

    public function test_telegram_group_summaries_are_ephemeral(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]])]);
        [, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $code = $this->postJson('/api/v1/channels/link-code')->json('code');
        auth()->guard('web')->logout();
        $tg = fn (array $update) => $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'tg-secret')->postJson('/api/v1/webhooks/telegram', $update);
        $group = ['id' => -100123, 'type' => 'supergroup'];
        $tg(['update_id' => 20, 'message' => ['message_id' => 1, 'text' => '/link '.$code, 'from' => ['id' => 777], 'chat' => $group]]);
        $tg(['update_id' => 21, 'message' => ['message_id' => 2, 'text' => '/name = Ada /amount = 9000', 'from' => ['id' => 777], 'chat' => $group]]);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sendRichMessage') && ($r['ephemeral_message_parameters']['receiver_user_id'] ?? null) === 777);
    }
}

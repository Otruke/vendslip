<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\AuditSeverity;
use App\Enums\Role;
use App\Exceptions\DomainRuleException;
use App\Models\Merchant;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\BusinessAlert;
use App\Notifications\SupportStaffNotification;
use App\Services\Notifications\AlertCatalog;
use App\Services\Security\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Support tickets between a business and VendSlip support.
 *
 *  - Businesses open tickets and reply from their dashboard; they never see internal notes.
 *  - Support agents (and admins) reply, change status and take tickets; replies email the opener.
 *  - QA reviewers read everything, add internal notes and score the support given, but can't reply.
 *  - Super-Admins and Admins assign tickets to any agent.
 */
final class SupportDesk
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function open(Merchant $merchant, User $user, string $subject, string $category, string $body): SupportTicket
    {
        $ticket = DB::transaction(function () use ($merchant, $user, $subject, $category, $body): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'reference' => $this->reference(),
                'merchant_id' => $merchant->id,
                'opened_by_user_id' => $user->id,
                'subject' => trim($subject),
                'category' => $category,
                'priority' => $category === 'BILLING' ? 'HIGH' : 'NORMAL',
                'status' => 'OPEN',
                'last_activity_at' => now(),
            ]);
            $this->message($ticket, $user, 'CUSTOMER', $body);

            return $ticket;
        });
        $this->audit->log('support_ticket.opened', $user, $merchant, ['summary' => "{$ticket->reference} · {$ticket->subject}"]);
        $this->tellStaff($ticket, 'New ticket: '.$ticket->subject, $body);

        return $ticket;
    }

    public function customerReply(SupportTicket $ticket, User $user, string $body): SupportTicketMessage
    {
        if ($ticket->status === 'CLOSED') {
            throw new DomainRuleException('This ticket is closed. Open a new one and mention '.$ticket->reference.'.', 'ticket_closed');
        }
        $message = DB::transaction(function () use ($ticket, $user, $body): SupportTicketMessage {
            $message = $this->message($ticket, $user, 'CUSTOMER', $body);
            // A reply re-opens a resolved ticket and puts it back in support's queue.
            $ticket->fill(['status' => 'OPEN', 'resolved_at' => null, 'last_activity_at' => now()])->save();

            return $message;
        });
        $this->tellStaff($ticket, 'Customer replied: '.$ticket->subject, $body);

        return $message;
    }

    public function customerClose(SupportTicket $ticket, User $user): void
    {
        if ($ticket->status === 'CLOSED') {
            return;
        }
        $ticket->fill(['status' => 'CLOSED', 'resolved_at' => $ticket->resolved_at ?? now(), 'last_activity_at' => now()])->save();
        $this->message($ticket, null, 'SYSTEM', "{$user->name} closed this ticket.");
        $this->audit->log('support_ticket.closed', $user, $ticket->merchant_id, ['summary' => $ticket->reference]);
    }

    public function agentReply(SupportTicket $ticket, User $agent, string $body, string $status = 'AWAITING_CUSTOMER'): SupportTicketMessage
    {
        $this->ensureCanAnswer($agent);
        if (! in_array($status, ['AWAITING_CUSTOMER', 'RESOLVED', 'OPEN'], true)) {
            $status = 'AWAITING_CUSTOMER';
        }
        $message = DB::transaction(function () use ($ticket, $agent, $body, $status): SupportTicketMessage {
            $message = $this->message($ticket, $agent, 'AGENT', $body);
            $ticket->fill([
                'status' => $status,
                'assigned_to_user_id' => $ticket->assigned_to_user_id ?? $agent->id,
                'first_response_at' => $ticket->first_response_at ?? now(),
                'resolved_at' => $status === 'RESOLVED' ? now() : null,
                'last_activity_at' => now(),
            ])->save();

            return $message;
        });
        $this->audit->log('support_ticket.replied', $agent, $ticket->merchant_id, ['summary' => $ticket->reference, 'status' => $status]);
        $this->tellOpener($ticket, $status === 'RESOLVED' ? "Support resolved: {$ticket->subject}" : "Support replied: {$ticket->subject}", Str::limit($body, 240));

        return $message;
    }

    public function note(SupportTicket $ticket, User $staff, string $body): SupportTicketMessage
    {
        $this->ensureStaff($staff);
        $message = $this->message($ticket, $staff, 'AGENT', $body, internal: true);
        $this->audit->log('support_ticket.note', $staff, $ticket->merchant_id, ['summary' => $ticket->reference]);

        return $message;
    }

    public function setStatus(SupportTicket $ticket, User $agent, string $status, ?string $priority = null): void
    {
        $this->ensureCanAnswer($agent);
        if (! array_key_exists($status, SupportTicket::STATUSES)) {
            throw new DomainRuleException('Unknown status.', 'invalid_status');
        }
        $before = $ticket->status;
        $ticket->fill([
            'status' => $status,
            'priority' => $priority !== null && array_key_exists($priority, SupportTicket::PRIORITIES) ? $priority : $ticket->priority,
            'resolved_at' => in_array($status, ['RESOLVED', 'CLOSED'], true) ? ($ticket->resolved_at ?? now()) : null,
            'last_activity_at' => now(),
        ])->save();
        if ($before !== $status) {
            $this->message($ticket, null, 'SYSTEM', "{$agent->name} marked this ticket ".mb_strtolower(SupportTicket::STATUSES[$status]).'.');
            if ($status === 'RESOLVED') {
                $this->tellOpener($ticket, "Support resolved: {$ticket->subject}", 'Reply on the ticket if you still need help; it reopens automatically.');
            }
        }
        $this->audit->log('support_ticket.status', $agent, $ticket->merchant_id, ['summary' => $ticket->reference, 'from' => $before, 'to' => $status]);
    }

    /** Admins assign anyone who can answer; an agent can only take an unassigned ticket (or let go of their own). */
    public function assign(SupportTicket $ticket, User $actor, ?User $to): void
    {
        $this->ensureCanAnswer($actor);
        $isLead = $actor->isPlatformAdmin();
        if (! $isLead) {
            $allowed = ($to?->id === $actor->id && $ticket->assigned_to_user_id === null)
                || ($to === null && $ticket->assigned_to_user_id === $actor->id);
            if (! $allowed) {
                throw new DomainRuleException('Only an admin can reassign a ticket that someone else holds.', 'not_allowed', 403);
            }
        }
        if ($to !== null && (! $to->is_active || ! $to->canAnswerTickets())) {
            throw new DomainRuleException('Tickets can only go to an active support agent or admin.', 'invalid_assignee');
        }
        $ticket->fill(['assigned_to_user_id' => $to?->id])->save();
        $this->message($ticket, null, 'SYSTEM', $to === null ? "{$actor->name} unassigned this ticket." : ($to->id === $actor->id ? "{$actor->name} took this ticket." : "{$actor->name} assigned this ticket to {$to->name}."), internal: true);
        $this->audit->log('support_ticket.assigned', $actor, $ticket->merchant_id, ['summary' => $ticket->reference, 'to' => $to?->id]);
        if ($to !== null && $to->id !== $actor->id) {
            $this->safely(fn () => $to->notify(new SupportStaffNotification($ticket->reference, 'Assigned to you: '.$ticket->subject, $ticket->merchant->name, Str::limit($ticket->subject, 200), route('admin.support.show', $ticket))));
        }
    }

    /** QA (or a Super-Admin) scores the support on a ticket, 1–5, with a private note. */
    public function review(SupportTicket $ticket, User $reviewer, int $score, ?string $note): void
    {
        if (! $reviewer->isQaReviewer() && ! $reviewer->isSuperAdmin()) {
            throw new DomainRuleException('Only QA reviewers can score tickets.', 'not_allowed', 403);
        }
        $score = max(1, min(5, $score));
        DB::transaction(function () use ($ticket, $reviewer, $score, $note): void {
            $ticket->fill(['qa_score' => $score, 'qa_reviewed_by_user_id' => $reviewer->id, 'qa_reviewed_at' => now()])->save();
            $this->message($ticket, $reviewer, 'AGENT', "QA review: {$score}/5".($note !== null && trim($note) !== '' ? ' · '.trim($note) : ''), internal: true);
        });
        $this->audit->log('support_ticket.reviewed', $reviewer, $ticket->merchant_id, ['summary' => $ticket->reference, 'score' => $score], AuditSeverity::INFO);
    }

    /** @return Collection<int, User> */
    public static function answerers(): Collection
    {
        return User::query()->role([Role::SUPPORT_AGENT->value, Role::ADMIN->value, Role::SUPER_ADMIN->value])
            ->where('is_active', true)->orderBy('name')->get();
    }

    private function message(SupportTicket $ticket, ?User $author, string $kind, string $body, bool $internal = false): SupportTicketMessage
    {
        return SupportTicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'author_user_id' => $author?->id,
            'author_kind' => $kind,
            'is_internal' => $internal,
            'body' => trim($body),
        ]);
    }

    private function tellOpener(SupportTicket $ticket, string $title, string $body): void
    {
        $opener = $ticket->openedBy;
        if ($opener === null || ! $opener->is_active) {
            return;
        }
        $this->safely(fn () => $opener->notify(new BusinessAlert(AlertCatalog::TICKET_UPDATE, "[{$ticket->reference}] {$title}", $body, '/dashboard/support/'.$ticket->reference, true, $ticket->merchant->name)));
    }

    /** The assigned agent, or every agent when nobody holds the ticket yet. */
    private function tellStaff(SupportTicket $ticket, string $title, string $body): void
    {
        $assignee = $ticket->assignee;
        $to = $assignee !== null && $assignee->is_active ? collect([$assignee]) : User::query()->role(Role::SUPPORT_AGENT->value)->where('is_active', true)->get();
        if ($to->isEmpty()) {
            $to = User::query()->role(Role::SUPER_ADMIN->value)->where('is_active', true)->get();
        }
        $this->safely(fn () => Notification::send($to, new SupportStaffNotification($ticket->reference, $title, $ticket->merchant->name, Str::limit($body, 240), route('admin.support.show', $ticket))));
    }

    private function safely(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            Log::warning('support.notify_failed', ['error' => $e->getMessage()]);
        }
    }

    private function ensureStaff(User $user): void
    {
        if (! $user->is_active || ! $user->isPlatformStaff()) {
            throw new DomainRuleException('Support staff only.', 'not_allowed', 403);
        }
    }

    private function ensureCanAnswer(User $user): void
    {
        if (! $user->is_active || ! $user->canAnswerTickets()) {
            throw new DomainRuleException('QA reviewers can add notes and scores but can’t reply or change tickets.', 'not_allowed', 403);
        }
    }

    private function reference(): string
    {
        do {
            $ref = 'VST-'.Str::upper(Str::random(6));
            $ref = strtr($ref, ['0' => 'X', 'O' => 'Y', '1' => 'Z', 'I' => 'W', 'L' => 'Q']);
        } while (SupportTicket::query()->where('reference', $ref)->exists());

        return $ref;
    }
}

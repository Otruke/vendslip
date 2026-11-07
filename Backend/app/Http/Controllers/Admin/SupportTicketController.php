<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The support console: every staff member reads tickets; agents and admins answer; QA scores. */
final class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportDesk $desk) {}

    public function index(Request $request): View
    {
        $me = $this->staff($request);
        $filters = $request->validate([
            'view' => ['nullable', Rule::in(['queue', 'mine', 'unassigned', 'all', 'resolved', 'unreviewed'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $view = $filters['view'] ?? ($me->isQaReviewer() ? 'unreviewed' : 'queue');

        $query = SupportTicket::query()->with(['merchant:id,name', 'assignee:id,name', 'openedBy:id,name']);
        match ($view) {
            'mine' => $query->where('assigned_to_user_id', $me->id)->whereNotIn('status', ['RESOLVED', 'CLOSED']),
            'unassigned' => $query->whereNull('assigned_to_user_id')->whereNotIn('status', ['RESOLVED', 'CLOSED']),
            'resolved' => $query->whereIn('status', ['RESOLVED', 'CLOSED']),
            'unreviewed' => $query->whereIn('status', ['RESOLVED', 'CLOSED'])->whereNull('qa_reviewed_at'),
            'all' => $query,
            default => $query->where('status', 'OPEN'),
        };
        if (! empty($filters['q'])) {
            $q = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']).'%';
            $query->where(static fn ($w) => $w->where('reference', 'ilike', $q)->orWhere('subject', 'ilike', $q)
                ->orWhereHas('merchant', static fn ($m) => $m->where('name', 'ilike', $q)));
        }
        // Waiting longest first in the queue; most recent first elsewhere.
        $view === 'queue'
            ? $query->orderByRaw("case priority when 'URGENT' then 0 when 'HIGH' then 1 when 'NORMAL' then 2 else 3 end")->orderBy('last_activity_at')
            : $query->orderByDesc('last_activity_at');

        $open = SupportTicket::query()->whereNotIn('status', ['RESOLVED', 'CLOSED']);

        return view('admin.support.index', [
            'tickets' => $query->paginate(30)->withQueryString(),
            'view' => $view,
            'filters' => $filters,
            'me' => $me,
            'counts' => [
                'queue' => SupportTicket::query()->where('status', 'OPEN')->count(),
                'mine' => (clone $open)->where('assigned_to_user_id', $me->id)->count(),
                'unassigned' => (clone $open)->whereNull('assigned_to_user_id')->count(),
                'unreviewed' => SupportTicket::query()->whereIn('status', ['RESOLVED', 'CLOSED'])->whereNull('qa_reviewed_at')->count(),
            ],
        ]);
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        $ticket->load(['merchant', 'openedBy', 'assignee', 'qaReviewer', 'messages.author']);

        return view('admin.support.show', [
            'ticket' => $ticket,
            'me' => $this->staff($request),
            'answerers' => SupportDesk::answerers(),
            'otherTickets' => SupportTicket::query()->where('merchant_id', $ticket->merchant_id)->whereKeyNot($ticket->id)->latest('last_activity_at')->limit(5)->get(),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'then' => ['required', Rule::in(['AWAITING_CUSTOMER', 'RESOLVED', 'OPEN'])],
        ]);

        return $this->attempt(fn () => $this->desk->agentReply($ticket, $this->staff($request), $data['body'], $data['then']), 'Reply sent to the customer.');
    }

    public function note(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);

        return $this->attempt(fn () => $this->desk->note($ticket, $this->staff($request), $data['body']), 'Internal note added.');
    }

    public function status(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(SupportTicket::PRIORITIES))],
        ]);

        return $this->attempt(fn () => $this->desk->setStatus($ticket, $this->staff($request), $data['status'], $data['priority'] ?? null), 'Ticket updated.');
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer', 'exists:users,id']]);
        $to = isset($data['user_id']) ? User::query()->find((int) $data['user_id']) : null;

        return $this->attempt(fn () => $this->desk->assign($ticket, $this->staff($request), $to), $to === null ? 'Ticket unassigned.' : 'Ticket assigned.');
    }

    public function review(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->attempt(fn () => $this->desk->review($ticket, $this->staff($request), (int) $data['score'], $data['note'] ?? null), 'QA review saved.');
    }

    private function attempt(callable $action, string $done): RedirectResponse
    {
        try {
            $action();
        } catch (DomainRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', $done);
    }

    private function staff(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isPlatformStaff(), 403);

        return $user;
    }
}

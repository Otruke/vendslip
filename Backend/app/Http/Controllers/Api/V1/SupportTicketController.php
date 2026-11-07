<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\Support\SupportDesk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The business's side of support tickets. Owners see every ticket of the business; everyone else
 * sees the ones they opened. Internal staff notes are never returned.
 */
final class SupportTicketController extends ApiController
{
    public function __construct(private readonly SupportDesk $desk) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = $this->visible($request)->with('openedBy:id,name')->orderByDesc('last_activity_at')->limit(100)->get();

        return response()->json([
            'data' => $tickets->map(fn (SupportTicket $t): array => $this->summary($t))->values(),
            'categories' => SupportTicket::CATEGORIES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $ticket = $this->desk->open($this->merchant($request), $this->user($request), $data['subject'], $data['category'], $data['body']);

        return response()->json(['data' => $this->detail($ticket->refresh())], 201);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        return response()->json(['data' => $this->detail($this->find($request, $reference))]);
    }

    public function reply(Request $request, string $reference): JsonResponse
    {
        $ticket = $this->find($request, $reference);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);
        $this->desk->customerReply($ticket, $this->user($request), $data['body']);

        return response()->json(['data' => $this->detail($ticket->refresh())]);
    }

    public function close(Request $request, string $reference): JsonResponse
    {
        $ticket = $this->find($request, $reference);
        $this->desk->customerClose($ticket, $this->user($request));

        return response()->json(['data' => $this->detail($ticket->refresh())]);
    }

    /** @return Builder<SupportTicket> */
    private function visible(Request $request): Builder
    {
        $user = $this->user($request);
        $query = SupportTicket::query()->where('merchant_id', $this->merchant($request)->id);
        if (! $user->can(Permission::SETTINGS_MANAGE->value)) {
            $query->where('opened_by_user_id', $user->id);
        }

        return $query;
    }

    private function find(Request $request, string $reference): SupportTicket
    {
        return $this->visible($request)->where('reference', strtoupper($reference))->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function summary(SupportTicket $t): array
    {
        return [
            'reference' => $t->reference,
            'subject' => $t->subject,
            'category' => $t->category,
            'category_label' => SupportTicket::CATEGORIES[$t->category] ?? $t->category,
            'status' => $t->status,
            'status_label' => SupportTicket::STATUSES[$t->status] ?? $t->status,
            'opened_by' => $t->openedBy?->name,
            'last_activity_at' => $t->last_activity_at->toIso8601String(),
            'created_at' => $t->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(SupportTicket $t): array
    {
        $t->loadMissing(['openedBy:id,name', 'messages' => static fn ($q) => $q->where('is_internal', false), 'messages.author:id,name']);

        return [
            ...$this->summary($t),
            'messages' => $t->messages->map(static fn (SupportTicketMessage $m): array => [
                'id' => $m->id,
                'kind' => $m->author_kind,
                // Agents are shown by first name with "VendSlip support", never their full account.
                'author' => match ($m->author_kind) {
                    'AGENT' => trim(strtok((string) $m->author?->name, ' ') ?: 'VendSlip').' · VendSlip support',
                    'SYSTEM' => null,
                    default => $m->author?->name,
                },
                'body' => $m->body,
                'created_at' => $m->created_at->toIso8601String(),
            ])->values(),
        ];
    }
}

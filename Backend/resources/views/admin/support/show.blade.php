<x-admin.layout :title="$ticket->reference">
    <x-ui.page-header :eyebrow="'Support · '.$ticket->reference" :title="$ticket->subject" :subtitle="($ticket->merchant?->name ?? 'Business').' · opened by '.($ticket->openedBy?->name ?? 'a deleted user').' · '.$ticket->created_at->format('d M Y, H:i')">
        <x-slot:actions><x-ui.button variant="ghost" icon="chevron-right" :href="route('admin.support.index')">All tickets</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    @php $canAnswer = $me->canAnswerTickets(); @endphp
    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <ol class="space-y-3" aria-label="Conversation">
                @foreach ($ticket->messages as $m)
                    @if ($m->author_kind === 'SYSTEM')
                        <li class="text-center text-xs text-ink-muted">{{ $m->body }} · {{ $m->created_at->format('d M, H:i') }}</li>
                    @else
                        <li @class([
                            'rounded-xl border p-4',
                            'border-[#e8d9a8] bg-[#fbf6e6]' => $m->is_internal,
                            'border-vendslip-primary/30 bg-vendslip-tint' => ! $m->is_internal && $m->author_kind === 'AGENT',
                            'border-border-subtle bg-white' => ! $m->is_internal && $m->author_kind === 'CUSTOMER',
                        ])>
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <span class="font-semibold text-ink-primary">{{ $m->author?->name ?? 'Unknown' }}
                                    <span class="font-normal text-ink-muted">· {{ $m->is_internal ? 'Internal note (never shown to the business)' : ($m->author_kind === 'AGENT' ? 'VendSlip support' : 'Business') }}</span>
                                </span>
                                <time class="text-ink-muted" datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->format('d M, H:i') }}</time>
                            </div>
                            <div class="mt-2 whitespace-pre-line break-words text-sm text-ink-primary">{{ $m->body }}</div>
                        </li>
                    @endif
                @endforeach
            </ol>

            @if ($canAnswer)
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" class="space-y-3">
                        @csrf
                        <x-ui.textarea name="body" label="Reply to the business" rows="5" required maxlength="5000" helper="Emailed to the person who opened the ticket and shown in their dashboard." />
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div class="w-56"><x-ui.select name="then" label="Then mark as" value="AWAITING_CUSTOMER" :options="['AWAITING_CUSTOMER' => 'Waiting for customer', 'RESOLVED' => 'Resolved', 'OPEN' => 'Still with support']" /></div>
                            <x-ui.button icon="send">Send reply</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endif
            <x-ui.card variant="tinted">
                <form method="POST" action="{{ route('admin.support.note', $ticket) }}" class="space-y-3">
                    @csrf
                    <x-ui.textarea name="body" label="Internal note" rows="3" required maxlength="5000" helper="Only VendSlip staff see this." />
                    <div class="flex justify-end"><x-ui.button variant="secondary" icon="message">Add note</x-ui.button></div>
                </form>
            </x-ui.card>
        </div>

        <div class="space-y-4">
            <x-ui.card>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Status</dt><dd><x-ui.status-badge :status="$ticket->status" size="sm" /></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Priority</dt><dd><x-ui.status-badge :status="$ticket->priority" size="sm" /></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Category</dt><dd class="text-right font-medium">{{ \App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Assigned</dt><dd class="font-medium">{{ $ticket->assignee?->name ?? 'Nobody yet' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">First reply</dt><dd class="font-medium">{{ $ticket->first_response_at ? $ticket->created_at->diffForHumans($ticket->first_response_at, true).' after opening' : '—' }}</dd></div>
                    @if ($ticket->merchant)
                        <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Business</dt><dd class="text-right font-medium">@if ($me->isPlatformAdmin())<a class="hover:text-vendslip-primary" href="{{ route('admin.merchants.show', $ticket->merchant) }}">{{ $ticket->merchant->name }}</a>@else{{ $ticket->merchant->name }}@endif<span class="block text-xs font-normal text-ink-muted">{{ $ticket->merchant->effectiveTier()->label() }}</span></dd></div>
                    @endif
                </dl>
            </x-ui.card>

            @if ($canAnswer)
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.support.assign', $ticket) }}" class="space-y-3">
                        @csrf @method('PUT')
                        @if ($me->isPlatformAdmin())
                            <x-ui.select name="user_id" label="Assign to" :value="$ticket->assigned_to_user_id" placeholder="Nobody" :options="$answerers->mapWithKeys(fn ($u) => [$u->id => $u->name.' · '.$u->platformRoleLabel()])->all()" />
                            <x-ui.button size="sm" variant="outline" full-width>Save assignment</x-ui.button>
                        @elseif ($ticket->assigned_to_user_id === null)
                            <input type="hidden" name="user_id" value="{{ $me->id }}">
                            <x-ui.button size="sm" full-width icon="user">Take this ticket</x-ui.button>
                        @elseif ($ticket->assigned_to_user_id === $me->id)
                            <x-ui.button size="sm" variant="ghost" full-width>Let go of this ticket</x-ui.button>
                        @else
                            <p class="text-sm text-ink-secondary">{{ $ticket->assignee?->name }} has this ticket. Ask an admin to reassign it.</p>
                        @endif
                    </form>
                </x-ui.card>
                <x-ui.card>
                    <form method="POST" action="{{ route('admin.support.status', $ticket) }}" class="space-y-3">
                        @csrf @method('PUT')
                        <x-ui.select name="status" label="Status" :value="$ticket->status" :options="\App\Models\SupportTicket::STATUSES" />
                        <x-ui.select name="priority" label="Priority" :value="$ticket->priority" :options="\App\Models\SupportTicket::PRIORITIES" />
                        <x-ui.button size="sm" variant="outline" full-width>Update ticket</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            @if ($me->isQaReviewer() || $me->isSuperAdmin())
                <x-ui.card variant="tinted">
                    <form method="POST" action="{{ route('admin.support.review', $ticket) }}" class="space-y-3">
                        @csrf @method('PUT')
                        <p class="text-sm font-semibold">QA review @if ($ticket->qa_score !== null)<span class="font-normal text-ink-secondary">· {{ $ticket->qa_score }}/5 by {{ $ticket->qaReviewer?->name }}</span>@endif</p>
                        <x-ui.select name="score" label="Score" :value="$ticket->qa_score ?? 4" :options="[5 => '5 · Excellent', 4 => '4 · Good', 3 => '3 · Acceptable', 2 => '2 · Needs work', 1 => '1 · Poor']" />
                        <x-ui.textarea name="note" label="What went well or should change" rows="3" maxlength="2000" />
                        <x-ui.button size="sm" full-width>Save review</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            @if ($otherTickets->isNotEmpty())
                <x-ui.card>
                    <p class="text-sm font-semibold">Other tickets from this business</p>
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach ($otherTickets as $o)
                            <li class="flex items-center justify-between gap-2"><a class="min-w-0 truncate hover:text-vendslip-primary" href="{{ route('admin.support.show', $o) }}">{{ $o->subject }}</a><x-ui.status-badge :status="$o->status" size="sm" /></li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-admin.layout>

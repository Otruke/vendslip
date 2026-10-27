<x-admin.layout title="My profile">
    <x-ui.page-header eyebrow="Account" title="My profile" subtitle="Your details, password and sign-in security." />

    @php
        $initials = collect(preg_split('/\s+/', trim($user->name)) ?: [])->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    @endphp

    <div class="grid items-start gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
        {{-- Identity card --}}
        <aside class="lg:col-start-1 lg:row-start-1">
            <div class="relative overflow-hidden rounded-2xl bg-netslip-obsidian p-6 text-white">
                <img src="/assets/brand/netslip-icon-white.png" alt="" class="pointer-events-none absolute -top-5 -right-5 h-28 w-auto opacity-[0.06]">
                <div class="flex size-16 items-center justify-center rounded-2xl bg-netslip-primary text-2xl font-extrabold tracking-tight">{{ $initials ?: 'A' }}</div>
                <p class="mt-4 text-lg font-bold leading-tight">{{ $user->name }}</p>
                <p class="truncate text-sm text-[#cfe3e8]">{{ $user->email }}</p>
                <span class="ns-tag ns-tag--sm mt-3" style="--t:#cfe3e8; background:transparent" data-fill="solid"><span class="ns-tag__mark"></span>{{ $role }}</span>
                <dl class="mt-6 space-y-3 border-t border-white/10 pt-4 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-[#9fb9c0]">Last sign-in</dt><dd class="tabular text-right">{{ $user->last_login_at?->timezone(config('netslip.timezone'))->format('j M Y, H:i') ?? 'Not recorded' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-[#9fb9c0]">Two-factor</dt><dd>{{ $user->hasTwoFactorEnabled() ? 'On' : 'Off' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-[#9fb9c0]">Phone</dt><dd class="tabular">{{ $user->phone ?: '—' }}</dd></div>
                </dl>
            </div>
        </aside>

        <aside class="lg:col-start-1 lg:row-start-2">
            <x-ui.card>
                <p class="text-sm font-semibold">Recent activity</p>
                @forelse ($recentEvents as $event)
                    <div class="mt-3 flex items-start justify-between gap-3 text-xs">
                        <span class="font-mono text-ink-secondary">{{ $event->event }}</span>
                        <span class="tabular shrink-0 text-ink-muted">{{ $event->created_at?->timezone(config('netslip.timezone'))->format('j M, H:i') }}</span>
                    </div>
                @empty
                    <p class="mt-2 text-xs text-ink-muted">Nothing yet.</p>
                @endforelse
            </x-ui.card>
        </aside>

        <div class="space-y-6 lg:col-start-2 lg:row-span-2 lg:row-start-1">
            {{-- Details --}}
            <x-ui.card padding="none">
                <form method="POST" action="{{ route('admin.profile.update') }}">
                    @csrf @method('PUT')
                    <div class="border-b border-border-subtle px-5 py-4"><h2 class="font-semibold">Details</h2><p class="text-sm text-ink-secondary">How you appear in audit logs.</p></div>
                    <div class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                        <x-ui.input name="name" label="Full name" :value="$user->name" required maxlength="120" autocomplete="name" />
                        <x-ui.input name="phone" label="Phone" :value="$user->phone" maxlength="32" autocomplete="tel" />
                        <div class="sm:col-span-2"><x-ui.input name="email_display" label="Email" :value="$user->email" disabled helper="Ask a Super-Admin to change the sign-in email." /></div>
                    </div>
                    <div class="flex justify-end border-t border-border-subtle px-5 py-3"><x-ui.button>Save details</x-ui.button></div>
                </form>
            </x-ui.card>

            {{-- Password --}}
            <x-ui.card padding="none" id="password">
                <form method="POST" action="{{ route('admin.profile.password') }}">
                    @csrf @method('PUT')
                    <div class="border-b border-border-subtle px-5 py-4"><h2 class="font-semibold">Change password</h2><p class="text-sm text-ink-secondary">Other devices are signed out when you change it.</p></div>
                    <div class="space-y-4 px-5 py-5">
                        <x-ui.input name="current_password" type="password" label="Current password" required autocomplete="current-password" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input name="password" type="password" label="New password" required autocomplete="new-password" helper="At least 8 characters." />
                            <x-ui.input name="password_confirmation" type="password" label="Confirm new password" required autocomplete="new-password" />
                        </div>
                    </div>
                    <div class="flex justify-end border-t border-border-subtle px-5 py-3"><x-ui.button icon="key">Update password</x-ui.button></div>
                </form>
            </x-ui.card>

            {{-- Two-factor --}}
            <x-ui.card padding="none" id="two-factor">
                <div class="border-b border-border-subtle px-5 py-4"><h2 class="font-semibold">Two-factor authentication</h2><p class="text-sm text-ink-secondary">A 6-digit code from your authenticator app at every sign-in.</p></div>
                <div class="px-5 py-5">
                    @if ($user->hasTwoFactorEnabled())
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <x-ui.status-badge status="ACTIVE">On since {{ $user->two_factor_confirmed_at?->format('j M Y') }}</x-ui.status-badge>
                            <x-ui.button variant="outline" type="button" data-open="disable-2fa">Turn off</x-ui.button>
                        </div>
                    @elseif ($setupSecret)
                        <div class="grid items-center gap-5 sm:grid-cols-[176px_1fr]">
                            <div class="rounded-xl border border-border-subtle bg-white p-2 [&_svg]:h-auto [&_svg]:w-full">{!! $setupQr !!}</div>
                            <form method="POST" action="{{ route('admin.profile.two-factor.confirm') }}" class="space-y-3">
                                @csrf
                                <p class="text-sm text-ink-secondary">Scan with Google Authenticator, Authy or 1Password, or type this key:</p>
                                <p class="break-all rounded-lg bg-surface-canvas px-3 py-2 font-mono text-xs">{{ $setupSecret }}</p>
                                <div class="flex items-start gap-2">
                                    <div class="w-36"><x-ui.input name="code" aria-label="6-digit code" inputmode="numeric" maxlength="6" placeholder="123456" autocomplete="one-time-code" class="tabular tracking-widest" /></div>
                                    <x-ui.button>Confirm</x-ui.button>
                                </div>
                            </form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('admin.profile.two-factor.start') }}">@csrf<x-ui.button icon="shield">Set up two-factor</x-ui.button></form>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>

    @if ($user->hasTwoFactorEnabled())
        <x-ui.modal id="disable-2fa" title="Turn off two-factor?" size="sm">
            <form method="POST" action="{{ route('admin.profile.two-factor.disable') }}" class="space-y-4">
                @csrf @method('DELETE')
                <p class="text-sm text-ink-secondary">Your admin account will be protected by your password alone.</p>
                <x-ui.input name="confirm_password" type="password" label="Confirm with your password" required autocomplete="current-password" />
                <div class="flex justify-end gap-2"><x-ui.button variant="ghost" type="button" data-close>Cancel</x-ui.button><x-ui.button variant="danger">Turn off</x-ui.button></div>
            </form>
        </x-ui.modal>
    @endif
</x-admin.layout>

<x-admin.layout :title="'Edit '.$admin->name">
    <x-ui.page-header eyebrow="Super-Admin · Platform admins" :title="'Edit '.$admin->name" subtitle="Changing the email, role or password signs them out everywhere so the change applies at once.">
        <x-slot:actions><x-ui.button variant="ghost" icon="users" :href="route('admin.admins.index')">All admins</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('admin.admins.update', $admin) }}" class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3" data-confirm="Save changes to this admin? This is logged as a critical event.">
        @csrf @method('PUT')
        <x-ui.card padding="none" class="lg:col-span-2">
            <div class="border-b border-border-subtle px-5 py-4"><h2 class="font-semibold">Details and access</h2></div>
            <div class="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2">
                <x-ui.input name="name" label="Full name" :value="$admin->name" required maxlength="120" autocomplete="off" />
                <x-ui.input name="phone" label="Phone" :value="$admin->phone" maxlength="32" autocomplete="off" />
                <x-ui.input name="email" type="email" label="Email" :value="$admin->email" required maxlength="255" autocomplete="off" helper="Their sign-in email." />
                <x-ui.select name="role" label="Role" :value="$role" :options="['Admin' => 'Admin', 'SuperAdmin' => 'Super Admin']" />
            </div>
            <div class="border-t border-border-subtle px-5 py-5">
                <h3 class="text-sm font-semibold">Set a new password <span class="font-normal text-ink-muted">(optional)</span></h3>
                <p class="mt-0.5 text-xs text-ink-secondary">Leave blank to keep their current password. 12+ characters with upper, lower and a number.</p>
                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.input name="password" type="password" label="New password" autocomplete="new-password" />
                    <x-ui.input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card variant="tinted">
            <h2 class="font-semibold">Confirm it's you</h2>
            <p class="mt-1 text-sm text-ink-secondary">Enter your own password to save changes to another admin.</p>
            <div class="mt-4"><x-ui.input name="your_password" type="password" label="Your password" required autocomplete="current-password" /></div>
            <dl class="mt-5 space-y-2 border-t border-border-subtle pt-4 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Two-factor</dt><dd class="font-medium">{{ $admin->hasTwoFactorEnabled() ? 'On' : 'Off' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Status</dt><dd class="font-medium">{{ $admin->is_active ? 'Active' : 'Disabled' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Last sign-in</dt><dd class="font-medium">{{ $admin->last_login_at?->diffForHumans() ?? 'Never' }}</dd></div>
            </dl>
            <div class="mt-5"><x-ui.button icon="shield" full-width>Save changes</x-ui.button></div>
        </x-ui.card>
    </form>
</x-admin.layout>

<x-admin.layout title="Platform Admins">
    <x-ui.page-header eyebrow="Super-Admin" title="Platform admins" subtitle="Admins manage merchants. Super-Admins also control pricing, templates and credentials." >
        <x-slot:actions><x-ui.button icon="plus" type="button" data-open="admin-modal">Add admin</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-ui.data-table :headers="['Name', 'Email', 'Role', '2FA', 'Last sign-in', 'Status', '']">
        @foreach ($admins as $admin)
            <tr class="hover:bg-surface-canvas">
                <td class="px-4 py-3 font-semibold">{{ $admin->name }}</td>
                <td class="px-4 py-3">{{ $admin->email }}</td>
                <td class="px-4 py-3">{{ $admin->isSuperAdmin() ? 'Super Admin' : 'Admin' }}</td>
                <td class="px-4 py-3">@if ($admin->hasTwoFactorEnabled())<x-ui.status-badge status="ACTIVE" size="sm">On</x-ui.status-badge>@else<x-ui.status-badge status="WARNING" size="sm">Off</x-ui.status-badge>@endif</td>
                <td class="px-4 py-3 text-ink-secondary">{{ $admin->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td class="px-4 py-3"><x-ui.status-badge :status="$admin->is_active ? 'ACTIVE' : 'SUSPENDED'" size="sm">{{ $admin->is_active ? 'Active' : 'Disabled' }}</x-ui.status-badge></td>
                <td class="px-4 py-3 text-right">
                    @if (auth()->id() !== $admin->id)
                        <form method="POST" action="{{ route('admin.admins.toggle', $admin) }}" data-confirm="Change this admin's access?">@csrf @method('PUT')<x-ui.button size="xs" :variant="$admin->is_active ? 'outline' : 'success'">{{ $admin->is_active ? 'Disable' : 'Enable' }}</x-ui.button></form>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-ui.data-table>

    <x-ui.modal id="admin-modal" title="Add platform admin">
        <form method="POST" action="{{ route('admin.admins.store') }}" class="space-y-4">
            @csrf
            <x-ui.input name="name" label="Full name" required />
            <x-ui.input name="email" type="email" label="Email" required />
            <x-ui.select name="role" label="Role" :options="['Admin' => 'Admin', 'SuperAdmin' => 'Super Admin']" />
            <x-ui.input name="password" type="password" label="Temporary password" helper="12+ chars with upper, lower and a number." required />
            <div class="flex justify-end gap-2"><x-ui.button variant="ghost" type="button" data-close>Cancel</x-ui.button><x-ui.button>Create admin</x-ui.button></div>
        </form>
    </x-ui.modal>
</x-admin.layout>

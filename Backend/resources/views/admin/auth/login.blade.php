<x-admin.guest-layout title="Sign in">
    <h1 class="mt-8 text-2xl font-bold tracking-tight">Admin sign in</h1>
    <p class="mt-1 text-sm text-ink-secondary">Platform staff only. Every attempt is logged.</p>
    @if (session('error'))<x-ui.alert tone="danger" class="mt-4">{{ session('error') }}</x-ui.alert>@endif
    <form method="POST" action="{{ route('admin.login.attempt') }}" class="mt-6 space-y-4">
        @csrf
        <x-ui.input name="email" type="email" label="Email" autocomplete="username" required autofocus />
        <x-ui.input name="password" type="password" label="Password" autocomplete="current-password" required />
        <x-ui.button full-width size="lg">Sign in</x-ui.button>
    </form>
</x-admin.guest-layout>

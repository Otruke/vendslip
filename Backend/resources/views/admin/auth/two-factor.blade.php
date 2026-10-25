<x-admin.guest-layout title="Two-factor">
    <h1 class="mt-8 text-2xl font-bold tracking-tight">Enter your code</h1>
    <p class="mt-1 text-sm text-ink-secondary">Open your authenticator app for the 6-digit code.</p>
    <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="mt-6 space-y-4">
        @csrf
        <x-ui.input name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
        <x-ui.button full-width size="lg">Verify</x-ui.button>
    </form>
</x-admin.guest-layout>

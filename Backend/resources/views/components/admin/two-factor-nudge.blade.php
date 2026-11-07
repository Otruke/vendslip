{{-- A small, animated pill (not a banner) until this admin turns on two-factor sign-in. --}}
@props(['align' => 'left', 'compact' => false])
<details class="vs-2fa relative" data-close-outside>
    <summary class="vs-nudge inline-flex h-8 cursor-pointer list-none items-center gap-1.5 whitespace-nowrap rounded-full border border-[#94600f]/30 bg-[#fdf5e6] px-2.5 text-xs font-semibold text-[#94600f] hover:border-[#94600f]/60 [&::-webkit-details-marker]:hidden"
             aria-label="Your account isn’t fully protected. Turn on two-factor sign-in">
        <span class="relative inline-flex"><span class="vs-nudge__halo" aria-hidden="true"></span><x-ui.icon name="shield" class="vs-nudge__icon size-4" /></span>
        <span @class(['hidden sm:inline' => $compact])>Secure your account</span>
    </summary>
    <div role="dialog" aria-label="Two-factor sign-in" @class(['absolute z-40 mt-2 w-[min(18rem,calc(100vw-2rem))] rounded-xl border border-border-subtle bg-white p-4 text-left shadow-lg', 'left-0' => $align === 'left', 'right-0' => $align === 'right'])>
        <p class="text-sm font-semibold text-ink-primary">Turn on two-factor sign-in</p>
        <p class="mt-1.5 text-xs leading-relaxed text-ink-secondary">Admin accounts can see every business. A 6-digit authenticator code keeps that access yours alone.</p>
        <div class="mt-3 flex justify-end"><x-ui.button size="sm" icon="shield" :href="route('admin.profile.show').'#two-factor'">Set it up</x-ui.button></div>
    </div>
</details>

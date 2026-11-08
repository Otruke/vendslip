@php
    $date = fn (string $v): string => \Illuminate\Support\Carbon::parse($v)->format('j M Y');
    $pct = $total > 0 ? (int) floor($accepted * 100 / $total) : 0;
    $upcoming = ! $latest['in_force'];
@endphp
<x-admin.layout title="Terms & Privacy">
    <x-ui.page-header eyebrow="Super-Admin" title="Terms & Privacy" subtitle="Publish new versions and see who has accepted them.">
        <x-slot:actions>
            <x-ui.button :href="$appUrl.'/terms'" variant="secondary" size="sm" icon="file-text" target="_blank" rel="noopener">Terms of Service</x-ui.button>
            <x-ui.button :href="$appUrl.'/privacy'" variant="secondary" size="sm" icon="shield" target="_blank" rel="noopener">Privacy Policy</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.stat-card variant="obsidian" title="In force" :value="'v'.$inForce" icon="check-circle" :hint="'Since '.$date($inForce)" />
        <x-ui.stat-card variant="deep" title="Newest" :value="'v'.$latest['version']" icon="clock" :hint="$upcoming ? 'Applies from '.$date($latest['version']) : 'Already applies'" />
        <x-ui.stat-card variant="teal" title="Accepted newest" :value="$pct.'%'" icon="users" :hint="number_format($accepted).' of '.number_format($total).' active business users'" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <x-ui.card variant="elevated" class="lg:col-span-3">
            <form method="POST" action="{{ route('admin.legal.publish') }}" class="space-y-4" data-confirm="Publish this version? Everyone will be asked to accept it.">
                @csrf
                <div class="flex items-center gap-2">
                    <x-ui.heading level="2" variant="card">Publish a new version</x-ui.heading>
                    <x-ui.info label="How publishing works">Change the wording on the Terms and Privacy pages first. From publishing until the date below, people see a notice and can accept early. From that date, anyone who hasn’t accepted must do so before they can change anything. Payments always ask.</x-ui.info>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input type="date" name="effective_on" label="Applies from" :value="old('effective_on', \Illuminate\Support\Carbon::parse($today)->addDays(14)->toDateString())" :min="$today"
                        info="This date becomes the version number. Laws such as GDPR expect fair notice of material changes, so 14–30 days ahead is good practice. Use today only for small or urgent fixes." />
                </div>
                <x-ui.textarea name="summary" label="What changed" rows="3" :value="old('summary')" placeholder="e.g. Added Paystack as a payment provider; data is now also backed up in the EU."
                    info="Plain words, a sentence or two. People see this on the accept screen, in the email and at the top of both pages." />
                <div class="space-y-2.5">
                    <label class="flex items-start gap-2.5 text-sm"><input type="checkbox" name="live" value="1" @checked(old('live')) class="mt-0.5 size-4 rounded border-slate-300 text-vendslip-primary focus:ring-vendslip-primary/30"><span>The new wording is already live on the Terms and Privacy pages.</span></label>
                    @error('live')<p class="text-xs text-[#a5341f]">{{ $message }}</p>@enderror
                    <input type="hidden" name="notify" value="0">
                    <label class="flex items-start gap-2.5 text-sm"><input type="checkbox" name="notify" value="1" @checked(old('notify', true)) class="mt-0.5 size-4 rounded border-slate-300 text-vendslip-primary focus:ring-vendslip-primary/30"><span>Tell business owners now, by email and in the app.</span></label>
                </div>
                <x-ui.button icon="send">Publish version</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card class="lg:col-span-2">
            <x-ui.heading level="2" variant="card">Versions</x-ui.heading>
            <ol class="mt-3 divide-y divide-border-subtle">
                @foreach ($history as $row)
                    <li class="py-3 first:pt-0 last:pb-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="tabular text-sm font-semibold">v{{ $row['version'] }}</p>
                            @if ($row['version'] === $inForce)
                                <span class="rounded-full bg-vendslip-tint px-2 py-0.5 text-[11px] font-semibold text-vendslip-deep">In force</span>
                            @elseif (strcmp($row['version'], $inForce) > 0)
                                <span class="rounded-full border border-vendslip-primary/40 px-2 py-0.5 text-[11px] font-semibold text-vendslip-primary">From {{ $date($row['version']) }}</span>
                            @else
                                <span class="text-[11px] text-ink-muted">Replaced</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-ink-secondary">{{ $row['summary'] ?? 'First version, set in the server settings.' }}</p>
                        @if ($row['published_at'])<p class="mt-1 text-xs text-ink-muted">Published {{ \Illuminate\Support\Carbon::parse($row['published_at'])->format('j M Y, H:i') }} by {{ $row['published_by'] }}</p>@endif
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    </div>
</x-admin.layout>

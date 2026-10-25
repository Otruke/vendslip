@props(['headers' => [], 'paginator' => null, 'empty' => 'Nothing here yet.'])
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-border-subtle bg-white']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-border-subtle text-sm">
            <thead class="sticky top-0 bg-surface-canvas">
                <tr>
                    @foreach ($headers as $header)
                        @php $h = is_array($header) ? $header : ['label' => $header]; @endphp
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted {{ ($h['align'] ?? '') === 'right' ? 'text-right' : '' }}">{{ $h['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @if (trim($slot) === '')
                    <tr><td colspan="{{ max(1, count($headers)) }}" class="px-4 py-12 text-center text-sm text-ink-muted">{{ $empty }}</td></tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>
    @if ($paginator && $paginator->hasPages())
        <div class="border-t border-border-subtle px-4 py-3">{{ $paginator->onEachSide(1)->links('components.ui.pagination') }}</div>
    @endif
</div>

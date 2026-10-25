@props(['status', 'size' => 'md', 'showDot' => true])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $map = [
        'PAID' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'bg-emerald-500', 'Paid'],
        'SUCCESSFUL' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'bg-emerald-500', 'Successful'],
        'ACTIVE' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'bg-emerald-500', 'Active'],
        'INFO' => ['bg-slate-100 text-slate-600 border-slate-200', 'bg-slate-400', 'Info'],
        'PARTIAL' => ['bg-amber-50 text-amber-700 border-amber-200', 'bg-amber-500', 'Part paid'],
        'PENDING' => ['bg-amber-50 text-amber-700 border-amber-200', 'bg-amber-500', 'Pending'],
        'WARNING' => ['bg-amber-50 text-amber-700 border-amber-200', 'bg-amber-500', 'Warning'],
        'UNPAID' => ['bg-rose-50 text-rose-700 border-rose-200', 'bg-rose-500', 'Unpaid'],
        'OVERDUE' => ['bg-rose-50 text-rose-700 border-rose-200', 'bg-rose-500', 'Overdue'],
        'FAILED' => ['bg-rose-50 text-rose-700 border-rose-200', 'bg-rose-500', 'Failed'],
        'SUSPENDED' => ['bg-rose-50 text-rose-700 border-rose-200', 'bg-rose-500', 'Suspended'],
        'CRITICAL' => ['bg-rose-50 text-rose-700 border-rose-200', 'bg-rose-500', 'Critical'],
        'DRAFT' => ['bg-slate-100 text-slate-600 border-slate-200', 'bg-slate-400', 'Draft'],
        'VOID' => ['bg-slate-100 text-slate-600 border-slate-200', 'bg-slate-400', 'Void'],
        'CANCELLED' => ['bg-slate-100 text-slate-600 border-slate-200', 'bg-slate-400', 'Cancelled'],
        'IMMUTABLE' => ['bg-teal-50 text-netslip-deep border-teal-200', 'bg-netslip-deep', 'Immutable'],
        'MUTABLE_WITH_AUDIT' => ['bg-sky-50 text-sky-700 border-sky-200', 'bg-sky-500', 'Mutable · Audited'],
        'MUTABLE_UNRESTRICTED' => ['bg-sky-50 text-sky-700 border-sky-200', 'bg-sky-500', 'Mutable'],
        'FREE' => ['bg-slate-100 text-slate-600 border-slate-200', 'bg-slate-400', 'Free'],
        'PRO' => ['bg-netslip-tint text-netslip-deep border-[#cfe3e8]', 'bg-netslip-primary', 'Pro'],
        'BUSINESS_PLUS' => ['bg-netslip-deep text-white border-netslip-deep', 'bg-white', 'Business Plus'],
        'WHATSAPP' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'bg-emerald-500', 'WhatsApp'],
        'TELEGRAM' => ['bg-sky-50 text-sky-700 border-sky-200', 'bg-sky-500', 'Telegram'],
        'INSTAGRAM' => ['bg-pink-50 text-pink-700 border-pink-200', 'bg-pink-500', 'Instagram'],
        'FACEBOOK' => ['bg-blue-50 text-blue-700 border-blue-200', 'bg-blue-600', 'Facebook'],
        'WEB' => ['bg-slate-100 text-slate-600 border-slate-200', 'bg-slate-400', 'Web'],
    ];
    [$classes, $dot, $label] = $map[$value] ?? ['bg-slate-100 text-slate-600 border-slate-200', 'bg-slate-400', ucwords(strtolower(str_replace('_', ' ', $value)))];
    $sizeClass = $size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-0.5 text-xs';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border font-semibold whitespace-nowrap $classes $sizeClass"]) }}>
    @if ($value === 'IMMUTABLE')<x-ui.icon name="lock" class="size-3" />@elseif ($showDot)<span class="size-1.5 rounded-full {{ $dot }} @if (in_array($value, ['PENDING', 'OVERDUE'])) animate-pulse @endif"></span>@endif
    {{ $slot->isEmpty() ? $label : $slot }}
</span>

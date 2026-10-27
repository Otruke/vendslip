@props(['status', 'size' => 'md', 'showDot' => true])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    // [tone, filled mark = settled/final, label]
    $map = [
        'PAID' => ['settled', true, 'Paid'],
        'SUCCESSFUL' => ['settled', true, 'Successful'],
        'ACTIVE' => ['settled', true, 'Active'],
        'PARTIAL' => ['partial', false, 'Part paid'],
        'PENDING' => ['partial', false, 'Pending'],
        'WARNING' => ['partial', false, 'Warning'],
        'UNPAID' => ['open', false, 'Unpaid'],
        'OVERDUE' => ['open', false, 'Overdue'],
        'FAILED' => ['open', true, 'Failed'],
        'SUSPENDED' => ['open', true, 'Suspended'],
        'CRITICAL' => ['open', true, 'Critical'],
        'INFO' => ['info', false, 'Info'],
        'DRAFT' => ['neutral', false, 'Draft'],
        'VOID' => ['neutral', true, 'Void'],
        'CANCELLED' => ['neutral', true, 'Cancelled'],
        'SYSTEM' => ['brand', true, 'System'],
        'IMMUTABLE' => ['brand', true, 'Immutable'],
        'MUTABLE_WITH_AUDIT' => ['info', false, 'Mutable · Audited'],
        'MUTABLE_UNRESTRICTED' => ['info', false, 'Mutable'],
        'FREE' => ['neutral', false, 'Free'],
        'PRO' => ['brand', false, 'Pro'],
        'BUSINESS_PLUS' => ['brand', true, 'Business Plus'],
        'WHATSAPP' => ['whatsapp', true, 'WhatsApp'],
        'TELEGRAM' => ['telegram', true, 'Telegram'],
        'INSTAGRAM' => ['instagram', true, 'Instagram'],
        'FACEBOOK' => ['facebook', true, 'Facebook'],
        'WEB' => ['neutral', true, 'Web'],
    ];
    [$tone, $solid, $label] = $map[$value] ?? ['neutral', false, ucwords(strtolower(str_replace('_', ' ', $value)))];
    $inverse = $value === 'BUSINESS_PLUS';
@endphp
<span {{ $attributes->merge(['class' => 'ns-tag ns-tag--'.$tone.($size === 'sm' ? ' ns-tag--sm' : '').($inverse ? ' ns-tag--inverse' : '')]) }}
      data-fill="{{ $solid ? 'solid' : 'hollow' }}" @if (in_array($value, ['PENDING', 'OVERDUE'], true)) data-pulse @endif>
    @if ($value === 'IMMUTABLE')<x-ui.icon name="lock" class="size-3" />@elseif ($showDot)<span class="ns-tag__mark" aria-hidden="true"></span>@endif
    {{ $slot->isEmpty() ? $label : $slot }}
</span>

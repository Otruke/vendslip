@php
    // Every account the business shows on slips; older data (or the admin template preview) falls back to the single bank.
    $payouts = $d['payouts'] ?? [];
    if ($payouts === [] && $d['bank']) {
        $payouts = [['kind' => 'BANK', 'label' => null, 'bank_name' => $d['bank']['name'], 'account_number' => $d['bank']['number'], 'account_name' => $d['bank']['account_name']]];
    }
    $banks = array_values(array_filter($payouts, fn ($p) => $p['kind'] === 'BANK'));
    $wallets = array_values(array_filter($payouts, fn ($p) => $p['kind'] === 'CRYPTO'));
@endphp
@if ($banks !== [] || $wallets !== [])
    <div class="bank">
        @if ($banks !== [])
            <div class="label">Pay by bank transfer</div>
            @foreach ($banks as $b)
                <div style="margin-top:{{ $loop->first ? 4 : 7 }}px;">
                    <div style="font-weight:700; font-size:13px;" class="mono">{{ $b['account_number'] }}@if (! empty($b['currency'])) <span style="font-weight:400; font-size:10px;">{{ $b['currency'] }}</span>@endif</div>
                    <div>{{ $b['bank_name'] }} &middot; {{ $b['account_name'] }}@if (! empty($b['label'])) &middot; {{ $b['label'] }}@endif</div>
                </div>
            @endforeach
        @endif
        @if ($wallets !== [])
            <div class="label" style="margin-top:{{ $banks !== [] ? 9 : 0 }}px;">Pay with crypto</div>
            @foreach ($wallets as $w)
                <div style="margin-top:{{ $loop->first ? 4 : 7 }}px;">
                    <div style="font-weight:700;">{{ $w['asset'] }} <span style="font-weight:400;">on {{ $w['network'] }}</span>@if (! empty($w['label'])) &middot; {{ $w['label'] }}@endif</div>
                    <div class="mono" style="font-size:10px; word-break:break-all;">{{ $w['wallet_address'] }}</div>
                </div>
            @endforeach
            <div style="margin-top:4px; font-size:9px;">Send only {{ implode(' / ', array_unique(array_map(fn ($w) => $w['asset'].' ('.$w['network'].')', $wallets))) }} to these addresses.</div>
        @endif
        @if ($d['pay_url'])
            <div style="margin-top:6px;">Or pay online: <span style="color:{{ $d['primary'] }}; font-weight:700;">{{ $d['pay_url'] }}</span></div>
        @endif
    </div>
@elseif ($d['pay_url'])
    <div class="bank">
        <div class="label">Pay online</div>
        <div style="margin-top:4px; color:{{ $d['primary'] }}; font-weight:700;">{{ $d['pay_url'] }}</div>
    </div>
@endif

@if ($d['bank'])
    <div class="bank">
        <div class="label">Pay by bank transfer</div>
        <div style="margin-top:4px; font-weight:700; font-size:13px;" class="mono">{{ $d['bank']['number'] }}</div>
        <div>{{ $d['bank']['name'] }} &middot; {{ $d['bank']['account_name'] }}</div>
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

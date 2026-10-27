<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $d['title'] }} {{ $d['document']->doc_number }}</title>
    @include('pdf.partials.fonts')
    <style>
        @page { margin: 26px 30px; }
        body { font-size: 10.5px; }
        .center { text-align: center; }
        .rule { border-top: 1px dashed #CBD5E1; margin: 12px 0; height: 0; }
        .biz { font-size: 16px; font-weight: 800; color: {{ $d['secondary'] }}; }
        .doc { font-size: 9px; letter-spacing: 3px; text-transform: uppercase; color: {{ $d['primary'] }}; font-weight: 800; margin-top: 8px; }
        .items th { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #64748B; padding: 4px 0; border-bottom: 1px solid #E2E8F0; font-weight: 700; }
        .items td { padding: 6px 0; border-bottom: 1px solid #F1F5F9; }
        .totals td { padding: 3px 0; }
        .totals .grand td { font-size: 16px; font-weight: 900; color: {{ $d['secondary'] }}; padding-top: 8px; border-top: 2px solid {{ $d['primary'] }}; }
        .totals .balance td { color: #A5341F; font-weight: 800; }
        .bank { border: 1px solid #E2E8F0; border-radius: 8px; padding: 8px 10px; }
    </style>
</head>
<body>
    @if ($d['is_void'])<div class="void-stamp" style="font-size:64px;">VOID</div>@endif
    @if ($d['watermark'])@include('pdf.partials.watermark')@endif

    <div class="center">
        @if ($d['logo'])<img src="{{ $d['logo'] }}" style="height:42px; max-width:140px;" alt=""><br>@endif
        <div class="biz">{{ $d['merchant']->name }}</div>
        <div class="muted" style="font-size:9px;">{{ collect([$d['merchant']->phone, $d['merchant']->address])->filter()->implode(' · ') }}</div>
        <div class="doc">{{ $d['title'] }} &middot; {{ $d['document']->doc_number }}</div>
        <div style="margin-top:6px;"><span class="pill pill-{{ $d['status'] }}"><span class="mark">{{ in_array($d['status'], ['PAID', 'VOID'], true) ? '◆' : '◇' }}</span>{{ $d['status_label'] }}</span></div>
    </div>

    <div class="rule"></div>

    <table>
        <tr>
            <td style="vertical-align:top;">
                <div class="label">For</div>
                <div style="font-weight:800; font-size:12px;">{{ $d['document']->customer_name }}</div>
                @if ($d['document']->customer_phone)<div class="muted">{{ $d['document']->customer_phone }}</div>@endif
                @if ($d['document']->customer_address)<div class="muted">{{ $d['document']->customer_address }}</div>@endif
            </td>
            <td style="vertical-align:top; text-align:right;">
                <div class="label">Date</div>
                <div style="font-weight:700;">{{ $d['issued_date'] }}</div>
                @if ($d['due_date'])<div class="muted">Due {{ $d['due_date'] }}</div>@endif
                @if ($d['parent_number'])<div class="muted">Ref {{ $d['parent_number'] }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top:12px;">
        <thead><tr><th style="text-align:left;">Item</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach ($d['items'] as $item)
                <tr>
                    <td>
                        <div style="font-weight:700;">{{ $item['description'] }}</div>
                        <div class="muted" style="font-size:9px;">{{ number_format($item['quantity']) }} &times; {!! $item['unit'] !!}</div>
                    </td>
                    <td class="num" style="font-weight:700;">{!! $item['total'] !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top:10px;">@include('pdf.partials.totals')</div>

    @if ($d['bank'] || $d['pay_url'])<div style="margin-top:12px;">@include('pdf.partials.bank')</div>@endif
    @if ($d['document']->notes)<div style="margin-top:10px;"><span class="label">Note</span> {{ $d['document']->notes }}</div>@endif

    <div class="rule"></div>
    <div class="center">
        <img src="{{ $d['qr'] }}" style="width:84px; height:84px;" alt="Verify">
        <div class="mono" style="font-weight:800; margin-top:2px;">{{ $d['verification_code'] }}</div>
        <div class="muted" style="font-size:8px;">Scan to verify this slip</div>
        <div class="script" style="font-size:20px; color:{{ $d['secondary'] }}; margin-top:8px;">{{ $d['footer'] }}</div>
        <div class="script" style="font-size:13px; color:{{ $d['primary'] }};">{{ $d['tagline'] }}</div>
    </div>
</body>
</html>

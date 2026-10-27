<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $d['title'] }} {{ $d['document']->doc_number }}</title>
    @include('pdf.partials.fonts')
    <style>
        @page { margin: 0; }
        body { padding: 0 0 40px 0; }
        .band { background: {{ $d['secondary'] }}; color: #FFFFFF; padding: 26px 36px 22px; }
        .band .biz { font-size: 19px; font-weight: 800; letter-spacing: -0.2px; }
        .band .biz-meta { font-size: 9.5px; color: #CFE3E8; margin-top: 3px; }
        .band .doc-title { font-size: 11px; letter-spacing: 3px; text-transform: uppercase; color: #CFE3E8; font-weight: 700; text-align: right; }
        .band .doc-number { font-size: 22px; font-weight: 900; text-align: right; letter-spacing: 0.5px; }
        .accent { height: 5px; background: {{ $d['primary'] }}; }
        .wrap { padding: 22px 36px 0; }
        .meta td { vertical-align: top; padding: 0; }
        .items { margin-top: 20px; }
        .items th { background: {{ $d['primary'] }}; color: #FFFFFF; font-size: 8.5px; text-transform: uppercase; letter-spacing: 1.2px; padding: 8px 10px; font-weight: 700; }
        .items td { padding: 9px 10px; border-bottom: 1px solid #E2E8F0; }
        .items tbody tr:nth-child(even) td { background: #F8FAFC; }
        .totals td { padding: 4px 0; font-size: 11px; }
        .totals .grand td { font-weight: 900; font-size: 15px; color: #FFFFFF; background: {{ $d['secondary'] }}; padding: 9px 12px; }
        .totals .balance td { color: #A5341F; font-weight: 800; font-size: 12px; padding-top: 7px; }
        .bank { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 10px 12px; }
        .thanks { font-size: 22px; color: {{ $d['secondary'] }}; }
    </style>
</head>
<body>
    @if ($d['is_void'])<div class="void-stamp">VOID</div>@endif
    @if ($d['watermark'])@include('pdf.partials.watermark')@endif

    <div class="band">
        <table>
            <tr>
                <td style="vertical-align:middle;">
                    <table style="width:auto;">
                        <tr>
                            @if ($d['logo'])
                                <td style="padding-right:12px; vertical-align:middle;"><img src="{{ $d['logo'] }}" style="height:46px; max-width:120px; background:#FFFFFF; border-radius:6px; padding:3px;" alt=""></td>
                            @endif
                            <td style="vertical-align:middle;">
                                <div class="biz">{{ $d['merchant']->name }}</div>
                                <div class="biz-meta">
                                    {{ collect([$d['merchant']->address, $d['merchant']->phone, $d['merchant']->email])->filter()->implode('  ·  ') }}
                                </div>
                                @if ($d['merchant']->rc_number || $d['merchant']->tax_id)
                                    <div class="biz-meta">
                                        @if ($d['merchant']->rc_number) RC {{ $d['merchant']->rc_number }} @endif
                                        @if ($d['merchant']->tax_id) &nbsp;TIN {{ $d['merchant']->tax_id }} @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="vertical-align:middle; width:40%;">
                    <div class="doc-title">{{ $d['title'] }}</div>
                    <div class="doc-number">{{ $d['document']->doc_number }}</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="accent"></div>

    <div class="wrap">
        <table class="meta">
            <tr>
                <td style="width:50%;">
                    <div class="label">Billed to</div>
                    <div style="font-size:14px; font-weight:800; margin-top:4px;">{{ $d['document']->customer_name }}</div>
                    @if ($d['document']->customer_address)<div class="muted">{{ $d['document']->customer_address }}</div>@endif
                    @if ($d['document']->customer_phone)<div class="muted">{{ $d['document']->customer_phone }}</div>@endif
                    @if ($d['document']->customer_email)<div class="muted">{{ $d['document']->customer_email }}</div>@endif
                </td>
                <td style="width:50%; text-align:right;">
                    <span class="pill pill-{{ $d['status'] }}"><span class="mark">{{ in_array($d['status'], ['PAID', 'VOID'], true) ? '◆' : '◇' }}</span>{{ $d['status_label'] }}</span>
                    <div style="margin-top:8px;"><span class="muted">Issued</span> &nbsp;<strong>{{ $d['issued_at'] }}</strong></div>
                    @if ($d['due_date'])<div><span class="muted">Due</span> &nbsp;<strong>{{ $d['due_date'] }}</strong></div>@endif
                    @if ($d['parent_number'])<div><span class="muted">Ref</span> &nbsp;<strong>{{ $d['parent_number'] }}</strong></div>@endif
                    @if ($d['document']->revision_number > 1)<div class="muted">Revision {{ $d['document']->revision_number }}</div>@endif
                </td>
            </tr>
        </table>

        @include('pdf.partials.items-table')

        <table style="margin-top:16px;">
            <tr>
                <td style="width:52%; vertical-align:top; padding-right:24px;">
                    @include('pdf.partials.bank')
                    @if ($d['document']->notes)
                        <div style="margin-top:12px;">
                            <div class="label">Notes</div>
                            <div style="margin-top:3px;">{{ $d['document']->notes }}</div>
                        </div>
                    @endif
                    @if ($d['is_void'] && $d['document']->void_reason)
                        <div style="margin-top:12px; color:#A5341F;"><strong>Voided:</strong> {{ $d['document']->void_reason }}</div>
                    @endif
                </td>
                <td style="width:48%; vertical-align:top;">
                    @include('pdf.partials.totals')
                </td>
            </tr>
        </table>

        <table style="margin-top:26px; border-top:1px dashed #CBD5E1;">
            <tr>
                <td style="padding-top:16px; vertical-align:top;">@include('pdf.partials.qr')</td>
                <td style="padding-top:16px; text-align:right; vertical-align:middle;">
                    <div class="thanks script">{{ $d['footer'] }}</div>
                    <div class="script" style="font-size:15px; color:{{ $d['primary'] }};">{{ $d['tagline'] }}</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

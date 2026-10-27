<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $data['title'] }} {{ $data['document']->doc_number }}</title>
    @include('pdf.partials.fonts')
    <style>
        @page { margin: 28px 32px; }
        .items th { background: {{ $data['primary'] }}; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; padding: 7px 8px; }
        .items td { padding: 7px 8px; border-bottom: 1px solid #E2E8F0; }
        .totals td { padding: 4px 0; }
        .totals .grand td { font-weight: 800; font-size: 14px; border-top: 2px solid {{ $data['secondary'] }}; padding-top: 8px; }
        .totals .balance td { color: #A5341F; font-weight: 800; }
        .bank { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 10px; }
    </style>
</head>
<body>
    @if ($data['is_void'])<div class="void-stamp">VOID</div>@endif
    {!! $body !!}
</body>
</html>

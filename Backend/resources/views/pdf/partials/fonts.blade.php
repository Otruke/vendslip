@php
    $font = static fn (string $file): string => str_replace('\\', '/', resource_path('fonts/'.$file));
@endphp
<style>
    @font-face { font-family: 'Lupio'; font-weight: 400; font-style: normal; src: url('{{ $font('lupio-400.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Lupio'; font-weight: 500; font-style: normal; src: url('{{ $font('lupio-500.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Lupio'; font-weight: 600; font-style: normal; src: url('{{ $font('lupio-600.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Lupio'; font-weight: 700; font-style: normal; src: url('{{ $font('lupio-700.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Lupio'; font-weight: 800; font-style: normal; src: url('{{ $font('lupio-800.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Lupio'; font-weight: 900; font-style: normal; src: url('{{ $font('lupio-900.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Inter'; font-weight: 400; font-style: normal; src: url('{{ $font('inter-400.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Inter'; font-weight: 500; font-style: normal; src: url('{{ $font('inter-500.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Inter'; font-weight: 600; font-style: normal; src: url('{{ $font('inter-600.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Inter'; font-weight: 700; font-style: normal; src: url('{{ $font('inter-700.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Inter'; font-weight: 800; font-style: normal; src: url('{{ $font('inter-800.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Inter'; font-weight: 900; font-style: normal; src: url('{{ $font('inter-900.ttf') }}') format('truetype'); }
    @font-face { font-family: 'BrushScript'; font-weight: 400; font-style: normal; src: url('{{ $font('brush-script.ttf') }}') format('truetype'); }

    * { box-sizing: border-box; }
    body { font-family: 'Lupio', 'Inter', 'DejaVu Sans', sans-serif; color: #0F172A; font-size: 11px; line-height: 1.45; margin: 0; }
    .cur { font-family: 'DejaVu Sans', sans-serif; font-weight: normal; }
    .script { font-family: 'BrushScript', 'DejaVu Sans', cursive; }
    .mono { font-family: 'Inter', 'DejaVu Sans', sans-serif; letter-spacing: 0.5px; }
    .num, .fig { font-family: 'Inter', 'DejaVu Sans', sans-serif; }
    .muted { color: #64748B; }
    .num { text-align: right; white-space: nowrap; }
    table { border-collapse: collapse; width: 100%; }
    .label { font-size: 8.5px; letter-spacing: 1.2px; text-transform: uppercase; color: #64748B; font-weight: 700; }
    .pill { display: inline-block; padding: 3px 9px; border-radius: 10px; font-size: 8.5px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
    .pill-PAID { background: #ECFDF5; color: #047857; }
    .pill-PARTIAL { background: #FFFBEB; color: #B45309; }
    .pill-UNPAID, .pill-OVERDUE { background: #FFF1F2; color: #BE123C; }
    .pill-VOID, .pill-DRAFT { background: #F1F5F9; color: #475569; }
    .void-stamp { position: fixed; top: 38%; left: 18%; width: 64%; text-align: center; font-size: 96px; font-weight: 900; color: #E11D48; opacity: 0.18; transform: rotate(-24deg); letter-spacing: 12px; }
    .watermark { position: fixed; top: 46%; left: 0; width: 100%; text-align: center; font-size: 44px; font-weight: 800; color: #377581; opacity: 0.07; transform: rotate(-24deg); letter-spacing: 3px; }
    .wm-strip { position: fixed; bottom: 0; left: 0; right: 0; height: 22px; background: #EAF2F4; color: #265363; font-size: 9px; text-align: center; line-height: 22px; letter-spacing: 1px; }
</style>

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
    /* Status ledger tag: white, thin tone border, diamond mark (matches the web app). */
    .pill { display: inline-block; padding: 2px 8px 2px 7px; border: 1px solid #94A3AE; border-radius: 2px; background: #FFFFFF; font-size: 8px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: #5D6F77; }
    .pill .mark { font-family: 'DejaVu Sans', sans-serif; font-size: 7px; letter-spacing: 0; margin-right: 4px; }
    .pill-PAID { color: #1D6B52; border-color: #8DB9A9; }
    .pill-PARTIAL { color: #94600F; border-color: #CDB088; }
    .pill-UNPAID, .pill-OVERDUE { color: #A5341F; border-color: #D29A8F; }
    .pill-VOID, .pill-DRAFT { color: #5D6F77; border-color: #B7C1C6; }
    .void-stamp { position: fixed; top: 38%; left: 18%; width: 64%; text-align: center; font-size: 96px; font-weight: 900; color: #A5341F; opacity: 0.16; transform: rotate(-24deg); letter-spacing: 12px; }
    .watermark { position: fixed; top: 46%; left: 0; width: 100%; text-align: center; font-size: 44px; font-weight: 800; color: #377581; opacity: 0.07; transform: rotate(-24deg); letter-spacing: 3px; }
    .wm-strip { position: fixed; bottom: 0; left: 0; right: 0; height: 22px; background: #EAF2F4; color: #265363; font-size: 9px; text-align: center; line-height: 22px; letter-spacing: 1px; }
</style>

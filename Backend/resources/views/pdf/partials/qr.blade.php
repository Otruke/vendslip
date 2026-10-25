<table style="width:auto;">
    <tr>
        <td style="width:84px; vertical-align:top;"><img src="{{ $d['qr'] }}" style="width:78px; height:78px;" alt="Verify"></td>
        <td style="vertical-align:top; padding-left:8px;">
            <div class="label">Scan to verify</div>
            <div style="font-weight:800; font-size:11px; margin-top:3px;" class="mono">{{ $d['verification_code'] }}</div>
            <div class="muted" style="font-size:8px; margin-top:2px;">HMAC-SHA256 &middot; rev {{ $d['document']->revision_number }}</div>
            <div class="muted" style="font-size:8px;">{{ $d['hash_short'] }}</div>
        </td>
    </tr>
</table>

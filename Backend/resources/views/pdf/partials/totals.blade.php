<table class="totals">
    <tr><td class="muted">Subtotal</td><td class="num">{!! $d['subtotal'] !!}</td></tr>
    @if ($d['discount'])<tr><td class="muted">Discount</td><td class="num">-{!! $d['discount'] !!}</td></tr>@endif
    @if ($d['tax'])<tr><td class="muted">{{ $d['tax_label'] }}</td><td class="num">{!! $d['tax'] !!}</td></tr>@endif
    @if ($d['shipping'])<tr><td class="muted">Shipping</td><td class="num">{!! $d['shipping'] !!}</td></tr>@endif
    <tr class="grand"><td>Total</td><td class="num">{!! $d['total'] !!}</td></tr>
    @if ($d['paid'])<tr><td class="muted">Amount paid</td><td class="num" style="color:#1D6B52;">-{!! $d['paid'] !!}</td></tr>@endif
    @if ($d['balance'])<tr class="balance"><td>Balance due</td><td class="num">{!! $d['balance'] !!}</td></tr>@endif
</table>

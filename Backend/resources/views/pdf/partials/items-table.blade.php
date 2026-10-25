<table class="items">
    <thead>
        <tr>
            <th style="text-align:left; width:52%;">Description</th>
            <th class="num" style="width:10%;">Qty</th>
            <th class="num" style="width:19%;">Unit price</th>
            <th class="num" style="width:19%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($d['items'] as $item)
            <tr>
                <td>{{ $item['description'] }}</td>
                <td class="num">{{ number_format($item['quantity']) }}</td>
                <td class="num">{!! $item['unit'] !!}</td>
                <td class="num" style="font-weight:700;">{!! $item['total'] !!}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<title>{{ $invoice->number }}</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #111; }
    h1 { font-size: 18px; margin: 0; }
    .muted { color: #666; }
    table { width: 100%; border-collapse: collapse; }
    .lines th { background: #f1f5f9; text-align: left; padding: 6px; border-bottom: 1px solid #cbd5e1; }
    .lines td { padding: 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .right, .lines th.right { text-align: right; }
    .totals td { padding: 3px 6px; }
    .grand td { font-size: 12px; font-weight: bold; border-top: 2px solid #111; }
    .cancelled { color: #b91c1c; font-size: 22px; font-weight: bold; border: 3px solid #b91c1c; padding: 4px 10px; display: inline-block; }
</style>
</head>
<body>
    <table>
        <tr>
            <td style="width:60%">
                <h1>{{ $business }}</h1>
                <div class="muted">{{ $invoice->type->label() }} faturası</div>
            </td>
            <td class="right">
                <div style="font-size:14px; font-weight:bold">{{ $invoice->number }}</div>
                <div>Tarih: {{ $invoice->issue_date->format('d.m.Y') }}</div>
                <div>Vade: {{ $invoice->due_date->format('d.m.Y') }}</div>
            </td>
        </tr>
    </table>

    <div style="margin:16px 0; padding:8px; background:#f8fafc">
        <strong>{{ $invoice->contact->name }}</strong><br>
        @if ($invoice->contact->tax_number)
            {{ $invoice->contact->tax_office }} V.D. · {{ $invoice->contact->tax_number }}<br>
        @endif
        {{ $invoice->contact->address }}
    </div>

    @if ($invoice->isCancelled())
        <p class="cancelled">İPTAL EDİLDİ</p>
        <p class="muted">Gerekçe: {{ $invoice->cancel_reason }}</p>
    @endif

    <table class="lines">
        <thead>
            <tr><th>Açıklama</th><th class="right">Miktar</th><th class="right">Birim fiyat</th><th class="right">İsk.</th><th class="right">KDV</th><th class="right">Tutar</th></tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $line->quantity, 3, ',', '.'), '0'), ',') }}</td>
                    <td class="right">{{ $money($line->unit_price) }}</td>
                    <td class="right">{{ $line->discount_rate ? '%'.$line->discount_rate : '' }}</td>
                    <td class="right">%{{ $line->vat_rate }}</td>
                    <td class="right">{{ $money($line->net) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="width:45%; margin-left:55%; margin-top:10px">
        <tr><td>Ara toplam</td><td class="right">{{ $money($invoice->subtotal) }}</td></tr>
        @if ($invoice->discount_total)
            <tr><td>İskonto</td><td class="right">-{{ $money($invoice->discount_total) }}</td></tr>
        @endif
        @foreach ($vatBreakdown as $rate => $vat)
            <tr><td>KDV (%{{ $rate }})</td><td class="right">{{ $money($vat) }}</td></tr>
        @endforeach
        <tr class="grand"><td>Genel toplam</td><td class="right">{{ $money($invoice->total) }}</td></tr>
    </table>

    @if ($invoice->notes)
        <p style="margin-top:20px" class="muted">{{ $invoice->notes }}</p>
    @endif
</body>
</html>

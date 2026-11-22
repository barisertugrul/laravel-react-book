<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<title>{{ $ticket->code }}</title>
<style>
    /* dompdf'te Türkçe karakterler için DejaVu Sans yazı tipi kullanılır */
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
    h1 { font-size: 16px; margin: 0; }
    .muted { color: #666; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    td, th { border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: top; }
    th { background: #f3f3f3; width: 32%; }
    .header td { border: none; padding: 0; }
    .code { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
    .terms { margin-top: 12px; font-size: 8px; color: #555; }
    .sign td { border: none; padding-top: 30px; text-align: center; }
</style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ $business }}</h1>
                <div class="muted">Teknik Servis Teslim Fişi</div>
                <div class="code" style="margin-top:8px">{{ $ticket->code }}</div>
                <div class="muted">{{ $ticket->created_at->format('d.m.Y H:i') }}</div>
            </td>
            <td style="text-align:right; width:90px">
                <img src="{{ $qr }}" width="80" height="80" alt="">
                <div class="muted" style="font-size:7px">Durumu takip edin</div>
            </td>
        </tr>
    </table>

    <table>
        <tr><th>Müşteri</th><td>{{ $ticket->customer->name }}</td></tr>
        <tr><th>Telefon</th><td>{{ $ticket->customer->phone }}</td></tr>
        <tr><th>Cihaz</th><td>{{ $ticket->device_type }} · {{ $ticket->brand }} {{ $ticket->model }}</td></tr>
        <tr><th>Seri no</th><td>{{ $ticket->serial_number ?: '—' }}</td></tr>
        <tr><th>Arıza</th><td>{{ $ticket->problem }}</td></tr>
        <tr><th>Aksesuarlar</th><td>{{ $ticket->accessories ?: '—' }}</td></tr>
        <tr><th>Tahmini ücret</th><td>{{ $ticket->estimated_cost ? number_format($ticket->estimated_cost, 2, ',', '.').' ₺' : 'Arıza tespitinden sonra bildirilecek' }}</td></tr>
    </table>

    <p class="terms">
        Durum sorgulama: {{ route('tracking.lookup') }} adresinde servis numaranız ve telefonunuzun son 4 hanesi ile.
        Teslime hazır olduğu bildirilen cihazlar 90 gün içinde teslim alınmalıdır. Veri yedeği müşterinin sorumluluğundadır.
    </p>

    <table class="sign">
        <tr><td>Teslim eden (müşteri)</td><td>Teslim alan</td></tr>
    </table>
</body>
</html>

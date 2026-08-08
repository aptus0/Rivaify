<!DOCTYPE html>
<html lang="tr">
<body style="margin:0;background:#f7f7f8;color:#111111;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;"><tr><td align="center"><table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e5e7eb;"><tr><td style="padding:28px 32px;"><strong>{{ $order->store->name }}</strong><h1 style="margin:22px 0 10px;font-size:22px;">Ödemeniz onaylandı</h1><p style="margin:0;color:#6b7280;line-height:1.5;">{{ $order->order_number }} numaralı siparişiniz için {{ $order->grand_total }} {{ $order->currency }} tutarındaki ödeme başarıyla alındı.</p></td></tr></table></td></tr></table>
</body>
</html>
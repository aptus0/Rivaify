<!DOCTYPE html>
<html lang="tr">
<body style="margin:0;background:#f7f7f8;color:#111111;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;">
        <tr><td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e5e7eb;">
                <tr><td style="padding:28px 32px;border-bottom:1px solid #e5e7eb;"><strong style="font-size:18px;">{{ $order->store->name }}</strong></td></tr>
                <tr><td style="padding:32px;">
                    <h1 style="margin:0 0 12px;font-size:22px;">Yeni sipariş</h1>
                    <p style="margin:0 0 20px;color:#6b7280;line-height:1.5;">{{ $order->order_number }} · {{ $order->customer_email }}</p>
                    <p style="margin:0;font-size:18px;"><strong>{{ $order->grand_total }} {{ $order->currency }}</strong></p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
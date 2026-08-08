<!DOCTYPE html>
<html lang="tr">
<body style="margin:0;background:#f7f7f8;color:#111111;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;">
        <tr><td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e5e7eb;">
                <tr><td style="padding:28px 32px;border-bottom:1px solid #e5e7eb;"><strong style="font-size:18px;">{{ $order->store->name }}</strong></td></tr>
                <tr><td style="padding:32px;">
                    <h1 style="margin:0 0 12px;font-size:22px;">Siparişiniz alındı</h1>
                    <p style="margin:0 0 24px;color:#6b7280;line-height:1.5;">{{ $order->order_number }} numaralı siparişiniz için teşekkür ederiz.</p>
                    @foreach ($order->items as $item)
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-top:1px solid #e5e7eb;padding:12px 0;"><tr><td style="padding:12px 0;"><strong>{{ $item->product_title }}</strong><br><span style="color:#6b7280;font-size:13px;">{{ $item->variant_title }} · {{ $item->quantity }} adet</span></td><td align="right" style="padding:12px 0;"><strong>{{ $item->line_total }} {{ $order->currency }}</strong></td></tr></table>
                    @endforeach
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:16px;border-top:1px solid #e5e7eb;"><tr><td style="padding-top:16px;"><strong>Toplam</strong></td><td align="right" style="padding-top:16px;"><strong>{{ $order->grand_total }} {{ $order->currency }}</strong></td></tr></table>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
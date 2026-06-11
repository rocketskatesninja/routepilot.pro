<!DOCTYPE html>
<html lang="en">
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px;">
        <h2 style="color: #0ea5e9; margin: 0 0 16px;">Payment received</h2>
        <p style="margin: 0 0 12px;">Hi {{ $customerName }},</p>
        <p style="margin: 0 0 12px;">Thank you — we've received your payment of
            <strong>${{ number_format($amount, 2) }}</strong> on {{ $paidOn }}.</p>
        <p style="margin: 0 0 12px;">Your balance with {{ $companyName }} is now settled.</p>
        <p style="color: #6b7280; font-size: 13px; margin: 28px 0 0;">— {{ $companyName }}</p>
    </div>
</body>
</html>

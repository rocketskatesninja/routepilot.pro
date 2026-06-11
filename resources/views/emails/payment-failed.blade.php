<!DOCTYPE html>
<html lang="en">
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px;">
        <h2 style="color: #d97706; margin: 0 0 16px;">We couldn't process your payment</h2>
        <p style="margin: 0 0 12px;">Hi {{ $customerName }},</p>
        <p style="margin: 0 0 12px;">Your saved card was declined when we tried to charge
            <strong>${{ number_format($amount, 2) }}</strong> for your balance with {{ $companyName }}.</p>
        <p style="margin: 0 0 16px;">Please pay below — it only takes a moment.</p>

        <a href="{{ $payUrl }}" style="display: inline-block; background: #0ea5e9; color: #ffffff; text-decoration: none; padding: 12px 22px; border-radius: 8px; font-weight: bold;">
            Pay ${{ number_format($amount, 2) }} now
        </a>

        <p style="color: #6b7280; font-size: 13px; margin: 24px 0 0;">If you've already paid, you can ignore this. — {{ $companyName }}</p>
    </div>
</body>
</html>

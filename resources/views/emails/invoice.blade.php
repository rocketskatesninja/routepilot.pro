<!DOCTYPE html>
<html lang="en">
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px;">
        <h2 style="color: #0ea5e9; margin: 0 0 16px;">Invoice {{ $invoice->number }}</h2>
        <p style="margin: 0 0 12px;">Hi {{ $invoice->customer->displayName() }},</p>
        <p style="margin: 0 0 12px;">Here is your invoice from {{ $company }}.</p>

        <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
            @foreach ($invoice->lineItems as $line)
                <tr>
                    <td style="padding: 6px 0; color: #6b7280;">{{ $line->description }}</td>
                    <td style="padding: 6px 0; text-align: right;">${{ number_format((float) $line->amount, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td style="padding: 10px 0 0; font-weight: bold; border-top: 1px solid #e5e7eb;">Total due</td>
                <td style="padding: 10px 0 0; text-align: right; font-weight: bold; border-top: 1px solid #e5e7eb;">${{ number_format((float) $invoice->total, 2) }}</td>
            </tr>
        </table>

        <a href="{{ $payUrl }}" style="display: inline-block; background: #0ea5e9; color: #ffffff; text-decoration: none; padding: 12px 22px; border-radius: 8px; font-weight: bold;">
            Pay ${{ number_format((float) $invoice->total, 2) }} online
        </a>

        <p style="color: #6b7280; font-size: 13px; margin: 24px 0 0;">A PDF copy is attached for your records. — {{ $company }}</p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px;">
        <h2 style="color: #0ea5e9; margin: 0 0 16px;">Pool service tomorrow</h2>
        <p style="margin: 0 0 12px;">Hi {{ $customerName }},</p>
        <p style="margin: 0 0 12px;">This is a friendly reminder that <strong>{{ $pool }}</strong> is scheduled for service on <strong>{{ $date }}</strong>@if ($arrivalWindow), with an estimated arrival window of <strong>{{ $arrivalWindow }}</strong>@endif.</p>
        @if ($agentName)
            <p style="margin: 0 0 12px;">Your technician for this visit will be <strong>{{ $agentName }}</strong>.</p>
        @endif
        <p style="margin: 0 0 12px;">Please make sure any gates are unlocked and pets are secured so we can complete your visit.</p>
        <p style="margin: 16px 0 0; color: #6b7280;">Thanks for choosing {{ $company }}.</p>
    </div>
</body>
</html>

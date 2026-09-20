<!DOCTYPE html>
<html lang="en">
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px;">
        <h2 style="color: #0ea5e9; margin: 0 0 16px;">Confirm your new email</h2>
        <p style="margin: 0 0 12px;">Hi {{ $name }},</p>
        <p style="margin: 0 0 12px;">We received a request to change your account email to <strong>{{ $newEmail }}</strong>. To keep your account secure, this is also the email you'll sign in with.</p>
        <p style="margin: 0 0 20px;">Click below to confirm the change. Until you do, your current email stays in place.</p>
        <p style="margin: 0 0 20px;">
            <a href="{{ $confirmUrl }}" style="display: inline-block; background: #0ea5e9; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: bold;">Confirm new email</a>
        </p>
        <p style="margin: 0 0 12px; color: #6b7280; font-size: 13px;">This link expires in 60 minutes. If you didn't request this, you can safely ignore this email — nothing will change.</p>
    </div>
</body>
</html>

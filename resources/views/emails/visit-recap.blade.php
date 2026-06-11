<!DOCTYPE html>
<html lang="en">
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; background: #f9fafb; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px;">
        <h2 style="color: #0ea5e9; margin: 0 0 16px;">Your pool was serviced</h2>
        <p style="margin: 0 0 12px;">Hi {{ $customerName }},</p>
        <p style="margin: 0 0 12px;">{{ $company }} serviced <strong>{{ $pool }}</strong> on {{ $date }}.</p>

        @if ($reading)
            <h3 style="margin: 18px 0 6px; font-size: 14px;">Water chemistry</h3>
            <table style="width: 100%; border-collapse: collapse;">
                @foreach ([['Free chlorine', $reading->free_chlorine, 'ppm'], ['pH', $reading->ph, ''], ['Alkalinity', $reading->alkalinity, 'ppm']] as $row)
                    @if ($row[1] !== null)
                        <tr>
                            <td style="padding: 4px 0; color: #6b7280;">{{ $row[0] }}</td>
                            <td style="padding: 4px 0; text-align: right;">{{ $row[1] }} {{ $row[2] }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        @endif

        @if ($treatments->count())
            <h3 style="margin: 18px 0 6px; font-size: 14px;">Treatments added</h3>
            <ul style="margin: 0; padding-left: 18px; color: #6b7280;">
                @foreach ($treatments as $t)
                    <li>{{ $t->chemical_name }} — {{ $t->amount }} {{ $t->unit }}</li>
                @endforeach
            </ul>
        @endif

        @if ($balance > 0)
            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                <p style="margin: 0 0 10px;">Your current balance is <strong>${{ number_format($balance, 2) }}</strong>.</p>
                @if ($payUrl)
                    <a href="{{ $payUrl }}" style="display: inline-block; background: #0ea5e9; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: bold;">Pay online</a>
                @endif
            </div>
        @endif

        <p style="color: #6b7280; font-size: 13px; margin: 24px 0 0;">— {{ $company }}</p>
    </div>
</body>
</html>

{{-- A note shown as a message from someone: "Ashley wrote", then what they said. --}}
@props(['author'])
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 18px 0 0; max-width: 480px;">
    <tr>
        <td style="background: #f1f5f9; border-radius: 6px 16px 16px 16px; padding: 12px 16px;">
            <p style="margin: 0; font-size: 10px; font-weight: 500; color: #64748b;">{{ $author }}</p>
            <p style="margin: 2px 0 0; font-size: 13px; line-height: 1.5; color: #0f172a;">{!! nl2br(e($slot)) !!}</p>
        </td>
    </tr>
</table>

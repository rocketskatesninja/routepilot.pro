{{-- A button that renders as a button in every inbox (a table cell, not just a styled link). --}}
@props(['brand', 'href', 'secondary' => false])
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 22px 0 0;">
    <tr>
        <td style="border-radius: 10px; {{ $secondary ? 'border: 1px solid #cbd5e1; background: #ffffff;' : 'background: '.$brand['color'].';' }}">
            <a href="{{ $href }}" style="display: inline-block; padding: 12px 22px; font-size: 15px; font-weight: 600; line-height: 1; text-decoration: none; border-radius: 10px; color: {{ $secondary ? '#0f172a' : $brand['on_color'] }};">{{ $slot }}</a>
        </td>
    </tr>
</table>

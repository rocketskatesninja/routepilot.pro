@php $total = '$'.number_format((float) $invoice->total, 2); @endphp
<x-email.layout
    :brand="$brand"
    :greeting="'Hi '.$customerName.','"
    :title="'Here’s your bill for '.$total.'.'"
    :sub="'Invoice '.$invoice->number.($dueOn ? ', due '.$dueOn : '').'. A PDF copy is attached.'"
    :preheader="'Invoice '.$invoice->number.' from '.$brand['name'].': '.$total.'.'"
>
    <x-email.section title="What it’s for">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e7eb; border-radius: 16px; border-collapse: separate;">
            @foreach ($invoice->charges as $i => $line)
                <tr>
                    <td style="padding: 12px 18px; font-size: 13px; {{ $i > 0 ? 'border-top: 1px solid #e5e7eb;' : '' }}">{{ $line->description }}</td>
                    <td align="right" style="padding: 12px 18px; font-size: 13px; white-space: nowrap; {{ $i > 0 ? 'border-top: 1px solid #e5e7eb;' : '' }}">${{ number_format((float) $line->amount, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td style="padding: 12px 18px; font-size: 13px; font-weight: 600; border-top: 1px solid #e5e7eb;">Total</td>
                <td align="right" style="padding: 12px 18px; font-size: 13px; font-weight: 600; white-space: nowrap; border-top: 1px solid #e5e7eb;">{{ $total }}</td>
            </tr>
        </table>
    </x-email.section>
    <x-email.button :brand="$brand" :href="$payUrl">Pay {{ $total }} online</x-email.button>
</x-email.layout>

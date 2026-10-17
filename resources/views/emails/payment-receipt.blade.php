<x-email.layout
    :brand="$brand"
    :greeting="'Hi '.$customerName.','"
    :title="'Thanks, we got your payment.'"
    :sub="'$'.number_format($amount, 2).' on '.$paidOn.'.'"
    :preheader="'Payment of $'.number_format($amount, 2).' received. Thank you!'"
>
    <x-email.section title="What happens now">
        <p style="margin: 0;">We’ve added it to your account. Keep this email as your receipt.</p>
    </x-email.section>
</x-email.layout>

<x-email.layout
    :brand="$brand"
    :greeting="'Hi '.$customerName.','"
    :title="'We couldn’t charge your card.'"
    :sub="'Your saved card was declined for $'.number_format($amount, 2).'.'"
    :preheader="'Your card was declined. You can pay online in a moment.'"
>
    <x-email.section title="What to do">
        <p style="margin: 0;">Pay below with any card. It only takes a moment. If you’ve already paid, you can ignore this email.</p>
    </x-email.section>
    <x-email.button :brand="$brand" :href="$payUrl">Pay ${{ number_format($amount, 2) }} now</x-email.button>
</x-email.layout>

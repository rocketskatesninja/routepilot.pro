<x-email.layout
    :brand="$brand"
    :title="'Your mail settings work.'"
    :sub="'This test came through your own mail server, from '.$sender['address'].'.'"
    :preheader="'Your RoutePilot emails now go out from '.$sender['address'].'.'"
>
    <x-email.section title="What happens now">
        <p style="margin: 0;">Your emails to customers (visit reports, reminders, invoices and receipts) now go out from your own mail server. If it ever can’t send one, RoutePilot sends it for you and lets you know.</p>
    </x-email.section>
</x-email.layout>

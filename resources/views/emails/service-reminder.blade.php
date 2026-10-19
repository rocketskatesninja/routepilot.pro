<x-email.layout
    :brand="$brand"
    :greeting="'Hi '.$customerName.','"
    :title="($agentName ?? 'Your technician').' is coming tomorrow.'"
    :sub="$date.' at '.$pool.'.'"
    :preheader="'Pool service tomorrow, '.$date.'.'"
>
    <x-email.section title="Before we get there">
        <p style="margin: 0;">Please unlock the gate and keep pets inside so we can get to the pool. You don’t need to be home.</p>
    </x-email.section>
    <x-slot:footer>
        <p style="margin: 0;">You get a reminder the day before each visit.@if ($stopUrl) <a href="{{ $stopUrl }}" style="color: #475569;">Stop reminders</a>@endif</p>
    </x-slot:footer>
</x-email.layout>

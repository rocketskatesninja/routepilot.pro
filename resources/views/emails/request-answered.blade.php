<x-email.layout :brand="$brand" :greeting="'Hi '.$customerName.','" :title="$headline" :sub="$summary" :preheader="$summary">
    @if ($reply)
        <x-email.note :author="$declined ? $company.' explained' : $company.' wrote'">{{ $reply }}</x-email.note>
    @endif
    @if ($portalUrl)
        <x-email.button :brand="$brand" :href="$portalUrl">See your requests</x-email.button>
    @endif
</x-email.layout>

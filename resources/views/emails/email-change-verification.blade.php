<x-email.layout
    :brand="$brand"
    :greeting="'Hi '.$name.','"
    :title="'Confirm your new email.'"
    :sub="'You asked to sign in with '.$newEmail.' from now on.'"
    :preheader="'Confirm your new email to finish the change.'"
>
    <x-email.section title="One click to finish">
        <p style="margin: 0;">Until you confirm, your current email stays in place. The link works for 60 minutes.</p>
    </x-email.section>
    <x-email.button :brand="$brand" :href="$confirmUrl">Confirm new email</x-email.button>
    <x-slot:footer>
        <p style="margin: 0;">Didn’t ask for this? Ignore this email and nothing will change.</p>
    </x-slot:footer>
</x-email.layout>

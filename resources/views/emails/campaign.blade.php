<x-email.layout :brand="$brand" :greeting="'Hi '.$recipientName.','" :title="$subjectLine" :preheader="\Illuminate\Support\Str::limit($body, 120)">
    <div style="margin-top: 18px; font-size: 13px; line-height: 1.6;">{!! nl2br(e($body)) !!}</div>
    <x-slot:footer>
        @if ($unsubscribeUrl)
            <p style="margin: 0;">No longer want news from us? <a href="{{ $unsubscribeUrl }}" style="color: #475569;">Unsubscribe</a></p>
        @endif
    </x-slot:footer>
</x-email.layout>

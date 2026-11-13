{{-- The writer's own message (cleaned, styled, first name filled in): no automatic greeting, the message opens how they wrote it. --}}
<x-email.layout :brand="$brand" :title="$subjectLine" :preheader="\Illuminate\Support\Str::limit($text, 120)">
    <div style="margin-top: 18px; font-size: 13px; line-height: 1.6;">{!! $html !!}</div>
    <x-slot:footer>
        @if ($unsubscribeUrl)
            <p style="margin: 0;">No longer want news from us? <a href="{{ $unsubscribeUrl }}" style="color: #475569; text-decoration: underline;">Unsubscribe</a></p>
        @endif
    </x-slot:footer>
</x-email.layout>

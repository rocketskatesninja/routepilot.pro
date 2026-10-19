{{-- A titled part of an email body. --}}
@props(['title'])
<h2 style="margin: 26px 0 10px; font-family: 'Bricolage Grotesque', 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 17px; line-height: 1.25; font-weight: 600; color: #0f172a;">{{ $title }}</h2>
{{ $slot }}

@php
    $done = $tasks->where('is_completed', true);
    $amount = fn ($t) => rtrim(rtrim(number_format((float) $t->amount, 2, '.', ''), '0'), '.').' '.$t->unit;
@endphp
<x-email.layout
    :brand="$brand"
    :greeting="'Hi '.$firstName.','"
    :title="'Your pool was serviced on '.$date.'.'"
    :sub="($service ?? 'Service visit').' at '.$pool.($agent ? ' with '.$agent : '').'.'"
    :preheader="trim(($service ?? 'Service visit').' at '.$pool.'. '.$waterSummary)"
>
    {{-- what was done --}}
    @if ($tasks->count() || count($checks) || $treatments->count())
        <x-email.section title="What was done">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                @foreach ($tasks->chunk(2) as $pair)
                    <tr>
                        @foreach ($pair as $t)
                            <td class="rp-col" width="50%" style="padding: 3px 0; font-size: 15px; color: {{ $t->is_completed ? '#0f172a' : '#64748b' }};">
                                <span style="color: {{ $t->is_completed ? $brand['color'] : '#94a3b8' }}; font-weight: 600;">{{ $t->is_completed ? '✓' : '–' }}</span>&nbsp; {{ $t->task_name }}{{ $t->is_completed ? '' : ' (skipped)' }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                @if (count($checks))
                    <tr><td colspan="2" style="padding: 3px 0; font-size: 15px;"><span style="color: {{ $brand['color'] }}; font-weight: 600;">✓</span>&nbsp; Water tested</td></tr>
                @endif
                @if ($treatments->count())
                    <tr><td colspan="2" style="padding: 3px 0; font-size: 15px;"><span style="color: {{ $brand['color'] }}; font-weight: 600;">✓</span>&nbsp; {{ $treatments->count() }} chemical{{ $treatments->count() === 1 ? '' : 's' }} added</td></tr>
                @endif
            </table>
        </x-email.section>
    @endif

    {{-- the tech's note --}}
    @if ($notes)
        <x-email.note :author="($agent ?? 'Your technician').' wrote'">{{ $notes }}</x-email.note>
    @endif

    {{-- the water test, against this pool's own targets --}}
    @if (count($checks))
        <x-email.section title="Your water">
            <p style="margin: 0 0 12px; font-size: 15px; color: #475569;">{{ $waterSummary }}</p>
            <x-email.water :brand="$brand" :checks="$checks" />
        </x-email.section>
    @endif

    {{-- chemicals added --}}
    @if ($treatments->count())
        <x-email.section title="Chemicals added">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e7eb; border-radius: 16px; border-collapse: separate;">
                @foreach ($treatments as $i => $t)
                    <tr>
                        <td style="padding: 12px 18px; font-size: 15px; {{ $i > 0 ? 'border-top: 1px solid #e5e7eb;' : '' }}">{{ $t->chemical_name }}</td>
                        <td align="right" style="padding: 12px 18px; font-size: 15px; color: #475569; white-space: nowrap; {{ $i > 0 ? 'border-top: 1px solid #e5e7eb;' : '' }}">{{ $amount($t) }}</td>
                    </tr>
                @endforeach
            </table>
        </x-email.section>
    @endif

    {{-- photos --}}
    @if (! empty($photos))
        <x-email.section title="Photos">
            @foreach ($photos as $photo)
                <a href="{{ $photo }}" style="text-decoration: none;"><img src="{{ $photo }}" alt="Photo from the visit" width="164" height="164" style="width: 164px; height: 164px; object-fit: cover; border-radius: 12px; border: 0; margin: 0 6px 6px 0; vertical-align: top;"></a>
            @endforeach
        </x-email.section>
    @endif

    {{-- what they owe --}}
    @if ($balance > 0)
        <x-email.section title="Your balance">
            <p style="margin: 0; font-size: 15px;">You owe <strong>${{ number_format($balance, 2) }}</strong>.</p>
            @if ($payUrl)
                <x-email.button :brand="$brand" :href="$payUrl">Pay ${{ number_format($balance, 2) }}</x-email.button>
            @endif
        </x-email.section>
    @endif

    @if ($reportUrl)
        <x-email.button :brand="$brand" :href="$reportUrl" :secondary="$balance > 0 && $payUrl">See the full report</x-email.button>
    @endif

    <x-slot:footer>
        <p style="margin: 0;">You get this email after each visit to your pool. <a href="{{ $stopUrl }}" style="color: #475569;">Stop visit reports</a></p>
    </x-slot:footer>
</x-email.layout>

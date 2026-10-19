{{--
    A water test as rows: the reading, a worded status (never colour alone),
    and a range bar with the healthy range shaded and a marker where this
    reading landed. The bar is a row of table cells so it shows in every inbox.
--}}
@props(['brand', 'checks'])
@php
    $track = '#e8edf2';
    // The bar's pieces, left to right: track, healthy band, track — with the marker carved in.
    $bar = function (array $c) use ($track, $brand): array {
        $dotW = 3.0;
        $d0 = max(0.0, min(100.0 - $dotW, $c['dot'] - $dotW / 2));
        $d1 = $d0 + $dotW;
        $bandL = $c['band_left'];
        $bandR = $c['band_left'] + $c['band_width'];
        $points = array_values(array_unique([0.0, $bandL, $bandR, $d0, $d1, 100.0]));
        sort($points);
        $marker = $c['status'] === 'good' ? '#059669' : '#f59e0b';
        $pieces = [];
        for ($i = 0; $i < count($points) - 1; $i++) {
            [$a, $b] = [$points[$i], $points[$i + 1]];
            if ($b - $a < 0.05) {
                continue;
            }
            $pieces[] = [
                'width' => round($b - $a, 2),
                'color' => $a >= $d0 - 0.01 && $b <= $d1 + 0.01 ? $marker : ($a >= $bandL - 0.01 && $b <= $bandR + 0.01 ? $brand['band'] : $track),
                'marker' => $a >= $d0 - 0.01 && $b <= $d1 + 0.01,
            ];
        }

        return $pieces;
    };
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e7eb; border-radius: 16px; border-collapse: separate;">
    @foreach ($checks as $i => $c)
        <tr>
            <td style="padding: 14px 18px; {{ $i > 0 ? 'border-top: 1px solid #e5e7eb;' : '' }}">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size: 13px; font-weight: 600; color: #0f172a;">{{ $c['name'] }}</td>
                        <td align="right" style="font-size: 13px; font-weight: 600; color: #0f172a; white-space: nowrap;">{{ $c['reads'] }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 11px; color: #64748b;">Healthy: {{ $c['range'] }}</td>
                        <td align="right" style="font-size: 11px; font-weight: 500; white-space: nowrap; color: {{ $c['status'] === 'good' ? '#047857' : '#b45309' }};">
                            {{ $c['status'] === 'good' ? '✓' : ($c['status'] === 'low' ? '↓' : '↑') }} {{ $c['label'] }}
                        </td>
                    </tr>
                </table>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 8px; border-collapse: collapse;" aria-label="{{ $c['name'] }} {{ $c['reads'] }}, healthy range {{ $c['range'] }}: {{ strtolower($c['label']) }}">
                    <tr>
                        @foreach ($bar($c) as $p)
                            <td width="{{ $p['width'] }}%" style="height: 10px; line-height: 10px; font-size: 0; background: {{ $p['color'] }};{{ $p['marker'] ? ' border-radius: 999px;' : '' }}{{ $loop->first ? ' border-radius: 999px 0 0 999px;' : '' }}{{ $loop->last ? ' border-radius: 0 999px 999px 0;' : '' }}">&nbsp;</td>
                        @endforeach
                    </tr>
                </table>
            </td>
        </tr>
    @endforeach
</table>

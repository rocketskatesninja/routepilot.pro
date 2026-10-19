{{--
    Every customer email, in the customer portal's style: the company's logo
    or name, a header panel in its colour with one big plain sentence, the
    pool-tile strip, then the body. Built from tables with inline styles so it
    holds together in Gmail, Outlook and Apple Mail alike.
--}}
@props(['brand', 'title', 'greeting' => null, 'sub' => null, 'preheader' => null])
@php
    $display = "'Bricolage Grotesque', 'Segoe UI', Helvetica, Arial, sans-serif";
    $body = "Inter, -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>{{ $title }}</title>
    <link href="https://fonts.bunny.net/css?family=bricolage-grotesque:600|inter:400,500,600" rel="stylesheet">
    <style>
        @media (max-width: 600px) {
            .rp-pad { padding-left: 20px !important; padding-right: 20px !important; }
            .rp-title { font-size: 22px !important; }
            .rp-col { display: block !important; width: 100% !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background: #f3f5f7; color: #0f172a; font-family: {{ $body }}; -webkit-text-size-adjust: 100%;">
    @if ($preheader)
        <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; color: transparent;">{{ $preheader }}</div>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f3f5f7;">
        <tr>
            <td align="center" style="padding: 24px 12px 32px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px;">
                    {{-- the company --}}
                    <tr>
                        <td style="padding: 0 6px 14px;">
                            @if ($brand['logo'])
                                <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" height="40" style="height: 40px; max-width: 200px; border: 0; display: block;">
                            @else
                                <span style="font-family: {{ $display }}; font-size: 16px; font-weight: 600; color: #0f172a;">{{ $brand['name'] }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 20px; overflow: hidden;">
                            {{-- the headline, on the company's colour --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="rp-pad" style="background: {{ $brand['tint'] }}; padding: 28px 32px 26px;">
                                        @if ($greeting)
                                            <p style="margin: 0 0 8px; font-size: 13px; color: #475569;">{{ $greeting }}</p>
                                        @endif
                                        <h1 class="rp-title" style="margin: 0; font-family: {{ $display }}; font-size: 26px; line-height: 1.15; font-weight: 600; letter-spacing: -0.01em; color: #0f172a;">{{ $title }}</h1>
                                        @if ($sub)
                                            <p style="margin: 10px 0 0; font-size: 13px; line-height: 1.5; color: #475569;">{{ $sub }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            {{-- the pool-tile strip: two rows of small tiles in the company's colour --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                                @foreach ([0, 1] as $row)
                                    <tr>
                                        @for ($i = 0; $i < 40; $i++)
                                            <td width="2.5%" style="height: 7px; line-height: 7px; font-size: 0; background: {{ $brand['waterline'][($i + $row) % 2] }}; border-right: 1px solid #ffffff; border-bottom: 1px solid #ffffff;">&nbsp;</td>
                                        @endfor
                                    </tr>
                                @endforeach
                            </table>
                            {{-- the body --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="rp-pad" style="padding: 8px 32px 32px; font-size: 13px; line-height: 1.55; color: #0f172a;">
                                        {{ $slot }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    {{-- footer --}}
                    <tr>
                        <td style="padding: 18px 8px 0; font-size: 11px; line-height: 1.5; color: #64748b; text-align: center;">
                            {{ $footer ?? '' }}
                            <p style="margin: 6px 0 0;">{{ $brand['name'] }}@if ($brand['address'])<br>{{ $brand['address'] }}@endif</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

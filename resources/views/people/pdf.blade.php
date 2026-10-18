@php
    $brand = $tenant?->brand_color ?: '#0ea5e9';
    $company = $tenant?->name ?? 'RoutePilot';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 10px; margin: 0; }
        .bar { height: 6px; background: {{ $brand }}; }
        .wrap { padding: 28px 32px; }
        .company { font-size: 16px; font-weight: bold; color: {{ $brand }}; }
        .title { font-size: 22px; font-weight: bold; margin-top: 6px; }
        .muted { color: #6b7280; }
        table.list { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.list thead { display: table-header-group; }
        table.list th { text-align: left; border-bottom: 2px solid #e5e7eb; padding: 6px 5px; font-size: 9px; color: #6b7280; }
        table.list td { padding: 6px 5px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        table.list tr { page-break-inside: avoid; }
        .right { text-align: right; }
        .owes { color: #b45309; font-weight: bold; }
        .empty { margin-top: 24px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="bar"></div>
    <div class="wrap">
        <div class="company">{{ $company }}</div>
        <div class="title">{{ $title }}</div>
        <div class="muted">{{ $summary }}</div>

        @if ($rows->isEmpty())
            <p class="empty">Nobody matches this list.</p>
        @else
            <table class="list">
                <thead>
                    <tr>
                        <th>Name</th>
                        @if ($showAgents)<th>Type</th>@endif
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Portal</th>
                        <th>Last visit</th>
                        <th class="right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>{{ $r['name'] }}</td>
                            @if ($showAgents)<td>{{ $r['type'] }}</td>@endif
                            <td>{{ $r['email'] }}</td>
                            <td>{{ $r['phone'] }}</td>
                            <td>{{ $r['address'] }}</td>
                            <td>{{ $r['portal'] }}</td>
                            <td>{{ $r['last_visit'] ? \Illuminate\Support\Carbon::parse($r['last_visit'])->format('M j, Y') : '' }}</td>
                            <td class="right {{ ($r['balance'] ?? 0) > 0 ? 'owes' : '' }}">{{ $r['balance'] === null ? '' : '$'.number_format((float) $r['balance'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>

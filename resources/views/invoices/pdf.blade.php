<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }
        .company-tagline {
            font-size: 14px;
            color: #666;
        }
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .invoice-info, .client-info {
            flex: 1;
        }
        .invoice-number {
            font-size: 18px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }
        .info-row {
            margin-bottom: 5px;
        }
        .label {
            font-weight: bold;
            color: #555;
        }
        .value {
            color: #333;
        }
        .service-details {
            margin-bottom: 30px;
        }
        .service-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .service-table th {
            background-color: #f8fafc;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
            font-weight: bold;
            color: #374151;
        }
        .service-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .amount {
            text-align: right;
            font-weight: bold;
        }
        .total-section {
            margin-top: 30px;
            border-top: 2px solid #e5e7eb;
            padding-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .total-row.final {
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
            border-top: 2px solid #2563eb;
            padding-top: 10px;
            margin-top: 10px;
        }
        .notes {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8fafc;
            border-left: 4px solid #2563eb;
        }
        .notes h3 {
            margin-top: 0;
            color: #2563eb;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-sent {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-overdue {
            background-color: #fef2f2;
            color: #dc2626;
        }
        .status-draft {
            background-color: #f3f4f6;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">RoutePilot Pro</div>
        <div class="company-tagline">Professional Pool Service Management</div>
    </div>

    <div class="invoice-details">
        <div class="invoice-info">
            <div class="invoice-number">INVOICE {{ $invoice->invoice_number }}</div>
            <div class="info-row">
                <span class="label">Service Date:</span>
                <span class="value">{{ $invoice->service_date->format('M d, Y') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Due Date:</span>
                <span class="value">{{ $invoice->due_date->format('M d, Y') }}</span>
            </div>
            <div class="info-row">
                <span class="label">Status:</span>
                <span class="status-badge status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
            </div>
            @if($invoice->paid_at)
            <div class="info-row">
                <span class="label">Paid Date:</span>
                <span class="value">{{ $invoice->paid_at->format('M d, Y') }}</span>
            </div>
            @endif
        </div>
        
        <div class="client-info">
            <div class="label" style="margin-bottom: 10px;">Bill To:</div>
            <div class="value">{{ $invoice->client->full_name }}</div>
            <div class="value">{{ $invoice->client->email }}</div>
            <div class="value">{{ $invoice->client->phone }}</div>
            <div class="value">{{ $invoice->client->address }}</div>
            @if($invoice->client->city && $invoice->client->state)
            <div class="value">{{ $invoice->client->city }}, {{ $invoice->client->state }} {{ $invoice->client->zip_code }}</div>
            @endif
        </div>
    </div>

    <div class="service-details">
        <h3>Service Details</h3>
        <table class="service-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Details</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Pool Service Visit</td>
                    <td>
                        <strong>Location:</strong> {{ $invoice->location->name }}<br>
                        <strong>Technician:</strong> {{ $invoice->technician->full_name }}<br>
                        <strong>Service Date:</strong> {{ $invoice->service_date->format('M d, Y') }}
                    </td>
                    <td class="amount">${{ number_format($invoice->rate_per_visit, 2) }}</td>
                </tr>
                
                @if($invoice->chemicals_included && $invoice->chemicals_cost > 0)
                <tr>
                    <td>Chemicals & Supplies</td>
                    <td>Pool chemicals and maintenance supplies included in service</td>
                    <td class="amount">${{ number_format($invoice->chemicals_cost, 2) }}</td>
                </tr>
                @endif
                
                @if($invoice->extras_cost > 0)
                <tr>
                    <td>Additional Services</td>
                    <td>Extra services and materials</td>
                    <td class="amount">${{ number_format($invoice->extras_cost, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="total-section">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>${{ number_format($invoice->rate_per_visit, 2) }}</span>
        </div>
        
        @if($invoice->chemicals_included && $invoice->chemicals_cost > 0)
        <div class="total-row">
            <span>Chemicals & Supplies:</span>
            <span>${{ number_format($invoice->chemicals_cost, 2) }}</span>
        </div>
        @endif
        
        @if($invoice->extras_cost > 0)
        <div class="total-row">
            <span>Additional Services:</span>
            <span>${{ number_format($invoice->extras_cost, 2) }}</span>
        </div>
        @endif
        
        <div class="total-row final">
            <span>Total Amount:</span>
            <span>${{ number_format($invoice->total_amount, 2) }}</span>
        </div>
        
        @if($invoice->status !== 'paid')
        <div class="total-row">
            <span>Balance Due:</span>
            <span>${{ number_format($invoice->balance, 2) }}</span>
        </div>
        @endif
    </div>

    @if($invoice->notes)
    <div class="notes">
        <h3>Notes</h3>
        <p>{{ $invoice->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Thank you for choosing RoutePilot Pro for your pool service needs.</p>
        <p>For questions about this invoice, please contact us at support@routepilot.pro</p>
        <p>Generated on {{ now()->format('M d, Y \a\t g:i A') }}</p>
    </div>
</body>
</html> 
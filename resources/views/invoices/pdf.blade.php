<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 14px;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 40px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }
        .header h1 {
            font-size: 28px;
            color: #1a1a1a;
            margin: 0;
        }
        .header .invoice-number {
            color: #666;
            font-size: 12px;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid { background: #d4edda; color: #155724; }
        .status-refunded { background: #f8d7da; color: #721c24; }
        .status-issued { background: #fff3cd; color: #856404; }
        .parties {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .party {
            width: 45%;
        }
        .party h3 {
            font-size: 11px;
            text-transform: uppercase;
            color: #999;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .party .name {
            font-weight: bold;
            font-size: 16px;
        }
        .party .email {
            color: #666;
            font-size: 13px;
        }
        .dates {
            margin-bottom: 30px;
            color: #666;
            font-size: 13px;
        }
        .dates span {
            margin-right: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 10px 12px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .text-right { text-align: right; }
        .total-row td {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 16px;
            padding-top: 15px;
        }
        .breakdown td {
            border-bottom: none;
            padding: 6px 12px;
            color: #666;
            font-size: 13px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            color: #999;
            font-size: 12px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <table width="100%" style="margin-bottom: 40px; border: none;">
        <tr>
            <td style="border:none; padding:0;">
                <h1 style="font-size: 28px; color: #1a1a1a; margin: 0;">INVOICE</h1>
                <p style="color: #666; font-size: 12px; margin: 5px 0 0;">{{ $invoice->invoice_number }}</p>
            </td>
            <td style="border:none; padding:0; text-align: right;">
                <span class="status status-{{ $invoice->status }}">{{ strtoupper($invoice->status) }}</span>
            </td>
        </tr>
    </table>

    <table width="100%" style="margin-bottom: 30px; border: none;">
        <tr>
            <td width="50%" style="border:none; padding:0; vertical-align: top;">
                <h3 style="font-size: 11px; text-transform: uppercase; color: #999; letter-spacing: 1px; margin-bottom: 8px;">Buyer</h3>
                <p class="name" style="font-weight: bold; font-size: 16px; margin: 0;">{{ $invoice->buyer->name }}</p>
                <p class="email" style="color: #666; font-size: 13px; margin: 4px 0 0;">{{ $invoice->buyer->email }}</p>
            </td>
            <td width="50%" style="border:none; padding:0; vertical-align: top;">
                <h3 style="font-size: 11px; text-transform: uppercase; color: #999; letter-spacing: 1px; margin-bottom: 8px;">Seller</h3>
                <p class="name" style="font-weight: bold; font-size: 16px; margin: 0;">{{ $invoice->seller->name }}</p>
                <p class="email" style="color: #666; font-size: 13px; margin: 4px 0 0;">{{ $invoice->seller->email }}</p>
            </td>
        </tr>
    </table>

    <div class="dates">
        <span><strong>Issued:</strong> {{ $invoice->issued_at?->format('F j, Y') }}</span>
        <span><strong>Paid:</strong> {{ $invoice->paid_at?->format('F j, Y') ?? 'Pending' }}</span>
        <span><strong>Currency:</strong> {{ $invoice->currency }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $invoice->auction->title }}</strong><br>
                    <span style="color: #666; font-size: 12px;">Auction #{{ $invoice->auction_id }} — Winning Bid</span>
                </td>
                <td class="text-right">${{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table style="width: 50%; margin-left: auto;">
        <tbody class="breakdown">
            <tr>
                <td>Subtotal</td>
                <td class="text-right">${{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Platform Fee ({{ number_format((float) ($invoice->commission_rate_percent ?? ((float) config('auction.platform_fee_percent', 0.05) * 100)), 2) }}%)</td>
                <td class="text-right">${{ number_format($invoice->platform_fee, 2) }}</td>
            </tr>
            <tr>
                <td>Seller Payout</td>
                <td class="text-right">${{ number_format($invoice->seller_amount, 2) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right">${{ number_format($invoice->total, 2) }} {{ $invoice->currency }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Payment method: Wallet Balance — Auto-captured on auction close</p>
        <p>Thank you for using our auction platform.</p>
    </div>
</body>
</html>

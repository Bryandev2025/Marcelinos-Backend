<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Statement {{ $booking->reference_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 11mm 11mm 12mm;
        }

        * { 
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1f2937;
            background: #ffffff;
            font-size: 9.8px;
            line-height: 1.32;
        }

        .page {
            position: relative;
            border: 1px solid #d1e7dd;
            border-radius: 8px;
            overflow: hidden;
            background: #ffffff;
            page-break-inside: avoid;
        }

        .watermark {
            position: absolute;
            top: 215px;
            left: 0;
            right: 0;
            text-align: center;
            z-index: 0;
            opacity: 0.07;
            transform: rotate(-15deg);
            pointer-events: none;
        }

        .watermark strong {
            display: block;
            font-size: 25px;
            line-height: 1;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #0f3d36;
        }

        .watermark span {
            display: block;
            margin-top: 6px;
            font-size: 10px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #7a5a17;
        }

        .header,
        .content {
            position: relative;
            z-index: 1;
        }

        .header {
            background: #0f3d36;
            color: #ffffff;
            padding: 14px 16px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .brand-cell {
            width: 62%;
        }

        .brand-wrap {
            display: table;
            width: 100%;
        }

        .brand-logo,
        .brand-copy {
            display: table-cell;
            vertical-align: middle;
        }

        .brand-logo {
            width: 50px;
            padding-right: 10px;
        }

        .brand-logo img {
            display: block;
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        .logo-fallback {
            width: 44px;
            height: 44px;
            line-height: 44px;
            text-align: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        .brand-kicker {
            margin: 0 0 3px;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            font-size: 8px;
            color: #ead8ad;
        }

        .brand-name {
            margin: 0;
            font-size: 12px;
            line-height: 1.12;
            font-weight: 700;
        }

        .brand-subtitle {
            margin: 1px 0 0;
            font-size: 9px;
            color: rgba(255, 255, 255, 0.84);
        }

        .meta-cell {
            text-align: right;
        }

        .statement-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            line-height: 1;
        }

        .statement-subtitle {
            margin: 3px 0 0;
            font-size: 9px;
            color: rgba(255, 255, 255, 0.86);
        }

        .content {
            padding: 12px 16px 14px;
            background: #ffffff;
        }

        .banner {
            border: 1px solid #f3d58a;
            background: #fff7e6;
            color: #8a5a00;
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 10px;
            font-size: 9px;
            line-height: 1.35;
        }

        .banner strong {
            display: block;
            font-size: 10px;
            margin-bottom: 2px;
        }

        .banner.cancellation-refund {
            border-color: #fdba74;
            background: #fff7ed;
            color: #7c2d12;
        }

        .banner.cancellation-refund .cr-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 9px;
        }

        .banner.cancellation-refund .cr-table td {
            padding: 3px 0;
            vertical-align: top;
        }

        .banner.cancellation-refund .cr-table td:first-child {
            color: #9a3412;
            width: 58%;
            padding-right: 8px;
        }

        .banner.cancellation-refund .cr-table td:last-child {
            text-align: right;
            font-weight: 700;
            white-space: nowrap;
        }

        .two-col {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .two-col td {
            width: 50%;
            vertical-align: top;
        }

        .two-col td.left {
            padding-right: 10px;
        }

        .two-col td.right {
            padding-left: 10px;
            text-align: right;
        }

        .heading {
            margin: 0 0 2px;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: #0f3d36;
        }

        .name {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            color: #1f2937;
        }

        .muted {
            color: #667085;
        }

        .contact {
            margin-top: 2px;
            font-size: 9px;
            line-height: 1.35;
        }

        .divider {
            height: 1px;
            border-top: 1px dashed #cfd8d2;
            margin: 9px 0;
        }

        .kv-row {
            width: 100%;
            border-collapse: collapse;
        }

        .kv-row tr td {
            padding: 2px 0;
            vertical-align: top;
        }

        .kv-label {
            color: #667085;
            width: 40%;
        }

        .kv-value {
            width: 60%;
            font-weight: 600;
            color: #1f2937;
            text-align: right;
            word-break: break-word;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 700;
            border: 1px solid #d1d5db;
            background: #fbfaf7;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .badge.booking {
            background: #f8f1dd;
            border-color: #e4cc88;
            color: #7a5a17;
            text-transform: none;
        }

        .badge.status-green {
            background: #dcfce7;
            border-color: #bbf7d0;
            color: #166534;
        }

        .badge.status-yellow {
            background: #fef9c3;
            border-color: #fde68a;
            color: #a16207;
        }

        .badge.status-red {
            background: #fee2e2;
            border-color: #fecaca;
            color: #b91c1c;
        }

        .badge.status-purple {
            background: #f3e8ff;
            border-color: #e9d5ff;
            color: #7e22ce;
        }

        .badge.payment-green {
            background: #dcfce7;
            border-color: #bbf7d0;
            color: #166534;
        }

        .badge.payment-amber {
            background: #fef3c7;
            border-color: #fde68a;
            color: #92400e;
        }

        .badge.payment-yellow {
            background: #fef9c3;
            border-color: #fde68a;
            color: #a16207;
        }

        .badge.payment-rose {
            background: #ffe4e6;
            border-color: #fecdd3;
            color: #be123c;
        }

        .badge.method {
            background: #f6f7fb;
            border-color: #d9deea;
            color: #394154;
            text-transform: none;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-top: 2px;
        }

        .items th,
        .items td {
            border-bottom: 1px solid #ece8dc;
            padding: 4px 5px;
            text-align: left;
            vertical-align: top;
        }

        .items th {
            background: #0f3d36;
            color: #ffffff;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .items tr:nth-child(even) td {
            background: #fafcf9;
        }

        .items tr:last-child td {
            border-bottom: none;
        }

        .right-align {
            text-align: right;
        }

        .total-box {
            margin-top: 4px;
            margin-left: auto;
            width: 265px;
        }

        .totals {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        .totals td {
            padding: 3px 0;
            border-bottom: 1px solid #efe9d7;
        }

        .totals td:last-child {
            text-align: right;
        }

        .totals tr.grand td {
            font-size: 11px;
            font-weight: 700;
            color: #0f3d36;
            border-top: 2px solid #c6a15b;
            border-bottom: none;
            padding-top: 6px;
        }

        .receipt-note {
            margin-top: 6px;
            border-left: 3px solid #c6a15b;
            background: #fbf7ee;
            padding: 6px 8px;
            border-radius: 6px;
            font-size: 8.5px;
            line-height: 1.35;
            color: #59431a;
        }

        .next-step {
            margin-top: 4px;
            font-size: 8.5px;
            color: #4b5563;
            line-height: 1.35;
            max-width: 300px;
        }

        .next-step p {
            margin: 0 0 5px;
        }

        .next-step .deposit {
            border-left: 2px solid #c6a15b;
            padding-left: 6px;
            color: #7a5a17;
        }

        .messenger-link {
            display: inline-block;
            margin-top: 3px;
            background: #0084ff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 8px;
            font-weight: 700;
        }

        .footer-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 6px;
        }

        .footer-grid td {
            vertical-align: middle;
        }

        .brand-mark {
            text-align: center;
        }

        .brand-mark strong {
            display: block;
            font-size: 10px;
            letter-spacing: 0.2em;
            color: #0f3d36;
            text-transform: uppercase;
        }

        .brand-mark span {
            display: block;
            margin-top: 1px;
            letter-spacing: 0.16em;
            color: #667085;
            text-transform: uppercase;
            font-size: 8px;
        }

        .brand-mark p {
            margin: 5px 0 0;
            color: #667085;
            font-size: 8px;
            line-height: 1.3;
        }

        .official-badge {
            display: inline-block;
            margin-top: 5px;
            border: 1px solid #c6a15b;
            color: #7a5a17;
            border-radius: 999px;
            padding: 2px 7px;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            background: #f8f1dd;
        }

        .qr {
            text-align: center;
        }

        .qr-shell {
            display: inline-block;
            padding: 4px;
            border: 1px solid #d7ccb0;
            border-radius: 7px;
            background: #fff;
        }

        .qr-shell img {
            width: 90px;
            height: 90px;
        }

        .caption {
            margin-top: 3px;
            font-size: 8px;
            color: #667085;
        }

        .footer {
            margin-top: 6px;
            font-size: 8px;
            color: #667085;
            text-align: center;
        }

        .peso {
            font-family: DejaVu Sans, Arial, sans-serif;
        }
    </style>
</head>
<body>
    @php
        $bookingStatusKey = strtolower((string) $booking->booking_status);
        $paymentStatusKey = strtolower((string) $booking->payment_status);

        $bookingStatusBadgeClass = match ($bookingStatusKey) {
            'completed' => 'status-green',
            'cancelled' => 'status-red',
            'pending' => 'status-yellow',
            'confirmed', 'reserved' => 'status-green',
            'occupied' => 'status-green',
            'rescheduled' => 'status-purple',
            default => 'status-yellow',
        };

        $paymentStatusBadgeClass = match ($paymentStatusKey) {
            'paid' => 'payment-green',
            'partial' => 'payment-amber',
            'unpaid' => 'payment-yellow',
            'refunded' => 'payment-rose',
            default => 'payment-yellow',
        };
    @endphp
    <div class="page">
        <div class="watermark">
            <strong>LEGITIMATE OFFICIAL COPY</strong>
            <span>Accountable to Marcelino&apos;s Resort Hotel</span>
        </div>

        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="brand-cell">
                        <div class="brand-wrap">
                            <div class="brand-logo">
                                @if (! empty($logoSrc))
                                    <img src="{{ $logoSrc }}" alt="Marcelino's logo">
                                @else
                                    <div class="logo-fallback">M</div>
                                @endif
                            </div>
                            <div class="brand-copy">
                                <p class="brand-kicker">Marcelino&apos;s Resort Hotel</p>
                                <p class="brand-name">Billing Statement</p>
                                <p class="brand-subtitle">Prepared from your confirmed booking details.</p>
                            </div>
                        </div>
                    </td>
                    <td class="meta-cell">
                        <p class="statement-title">Invoice</p>
                        <p class="statement-subtitle">Statement No.: {{ $booking->reference_number }}</p>
                        <p class="statement-subtitle">Issued: {{ $issuedAt->timezone('Asia/Manila')->format('F j, Y g:i A') }}</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="content">
            @if ($booking->booking_status === 'pending_verification')
                <div class="banner">
                    <strong>Confirm your booking by email</strong>
                    <div>Your reservation is not active until the email verification link is opened.</div>
                </div>
            @endif

            <table class="two-col">
                <tr>
                    <td class="left">
                        <p class="heading">Accountable to</p>
                        <p class="name">{{ $guestName }}</p>
                        <div class="contact muted">
                            @if ($guestAddress !== '—')
                                <div>{{ $guestAddress }}</div>
                            @endif
                            <div>Phone: {{ $booking->guest?->contact_num ?? '—' }}</div>
                            <div>Email: {{ $booking->guest?->email ?? '—' }}</div>
                        </div>
                    </td>
                    <td class="right">
                        <p class="heading">Remittance to</p>
                        <p class="name">Marcelino&apos;s Resort Hotel</p>
                        <div class="contact muted">
                            <div>Mabini ST. Eastern Barangay Poblacion, Hilongos, Philippines, 6524</div>
                            <div>Phone: 09063034150</div>
                            <div>Phone: 09541865049</div>
                            <div>Email: marcelinosresorthotel@gmail.com</div>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="divider"></div>

            <table class="two-col">
                <tr>
                    <td class="left" style="text-align:left;">
                        <table class="kv-row">
                            <tr>
                                <td class="kv-label">Booking type</td>
                                <td class="kv-value"><span class="badge booking">{{ $bookingTypeLabel }}</span></td>
                            </tr>
                            <tr>
                                <td class="kv-label">Check-in</td>
                                <td class="kv-value">{{ $checkIn ? $checkIn->timezone('Asia/Manila')->format('F j, Y g:i A') : '—' }}</td>
                            </tr>
                            <tr>
                                <td class="kv-label">Check-out</td>
                                <td class="kv-value">{{ $checkOut ? $checkOut->timezone('Asia/Manila')->format('F j, Y g:i A') : '—' }}</td>
                            </tr>
                            <tr>
                                <td class="kv-label">Nights / Days</td>
                                <td class="kv-value">{{ $billingUnits }}</td>
                            </tr>
                            @if ($venueEventTypeLabel !== 'Wedding' || ! empty($venueItems))
                                <tr>
                                    <td class="kv-label">Event type</td>
                                    <td class="kv-value">{{ $venueEventTypeLabel }}</td>
                                </tr>
                            @endif
                        </table>
                    </td>
                    <td class="right">
                        <table class="kv-row">
                            <tr>
                                <td class="kv-label">Stay status</td>
                                <td class="kv-value"><span class="badge {{ $bookingStatusBadgeClass }}">{{ $bookingStatusLabel }}</span></td>
                            </tr>
                            <tr>
                                <td class="kv-label">Payment status</td>
                                <td class="kv-value"><span class="badge {{ $paymentStatusBadgeClass }}">{{ $paymentStatusLabel }}</span></td>
                            </tr>
                            <tr>
                                <td class="kv-label">Method</td>
                                <td class="kv-value"><span class="badge method">{{ $paymentMethodLabel }}</span></td>
                            </tr>
                            <tr>
                                <td class="kv-label">Amount paid</td>
                                <td class="kv-value"><span class="peso">P</span>{{ number_format($amountPaid, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="kv-label">Remaining balance</td>
                                <td class="kv-value"><span class="peso">P</span>{{ number_format($balance, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            @if (! empty($cancellationRefund))
                @php
                    $crAppliesPercent = ! empty($cancellationRefund['applies_cancellation_percent']);
                @endphp
                <div class="banner cancellation-refund" style="margin-top: 10px;">
                    <strong>Cancellation — refund transparency</strong>
                    @if ($crAppliesPercent)
                        <div class="muted" style="margin-top: 2px; font-size: 8.5px; line-height: 1.35;">
                            Based on the cancellation policy in effect now: <strong>{{ (int) $cancellationRefund['fee_percent'] }}%</strong> of the booking total is the cancellation fee. Amounts below show how that applies to what you paid.
                        </div>
                        <table class="cr-table">
                            <tr>
                                <td>Booking total (for fee calculation)</td>
                                <td><span class="peso">&#8369;</span>{{ number_format((float) $grandTotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Cancellation fee ({{ (int) $cancellationRefund['fee_percent'] }}% of booking total)</td>
                                <td><span class="peso">&#8369;</span>{{ number_format((float) $cancellationRefund['fee_from_total'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Amount you paid</td>
                                <td><span class="peso">&#8369;</span>{{ number_format((float) $cancellationRefund['amount_paid'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Deducted / retained (non-refundable portion)</td>
                                <td><span class="peso">&#8369;</span>{{ number_format((float) $cancellationRefund['retained'], 2) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Refund to you (after deduction)</strong></td>
                                <td><strong><span class="peso">&#8369;</span>{{ number_format((float) $cancellationRefund['refund_to_guest'], 2) }}</strong></td>
                            </tr>
                        </table>
                    @else
                        <div class="muted" style="margin-top: 2px; font-size: 8.5px; line-height: 1.35;">
                            {{ (string) ($cancellationRefund['statement_note'] ?? '') }}
                        </div>
                        <table class="cr-table">
                            <tr>
                                <td>Booking total</td>
                                <td><span class="peso">&#8369;</span>{{ number_format((float) $grandTotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Amount you paid (partial / reservation)</td>
                                <td><span class="peso">&#8369;</span>{{ number_format((float) $cancellationRefund['amount_paid'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Non-refundable (reservation fee)</td>
                                <td><span class="peso">&#8369;</span>{{ number_format((float) $cancellationRefund['retained'], 2) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Refund to you</strong></td>
                                <td><strong><span class="peso">&#8369;</span>{{ number_format((float) $cancellationRefund['refund_to_guest'], 2) }}</strong></td>
                            </tr>
                        </table>
                    @endif
                </div>
            @endif

            @if (! empty($venueItems))
                <div style="text-align:center; margin-top: 6px;">
                    <span style="display:inline-block; font-size: 8px; font-style: italic; color: #8a5a00; background: #fff7e6; border: 1px solid #f3d58a; border-radius: 6px; padding: 2px 8px;">
                        *Check-in time: 8:00 AM - Check-out time: 12:00 AM
                    </span>
                </div>
            @endif

            <div class="divider"></div>

            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 9%;">No.</th>
                        <th>Description</th>
                        <th style="width: 16%;" class="right-align">Rate</th>
                        <th style="width: 12%;" class="right-align">Qty</th>
                        <th style="width: 18%;" class="right-align">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php $line = 1; @endphp

                    @forelse ($roomItems as $item)
                        <tr>
                            <td>#{{ $line++ }}</td>
                            <td><strong>{{ $item['label'] }}</strong></td>
                            <td class="right-align"><span class="peso">&#8369;</span>{{ number_format((float) $item['unit_price'], 2) }}</td>
                            <td class="right-align">{{ $item['quantity'] }}</td>
                            <td class="right-align"><span class="peso">&#8369;</span>{{ number_format((float) $item['line_total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">No room line items recorded.</td>
                        </tr>
                    @endforelse

                    @foreach ($venueItems as $item)
                        <tr>
                            <td>#{{ $line++ }}</td>
                            <td>
                                <strong>{{ $item['label'] }}</strong>
                                <div class="muted" style="font-size:8px; margin-top:1px;">{{ $item['event_type'] }} event · Capacity {{ $item['capacity'] }}</div>
                            </td>
                            <td class="right-align"><span class="peso">&#8369;</span>{{ number_format((float) $item['unit_price'], 2) }}</td>
                            <td class="right-align">{{ $billingUnits }}</td>
                            <td class="right-align"><span class="peso">&#8369;</span>{{ number_format((float) $item['line_total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="two-col" style="margin-top: 4px;">
                <tr>
                    <td class="left" style="text-align:left;">
                        <div class="next-step">
                            <p>Thank you for choosing Marcelino&apos;s Resort Hotel. Please bring a valid ID at check-in.</p>
                            @if ($showMessengerDepositBlock)
                                <p class="deposit">
                                    <strong>Next step:</strong> To settle your {{ $downPaymentPercent }}% down payment, please message us on Facebook Messenger and attach your proof of payment so we can verify your deposit. Unpaid bookings may be cancelled after 9:00 PM (Philippine time) on your check-in date if not settled.
                                </p>
                                @if (! empty($messengerLink))
                                    <a class="messenger-link" href="{{ $messengerLink }}">Open Messenger</a>
                                @endif
                            @else
                                <p class="deposit">
                                    <strong>Next step:</strong> Pay your deposit by <strong>{{ $depositDueLabel }}</strong> so this reservation stays confirmed. The amounts and schedule are in the payment summary. Unpaid bookings may be cancelled after 9:00 PM (Philippine time) on your check-in date if not settled.
                                </p>
                            @endif
                        </div>
                    </td>
                    <td class="right">
                        <div class="total-box" style="margin-top:0;">
                            <table class="totals">
                                <tr>
                                    <td>Room subtotal</td>
                                    <td><span class="peso">&#8369;</span>{{ number_format($roomSubtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Venue subtotal</td>
                                    <td><span class="peso">&#8369;</span>{{ number_format($venueSubtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Original total</td>
                                    <td><span class="peso">&#8369;</span>{{ number_format($originalTotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Discount applied</td>
                                    <td>- <span class="peso">&#8369;</span>{{ number_format($discountApplied, 2) }}</td>
                                </tr>
                                @if ((float) $discountApplied > 0)
                                    <tr>
                                        <td>Discount target</td>
                                        <td>{{ $discountTargetLabel ?? 'Grand total (room + venue)' }}</td>
                                    </tr>
                                @endif
                                <tr class="grand">
                                    <td>Grand total</td>
                                    <td><span class="peso">&#8369;</span>{{ number_format($grandTotal, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="receipt-note">
                This is your official billing statement from Marcelino&apos;s Resort Hotel. It is a valid copy of your booking details and payment summary.
                </div>

            <div class="divider"></div>

            <table class="footer-grid">
                <tr>
                    <td colspan="2">
                        <div class="brand-mark">
                            <strong>MARCELINO&apos;S</strong>
                            <span>RESORT AND HOTEL</span>
                            <p>This billing statement is issued by Marcelino&apos;s Resort Hotel and does not require a physical signature.</p>
                            <div class="official-badge">Official accountable copy</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <div class="qr">
                            @if ($qrCodeDataUri)
                                <div class="qr-shell">
                                    <img src="{{ $qrCodeDataUri }}" alt="QR Code Image">
                                </div>
                                <div class="caption">Scan to view your digital receipt</div>
                            @else
                                <div class="caption">No QR code is available for this booking.</div>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>

            <div class="footer">
                Marcelino&apos;s Resort Hotel · Billing Statement PDF · Official guest copy
            </div>
        </div>
    </div>
</body>
</html>

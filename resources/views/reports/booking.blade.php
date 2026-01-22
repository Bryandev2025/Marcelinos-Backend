<!DOCTYPE html>
<html>
<head>
    <title>Booking Report</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background-color: #f0f0f0; }
        .header { text-align: center; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="header">
    <h2>Booking Report - {{ ucfirst($period) }}</h2>
    <p>Generated: {{ $generatedAt->format('M d, Y h:i A') }}</p>
</div>

<table>
    <thead>
        <tr>
            <th>Guest</th>
            <th>Room / Venue</th>
            <th>Check-in</th>
            <th>Check-out</th>
            <th>Total</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($bookings as $booking)
            <tr>
                <td>{{ $booking->guest->first_name }} {{ $booking->guest->last_name }}</td>
                <td>{{ $booking->room->name ?? $booking->venue->name }}</td>
                <td>{{ $booking->check_in->format('M d, Y h:i A') }}</td>
                <td>{{ $booking->check_out->format('M d, Y h:i A') }}</td>
                <td>₱{{ number_format($booking->total_price, 2) }}</td>
                <td>{{ ucfirst($booking->status) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="text-align: center;">No bookings found for this period.</td>
            </tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
{{--
Print Report Partial Template
Variables: $title, $subtitle, $localData (Collection), $foreignData (Collection)
--}}
<div style="font-family: Arial, sans-serif; color: #111; padding: 32px; background: white;">
    <!-- Header -->
    <div style="text-align:center; margin-bottom: 32px; border-bottom: 2px solid #111; padding-bottom: 16px;">
        <h1
            style="font-size: 24px; font-weight: bold; margin: 0 0 6px 0; text-transform: uppercase; letter-spacing: 1px;">
            {{ $title }}
        </h1>
        <p style="font-size: 13px; color: #555; margin: 0;">{{ $subtitle }}</p>
        <p style="font-size: 11px; color: #888; margin: 6px 0 0 0;">Marcelinos Resort and Hotel — Tourism Demographics
            Record</p>
    </div>

    <!-- Domestic Tourists -->
    <div style="margin-bottom: 40px;">
        <div
            style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #222; padding-bottom: 6px; margin-bottom: 12px;">
            <h2 style="font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 0;">
                🗺 Domestic Tourists</h2>
            <span
                style="font-size: 13px; font-weight: bold; background: #f3f4f6; border: 1px solid #ccc; padding: 2px 10px; border-radius: 4px;">
                Total: {{ $localData->sum('total') }}
            </span>
        </div>

        @if($localData->isEmpty())
            <p style="text-align:center; color: #888; font-style: italic; padding: 16px 0;">No domestic guest records for
                this period.</p>
        @else
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background-color: #f9fafb;">
                        <th
                            style="text-align:left; padding: 8px 12px; border: 1px solid #ddd; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            Region</th>
                        <th
                            style="text-align:left; padding: 8px 12px; border: 1px solid #ddd; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            Province</th>
                        <th
                            style="text-align:left; padding: 8px 12px; border: 1px solid #ddd; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            Municipality / City</th>
                        <th
                            style="text-align:right; padding: 8px 12px; border: 1px solid #ddd; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            Booking Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($localData as $i => $stat)
                        <tr style="background-color: {{ $i % 2 === 0 ? '#ffffff' : '#f9fafb' }};">
                            <td style="padding: 7px 12px; border: 1px solid #ddd; font-weight: 600; color: #1d4ed8;">
                                {{ $stat->region ?: 'N/A' }}</td>
                            <td style="padding: 7px 12px; border: 1px solid #ddd;">{{ $stat->province ?: 'N/A' }}</td>
                            <td style="padding: 7px 12px; border: 1px solid #ddd;">{{ $stat->municipality ?: 'N/A' }}</td>
                            <td style="padding: 7px 12px; border: 1px solid #ddd; text-align:right; font-weight: bold;">
                                {{ $stat->total }}</td>
                        </tr>
                    @endforeach
                    <tr style="background: #eff6ff; font-weight: bold;">
                        <td colspan="3"
                            style="padding: 8px 12px; border: 1px solid #ddd; text-align:right; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                            Grand Total (Domestic)</td>
                        <td style="padding: 8px 12px; border: 1px solid #ddd; text-align:right; font-size: 14px;">
                            {{ $localData->sum('total') }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>

    <!-- International Tourists -->
    <div>
        <div
            style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #222; padding-bottom: 6px; margin-bottom: 12px;">
            <h2 style="font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 0;">
                🌐 International Tourists</h2>
            <span
                style="font-size: 13px; font-weight: bold; background: #f3f4f6; border: 1px solid #ccc; padding: 2px 10px; border-radius: 4px;">
                Total: {{ $foreignData->sum('total') }}
            </span>
        </div>

        @if($foreignData->isEmpty())
            <p style="text-align:center; color: #888; font-style: italic; padding: 16px 0;">No international guest records
                for this period.</p>
        @else
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background-color: #f9fafb;">
                        <th
                            style="text-align:left; padding: 8px 12px; border: 1px solid #ddd; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            Country of Origin</th>
                        <th
                            style="text-align:right; padding: 8px 12px; border: 1px solid #ddd; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                            Booking Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($foreignData as $i => $stat)
                        <tr style="background-color: {{ $i % 2 === 0 ? '#ffffff' : '#f9fafb' }};">
                            <td style="padding: 7px 12px; border: 1px solid #ddd; font-weight: 600;">
                                {{ $stat->country ?: 'N/A' }}</td>
                            <td style="padding: 7px 12px; border: 1px solid #ddd; text-align:right; font-weight: bold;">
                                {{ $stat->total }}</td>
                        </tr>
                    @endforeach
                    <tr style="background: #f0fdf4; font-weight: bold;">
                        <td
                            style="padding: 8px 12px; border: 1px solid #ddd; text-align:right; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                            Grand Total (International)</td>
                        <td style="padding: 8px 12px; border: 1px solid #ddd; text-align:right; font-size: 14px;">
                            {{ $foreignData->sum('total') }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>

    <!-- Footer -->
    <div
        style="margin-top: 48px; border-top: 1px solid #ccc; padding-top: 12px; display: flex; justify-content: space-between; font-size: 11px; color: #888;">
        <span>Marcelinos Resort and Hotel — Confidential Tourism Report</span>
        <span>Printed: {{ now()->format('F j, Y  g:i A') }}</span>
    </div>
</div>
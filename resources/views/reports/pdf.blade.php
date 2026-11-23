<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>EME'S Apartelle - Performance Report</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        /* Header */
        .header-table {
            width: 100%;
            border-bottom: 3px solid #bc9151;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .brand-title {
            font-size: 22px;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .brand-subtitle {
            font-size: 10px;
            color: #64748b;
            margin: 3px 0 0 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-meta {
            text-align: right;
            font-size: 10px;
            color: #475569;
        }
        .report-badge {
            display: inline-block;
            background: #bc9151;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 12px;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        /* KPI Cards */
        .kpi-table {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: separate;
            border-spacing: 8px 0;
        }
        .kpi-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-top: 3px solid #bc9151;
            border-radius: 6px;
            padding: 12px 10px;
            text-align: center;
        }
        .kpi-label {
            font-size: 9px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .kpi-value {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
        }

        /* Section Titles */
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin: 18px 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            text-align: left;
        }
        .data-table td {
            padding: 7px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .data-table tr.total-row td {
            font-weight: bold;
            background-color: #f1f5f9;
            border-top: 2px solid #cbd5e1;
            border-bottom: 2px solid #cbd5e1;
            color: #0f172a;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .text-gold { color: #bc9151; }

        /* Badges */
        .status-badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-completed, .status-confirmed { background-color: #dcfce7; color: #15803d; }
        .status-pending { background-color: #fef9c3; color: #854d0e; }
        .status-cancelled { background-color: #fee2e2; color: #b91c1c; }

        /* Two columns layout */
        .two-col-table {
            width: 100%;
            border-collapse: collapse;
        }
        .two-col-table td {
            vertical-align: top;
            width: 50%;
        }

        /* Footer */
        .footer-note {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="vertical-align: middle;">
                <h1 class="brand-title">EME'S APARTELLE</h1>
                <p class="brand-subtitle">Apartelle &amp; Suites • Administrative Management Report</p>
            </td>
            <td class="report-meta" style="vertical-align: middle;">
                <span class="report-badge">Executive Summary</span>
                <div><strong>Generated:</strong> {{ $generatedAt }}</div>
                <div><strong>Prepared By:</strong> {{ $generatedBy }}</div>
            </td>
        </tr>
    </table>

    <!-- KPI Summary Grid -->
    <table class="kpi-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value text-gold">&#8369;{{ number_format($summary['total_revenue'] ?? 0, 2) }}</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">Total Bookings</div>
                <div class="kpi-value">{{ number_format($summary['total_bookings'] ?? 0) }}</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">Avg Booking Value</div>
                <div class="kpi-value">&#8369;{{ number_format($summary['avg_booking_value'] ?? 0, 2) }}</div>
            </td>
            <td class="kpi-card" style="width: 25%;">
                <div class="kpi-label">Cancellation Rate</div>
                <div class="kpi-value">{{ number_format($summary['cancellation_rate'] ?? 0, 1) }}%</div>
            </td>
        </tr>
    </table>

    <!-- Room Performance Analysis -->
    <div class="section-title">Room Performance Analysis ({{ ucfirst($roomTimeframe) }})</div>
    <table class="data-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th>Room Type</th>
                <th class="text-center">Total Bookings</th>
                <th class="text-right">Total Revenue</th>
                <th class="text-right">Share of Revenue</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalRoomBookings = 0;
                $totalRoomRevenue = 0;
                foreach($roomData as $r) {
                    $totalRoomBookings += (int)($r['count'] ?? 0);
                    $totalRoomRevenue += (float)($r['total_revenue'] ?? 0);
                }
            @endphp

            @forelse($roomData as $room)
                @php
                    $rev = (float)($room['total_revenue'] ?? 0);
                    $share = $totalRoomRevenue > 0 ? ($rev / $totalRoomRevenue) * 100 : 0;
                @endphp
                <tr>
                    <td class="text-bold">{{ $room['room_type'] ?? 'N/A' }}</td>
                    <td class="text-center">{{ number_format($room['count'] ?? 0) }}</td>
                    <td class="text-right text-bold">&#8369;{{ number_format($rev, 2) }}</td>
                    <td class="text-right">{{ number_format($share, 1) }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center" style="color: #94a3b8; padding: 12px;">No room performance data available for this timeframe.</td>
                </tr>
            @endforelse

            @if(count($roomData) > 0)
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td class="text-center">{{ number_format($totalRoomBookings) }}</td>
                    <td class="text-right text-gold">&#8369;{{ number_format($totalRoomRevenue, 2) }}</td>
                    <td class="text-right">100.0%</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Two-Column Breakdown: Revenue History & Status Distribution -->
    <table class="two-col-table" cellpadding="0" cellspacing="0">
        <tr>
            <!-- Revenue History Table -->
            <td style="padding-right: 10px;">
                <div class="section-title">Revenue History ({{ ucfirst($financialTimeframe) }})</div>
                <table class="data-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th class="text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $sumFinancial = 0; @endphp
                        @forelse($financialData as $f)
                            @php
                                $fRev = (float)($f['revenue'] ?? 0);
                                $sumFinancial += $fRev;
                            @endphp
                            <tr>
                                <td>{{ $f['month'] ?? 'N/A' }}</td>
                                <td class="text-right text-bold">&#8369;{{ number_format($fRev, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center" style="color: #94a3b8; padding: 10px;">No revenue records found.</td>
                            </tr>
                        @endforelse

                        @if(count($financialData) > 0)
                            <tr class="total-row">
                                <td>TOTAL</td>
                                <td class="text-right text-gold">&#8369;{{ number_format($sumFinancial, 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </td>

            <!-- Booking Status Distribution -->
            <td style="padding-left: 10px;">
                <div class="section-title">Status Summary ({{ ucfirst($statusTimeframe) }})</div>
                <table class="data-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th class="text-center">Count</th>
                            <th class="text-right">Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalStatusCount = 0;
                            foreach($statusData as $s) {
                                $totalStatusCount += (int)($s['count'] ?? 0);
                            }
                        @endphp

                        @forelse($statusData as $status)
                            @php
                                $sCount = (int)($status['count'] ?? 0);
                                $sShare = $totalStatusCount > 0 ? ($sCount / $totalStatusCount) * 100 : 0;
                                $sName = strtolower($status['status'] ?? '');
                            @endphp
                            <tr>
                                <td>
                                    <span class="status-badge status-{{ $sName }}">
                                        {{ ucfirst($status['status'] ?? 'N/A') }}
                                    </span>
                                </td>
                                <td class="text-center text-bold">{{ number_format($sCount) }}</td>
                                <td class="text-right">{{ number_format($sShare, 1) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center" style="color: #94a3b8; padding: 10px;">No status data found.</td>
                            </tr>
                        @endforelse

                        @if(count($statusData) > 0)
                            <tr class="total-row">
                                <td>TOTAL</td>
                                <td class="text-center">{{ number_format($totalStatusCount) }}</td>
                                <td class="text-right">100.0%</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- Booking Trends Table -->
    @if(count($trendsData) > 0)
        <div class="section-title">Booking Volume Trends ({{ ucfirst($trendsTimeframe) }})</div>
        <table class="data-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>Date / Period</th>
                    <th class="text-center">New Bookings</th>
                </tr>
            </thead>
            <tbody>
                @php $totalTrendCount = 0; @endphp
                @foreach($trendsData as $trend)
                    @php $totalTrendCount += (int)($trend['count'] ?? 0); @endphp
                    <tr>
                        <td>{{ $trend['date'] ?? 'N/A' }}</td>
                        <td class="text-center text-bold">{{ number_format($trend['count'] ?? 0) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td class="text-center">{{ number_format($totalTrendCount) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <!-- Footer Note -->
    <div class="footer-note">
        EME'S Apartelle • Administrative Performance Report • Confidential Document for Internal Use Only
    </div>

</body>
</html>


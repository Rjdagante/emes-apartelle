<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="utf-8">
    <title>Invoice #{{ $reservation->id }}</title>
    <style>
        body { font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, .15); font-size: 16px; line-height: 24px; }
        .invoice-box table { width: 100%; line-height: inherit; text-align: left; }
        .invoice-box table td { padding: 5px; vertical-align: top; }
        .invoice-box table tr td:nth-child(2) { text-align: right; }
        .invoice-box table tr.top table td { padding-bottom: 20px; }
        .invoice-box table tr.top table td.title { font-size: 45px; line-height: 45px; color: #333; font-weight: bold; }
        .invoice-box table tr.information table td { padding-bottom: 40px; }
        .invoice-box table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
        .invoice-box table tr.details td { padding-bottom: 20px; }
        .invoice-box table tr.item td { border-bottom: 1px solid #eee; }
        .invoice-box table tr.item.last td { border-bottom: none; }
        .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; }
        .badge { padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-unpaid { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <table cellpadding="0" cellspacing="0">
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="title">EME'S Apartelle</td>
                            <td>
                                Invoice #: {{ $reservation->id }}<br>
                                Created: {{ $date }}<br>
                                Status: <span class="badge {{ $reservation->payment_status === 'paid' ? 'badge-paid' : 'badge-unpaid' }}">
                                    {{ $reservation->payment_status }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="information">
                <td colspan="2">
                    <table>
                        <tr>
                            <td>
                                EME's Apartelle<br>
                                123 Sunshine Street<br>
                                City, Province, 4000
                            </td>
                            <td>
                                {{ $reservation->guest_name ?? $reservation->user->name }}<br>
                                {{ $reservation->user->email }}<br>
                                {{ $reservation->user->phone ?? ($reservation->user->contact_number ?? '') }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="heading">
                <td>Description</td>
                <td>Details</td>
            </tr>
            <tr class="item">
                <td>Room Type</td>
                <td>{{ $reservation->room->room_type }} (#{{ $reservation->room->room_number }})</td>
            </tr>
            @php
                $checkIn = \Carbon\Carbon::parse($reservation->check_in);
                $checkOut = \Carbon\Carbon::parse($reservation->check_out);
                $nights = (int) $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay());
                if ($nights <= 0) $nights = 1;
            @endphp
            <tr class="item">
                <td>Stay Duration</td>
                <td>{{ $checkIn->format('M d, Y (1:00 PM)') }} to {{ $checkOut->format('M d, Y (11:00 AM)') }} ({{ $nights }} night{{ $nights > 1 ? 's' : '' }})</td>
            </tr>
            @if($reservation->package)
            <tr class="item">
                <td>Tour Package</td>
                <td>
                    <strong>{{ $reservation->package->name }}</strong><br>
                    <span style="color: #666; font-size: 13px;">{{ $reservation->package->duration }} &middot; {{ $reservation->package->pax }}</span>
                    @if($reservation->package->inclusions)
                        <br><span style="color: #777; font-size: 12px;">Inclusions: {{ $reservation->package->inclusions }}</span>
                    @endif
                </td>
            </tr>
            <tr class="item">
                <td>Package Rate</td>
                <td>
                    &#8369;{{ number_format($reservation->package->price, 2) }}
                    <br>
                    <span style="color: #666; font-size: 13px; font-style: italic;">(All-inclusive fixed rate)</span>
                </td>
            </tr>
            @elseif(in_array($reservation->room->room_type, ['Family Room', 'Barkadahan Room']))
            @php
                $adults = $reservation->adults ?? $reservation->guests ?? 1;
                $children = $reservation->children ?? 0;
                $childrenAges = is_array($reservation->children_ages) ? $reservation->children_ages : (json_decode($reservation->children_ages ?? '[]', true) ?: []);
                $freeChildren = 0;
                $payingChildren = 0;
                foreach ($childrenAges as $a) {
                    if ((int)$a >= 7) $payingChildren++;
                    else $freeChildren++;
                }
                $chargeableGuests = max(1, $adults + $payingChildren);
            @endphp
            <tr class="item">
                <td>Guests</td>
                <td>
                    {{ $reservation->guests ?? 1 }} Guests
                    <span style="color: #666; font-size: 13px;">({{ $adults }} Adult{{ $adults > 1 ? 's' : '' }}@if($children > 0), {{ $children }} Child{{ $children > 1 ? 'ren' : '' }}@if($freeChildren > 0) [{{ $freeChildren }} Free under 7]@endif @endif)</span>
                    @if(($reservation->extra_guests ?? 0) > 0)
                        <br>
                        <span style="color: #bc9151; font-size: 13px; font-weight: bold;">
                            ({{ $reservation->extra_guests }} Additional Bedding{{ $reservation->extra_guests > 1 ? 's' : '' }})
                        </span>
                    @endif
                    <br>
                    <span style="color: #666; font-size: 13px; font-style: italic;">
                        (Room Capacity: {{ $reservation->room->min_occupancy }} - {{ $reservation->room->max_occupancy }} Guests)
                    </span>
                </td>
            </tr>
            <tr class="item">
                <td>Per Head Rate</td>
                <td>
                    &#8369;{{ number_format($reservation->room->price_per_head, 2) }} / head / night
                    <br>
                    <span style="color: #666; font-size: 13px; font-style: italic;">
                        ((&#8369;{{ number_format($reservation->room->price_per_head, 2) }} × {{ $chargeableGuests }} paying guests) × {{ $nights }} night{{ $nights > 1 ? 's' : '' }}@if($freeChildren > 0) + {{ $freeChildren }} Child Free @endif)
                    </span>
                </td>
            </tr>
            @else
            <tr class="item">
                <td>Guests</td>
                <td>
                    {{ $reservation->guests ?? 1 }} Guests
                    @if(($reservation->extra_guests ?? 0) > 0)
                        <br>
                        <span style="color: #bc9151; font-size: 13px; font-weight: bold;">
                            ({{ $reservation->extra_guests }} Additional Bedding{{ $reservation->extra_guests > 1 ? 's' : '' }})
                        </span>
                    @endif
                    <br>
                    <span style="color: #666; font-size: 13px; font-style: italic;">
                        (Standard Capacity: {{ $reservation->room->max_occupancy }} Guests)
                    </span>
                </td>
            </tr>
            <tr class="item">
                <td>Nightly Rate</td>
                <td>
                    &#8369;{{ number_format($reservation->room->price_per_night, 2) }} / night
                    <br>
                    <span style="color: #666; font-size: 13px; font-style: italic;">
                        (&#8369;{{ number_format($reservation->room->price_per_night, 2) }} × {{ $nights }} night{{ $nights > 1 ? 's' : '' }})
                    </span>
                </td>
            </tr>
            @endif

            @php
                $extraBedRate = (float) ($reservation->room->extra_bed_price ?? 300.00);
                $extraGuestsCount = (int) ($reservation->extra_guests ?: max(0, ($reservation->guests ?? 1) - $reservation->room->max_occupancy));
                $extraCharge = (float) ($reservation->extra_bed_charge ?: ($extraGuestsCount * $extraBedRate * $nights));
            @endphp

            @if($extraGuestsCount > 0 && $extraCharge > 0)
            <tr class="item">
                <td>Additional Beddings</td>
                <td>
                    &#8369;{{ number_format($extraCharge, 2) }}
                    <br>
                    <span style="color: #666; font-size: 13px; font-style: italic;">
                        ({{ $extraGuestsCount }} extra bedding{{ $extraGuestsCount > 1 ? 's' : '' }} × &#8369;{{ number_format($extraBedRate, 2) }} × {{ $nights }} night{{ $nights > 1 ? 's' : '' }})
                    </span>
                </td>
            </tr>
            @endif
            @php
                $isHalfPayment = ($reservation->payment_option ?? 'full') === 'half';
                $isFullyPaid = $reservation->payment_status === 'paid';
                $hasProof = !empty($reservation->payment_screenshot);
                $totalAmount = (float) $reservation->total_amount;
                $paidAmount = $isFullyPaid
                    ? $totalAmount
                    : ($isHalfPayment ? (float) ($reservation->downpayment_amount ?: ($totalAmount / 2)) : ($hasProof ? $totalAmount : 0));
                $remainingBalance = $isFullyPaid ? 0 : max(0, $totalAmount - $paidAmount);
            @endphp
            <tr class="total">
                <td></td>
                <td>Total: &#8369;{{ number_format($totalAmount, 2) }}</td>
            </tr>
            @if($isHalfPayment && !$isFullyPaid)
            <tr class="item">
                <td>Amount Paid (50% Downpayment)</td>
                <td style="color: #155724; font-weight: bold;">&#8369;{{ number_format($paidAmount, 2) }}</td>
            </tr>
            <tr class="total">
                <td>Remaining Balance</td>
                <td>
                    &#8369;{{ number_format($remainingBalance, 2) }}<br>
                    <span style="color: #666; font-size: 12px; font-weight: normal;">Pay the other half when you walk in.</span>
                </td>
            </tr>
            @elseif($isFullyPaid || $hasProof)
            <tr class="item">
                <td>Amount Paid</td>
                <td style="color: #155724; font-weight: bold;">&#8369;{{ number_format($paidAmount, 2) }}</td>
            </tr>
            @endif
        </table>
        <div style="margin-top: 50px; text-align: center; color: #999; font-size: 12px;">
            Thank you for your business!
        </div>
    </div>
    <script>
        if (window.location.search.includes('print=true')) {
            window.onload = function() {
                window.print();
            }
        }
    </script>
</body>
</html>

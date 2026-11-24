<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\XenditService;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use App\Notifications\BookingCreated;
use App\Notifications\NewBookingForAdmin;
use Carbon\Carbon;

class ReservationController extends Controller
{
    protected $xenditService;

    public function __construct(XenditService $xenditService)
    {
        $this->xenditService = $xenditService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Reservation::with(['room', 'user', 'review', 'package']);
        
        // If user is not admin or staff, they can only see their own reservations
        if (!in_array($user->role, ['admin', 'staff'])) {
            $query->where('user_id', $user->id);
        }
        
        $reservations = $query->orderBy('created_at', 'desc')->get();
            
        return response()->json($reservations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $userId = $request->user_id ?? ($request->user() ? $request->user()->id : null);
        $user = $request->user();

        // ❌ BLOCK: If guest is suspended
        if ($user && $user->is_suspended) {
            return response()->json([
                'message' => 'Your account is suspended: ' . ($user->suspension_reason ?? 'Violation of house rules.') . ' You are restricted from making new bookings.',
                'is_suspended' => true,
                'reason' => $user->suspension_reason
            ], 403);
        }

        // Check System Availability Settings
        $maintenanceMode = \App\Models\SystemSetting::get('maintenance_mode', false);
        if ($maintenanceMode) {
            return response()->json([
                'message' => 'The booking system is currently under maintenance. Please try again later.'
            ], 503);
        }

        $onlineBooking = \App\Models\SystemSetting::get('online_booking', true);
        if (!$onlineBooking && (!$user || !in_array($user->role, ['admin', 'staff']))) {
            return response()->json([
                'message' => 'Online booking is temporarily disabled. Please contact the front desk for reservations.'
            ], 403);
        }
        
        $isStaffOrAdmin = $user && in_array($user->role, ['admin', 'staff']);

        $validator = Validator::make($request->all(), [
            'room_id' => 'required|exists:rooms,id',
            'package_id' => 'nullable|exists:room_packages,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'payment_option' => 'sometimes|required|in:full,half',
            'guests' => 'nullable|integer|min:1',
            'adults' => 'nullable|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'children_ages' => 'nullable',
            'extra_beds' => 'nullable|integer|min:0',
            'guest_name' => 'nullable|string|max:255',
            'payment_screenshot' => ($isStaffOrAdmin ? 'nullable' : 'required') . '|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'payment_screenshot.required' => 'Please upload a screenshot of your payment to proceed with your booking.',
            'payment_screenshot.image' => 'The payment proof must be a valid image file (JPG, PNG, or WEBP).',
        ]);

        if (!$userId && !$request->has('user_id')) {
             return response()->json(['user_id' => ['The user id field is required.']], 422);
        }

        if ($validator->fails()) {
             return response()->json($validator->errors(), 422);
        }

        $checkIn = Carbon::parse($request->check_in);
        if (strlen(trim($request->check_in)) <= 10 || $checkIn->format('H:i:s') === '00:00:00') {
            $checkIn = $checkIn->setTime(13, 0, 0);
        }

        $checkOut = Carbon::parse($request->check_out);
        if (strlen(trim($request->check_out)) <= 10 || $checkOut->format('H:i:s') === '00:00:00') {
            $checkOut = $checkOut->setTime(11, 0, 0);
        }

        // Check if room is available
        $isAvailable = !Reservation::where('room_id', $request->room_id)
            ->where(function ($query) use ($checkIn, $checkOut) {
                // Modified overlap logic for better accuracy
                $query->where(function($q) use ($checkIn, $checkOut) {
                    $q->where('check_in', '<', $checkOut)
                      ->where('check_out', '>', $checkIn);
                });
            })
            ->whereIn('status', ['pending', 'confirmed', 'checked-in'])
            ->exists();

        if (!$isAvailable) {
            return response()->json(['message' => 'Room is already reserved or pending confirmation for these dates.'], 422);
        }

        // Check if selected dates are blocked (globally or for this specific room)
        $blockedRecord = \App\Models\BlockedDate::where(function ($query) use ($request) {
                $query->where('room_id', $request->room_id)
                      ->orWhereNull('room_id');
            })
            ->where('start_date', '<', $checkOut)
            ->where('end_date', '>', $checkIn)
            ->first();

        if ($blockedRecord) {
            $reasonMsg = $blockedRecord->reason ? " Reason: {$blockedRecord->reason}." : '';
            return response()->json([
                'message' => "The selected dates are currently blocked and unavailable for booking.{$reasonMsg}"
            ], 422);
        }

        $room = Room::find($request->room_id);
        $days = (int) $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay());
        if ($days <= 0) $days = 1;
        
        $guests = (int) $request->input('guests', 1);
        $adults = (int) $request->input('adults', $guests);
        $children = (int) $request->input('children', 0);
        $childrenAges = $request->input('children_ages', []);
        if (is_string($childrenAges)) {
            $childrenAges = json_decode($childrenAges, true) ?: [];
        }

        // Child Policy:
        // Children under 7 years old (ages 0-6): Free of charge (₱0).
        // Children 7 years and older (ages 7-17): Charged the same price as adults.
        $payingChildren = 0;
        $freeChildren = 0;
        if (!empty($childrenAges) && is_array($childrenAges)) {
            foreach ($childrenAges as $age) {
                if ((int)$age >= 7) {
                    $payingChildren++;
                } else {
                    $freeChildren++;
                }
            }
        }

        $chargeableGuests = max(1, $adults + $payingChildren);
        $totalGuests = max(1, $adults + $children);

        $package = null;
        if ($request->filled('package_id')) {
            $package = \App\Models\RoomPackage::find($request->package_id);
        }

        if ($package) {
            $totalAmount = (float) $package->price;
            $extraGuests = 0;
            $extraBedCharge = 0.00;
        } else {
            // Maximum allowed guests strictly capped at room max occupancy
            $maxAllowed = (int) $room->max_occupancy;
            if ($totalGuests > $maxAllowed) {
                return response()->json([
                    'message' => "Maximum capacity for {$room->room_type} is {$maxAllowed} guests."
                ], 422);
            }

            // Extra bedding charge applies when guest requests extra beds (or exceeds standard occupancy)
            $requestedExtraBeds = (int) $request->input('extra_beds', 0);
            $maxExtraBeds = (int) ($room->max_extra_guests ?? 2);
            if ($requestedExtraBeds > $maxExtraBeds) {
                return response()->json([
                    'message' => "Maximum extra beds allowed for {$room->room_type} is {$maxExtraBeds}."
                ], 422);
            }

            $extraGuests = max($requestedExtraBeds, max(0, $chargeableGuests - (int) $room->max_occupancy));
            $extraBedRate = (float) ($room->extra_bed_price ?? 300.00);
            $extraBedCharge = $extraGuests * $extraBedRate * $days;

            $isPerHead = in_array($room->room_type, ['Family Room', 'Barkadahan Room']);
            if ($isPerHead) {
                $headRate = ($room->is_promo && $room->promo_price_per_head > 0) ? (float) $room->promo_price_per_head : (float) $room->price_per_head;
                // Paying guests (adults + children 7+) pay the per-head rate; under 7 are free (₱0)
                $baseAmount = $headRate * $chargeableGuests * $days;
            } else {
                $nightRate = ($room->is_promo && $room->promo_price > 0) ? (float) $room->promo_price : (float) $room->price_per_night;
                $baseAmount = $nightRate * $days;
            }

            $totalAmount = $baseAmount + $extraBedCharge;
        }

        $paymentOption = $request->payment_option ?? 'full';
        $downpaymentAmount = ($paymentOption === 'half') ? ($totalAmount / 2) : null;

        $guestName = $request->input('guest_name');
        if (!$guestName && $request->user()) {
            $guestName = $request->user()->name ?: trim(($request->user()->first_name ?? '') . ' ' . ($request->user()->last_name ?? ''));
        }

        $paymentScreenshotPath = null;
        if ($request->hasFile('payment_screenshot')) {
            $paymentScreenshotPath = $request->file('payment_screenshot')->store('payment_screenshots', 'public');
        }

        $reservation = Reservation::create([
            'user_id' => $userId,
            'guest_name' => $guestName,
            'room_id' => $request->room_id,
            'package_id' => $package ? $package->id : null,
            'guests' => $totalGuests,
            'adults' => $adults,
            'children' => $children,
            'children_ages' => $childrenAges,
            'extra_guests' => $extraGuests,
            'check_in' => $checkIn->format('Y-m-d H:i:s'),
            'check_out' => $checkOut->format('Y-m-d H:i:s'),
            'total_amount' => $totalAmount,
            'extra_bed_charge' => $extraBedCharge,
            'payment_option' => $paymentOption,
            'downpayment_amount' => $downpaymentAmount,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_screenshot' => $paymentScreenshotPath,
        ]);

        // Send Notification immediately
        $reservation->load(['room', 'user', 'package']);

        // Record audit log for booking creation
        try {
            AuditLog::recordBookingActivity(
                $reservation,
                'booking_created',
                $request->user(),
                null,
                null,
                [
                    'status' => $reservation->status,
                    'payment_status' => $reservation->payment_status,
                    'guests' => $reservation->guests,
                    'adults' => $reservation->adults,
                    'children' => $reservation->children,
                    'check_in' => $reservation->check_in,
                    'check_out' => $reservation->check_out,
                    'total_amount' => $reservation->total_amount,
                ],
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AuditLog (booking_created) failed: ' . $e->getMessage());
        }

        if ($request->user()) {
            $request->user()->notify(new BookingCreated($reservation));
        }

        // Notify Admins & Staff (other than the one who made the booking, if any)
        $adminsAndStaff = \App\Models\User::whereIn('role', ['admin', 'staff']);
        if ($request->user()) {
            $adminsAndStaff->where('id', '!=', $request->user()->id);
        }
        Notification::send($adminsAndStaff->get(), new NewBookingForAdmin($reservation));

        // Return reservation directly for receipt display (no online payment redirect; settle at the apartelle)
        return response()->json([
            'reservation' => $reservation,
            'message' => 'Reservation created successfully. Please settle payment at the apartelle.',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $reservation = Reservation::with(['room', 'user', 'package'])->find($id);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        return response()->json($reservation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        $user = $request->user();
        
        // ❌ BLOCK: If guest is suspended (Admins and Staff can still update)
        if ($user && !in_array($user->role, ['admin', 'staff']) && $user->is_suspended) {
            return response()->json([
                'message' => 'Your account is suspended. You cannot modify reservations.',
                'is_suspended' => true
            ], 403);
        }

        // Authorization Check for non-admin/staff users
        if ($user && !in_array($user->role, ['admin', 'staff'])) {
            // Guests can only modify their own reservations
            if ($reservation->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            
            // Guests can ONLY update the check_out date
            $restrictedKeys = ['status', 'payment_status', 'check_in', 'room_id', 'total_amount', 'user_id'];
            if ($request->hasAny($restrictedKeys)) {
                return response()->json(['message' => 'Guests are not authorized to modify booking statuses, room types, check-in dates, or payment details.'], 403);
            }
            
            // Stays can only be extended if the booking is currently active (confirmed or checked-in)
            if (!in_array($reservation->status, ['confirmed', 'checked-in'])) {
                return response()->json(['message' => 'Stays can only be extended for active (confirmed or checked-in) reservations.'], 422);
            }

            // Ensure they are actually extending (new checkout must be strictly after the current checkout)
            if ($request->has('check_out')) {
                $currentCheckOut = Carbon::parse($reservation->check_out);
                
                // Block stay extension if current checkout is in the past
                if (Carbon::now()->greaterThan($currentCheckOut)) {
                    return response()->json(['message' => 'Stays cannot be extended after the checkout date has passed.'], 422);
                }

                $newCheckOut = Carbon::parse($request->check_out);
                if ($newCheckOut->lessThanOrEqualTo($currentCheckOut)) {
                    return response()->json(['message' => 'The new checkout date must be after your current checkout date.'], 422);
                }
            }
        }

        // Rule: Completed and Cancelled reservations are not editable (History only)
        if (in_array($reservation->status, ['completed', 'cancelled'])) {
            return response()->json([
                'message' => "This reservation is already {$reservation->status} and cannot be modified."
            ], 422);
        }

        $isGuest = $user && !in_array($user->role, ['admin', 'staff']);

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|required|in:pending,confirmed,checked-in,cancelled,completed,cancellation_pending',
            'payment_status' => 'sometimes|required|in:unpaid,partially_paid,paid,refunded',
            'check_in' => 'sometimes|required|date',
            'check_out' => 'sometimes|required|date|after:check_in',
            'guest_name' => 'sometimes|nullable|string|max:255',
            'cancellation_reason' => 'sometimes|nullable|string|max:1000',
            'guests' => 'sometimes|required|integer|min:1',
            'payment_screenshot' => ($isGuest && $request->has('check_out') ? 'required' : 'nullable') . '|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'payment_screenshot.required' => 'Please upload a screenshot of your payment to confirm stay extension.',
            'payment_screenshot.image' => 'The payment proof must be a valid image file (JPG, PNG, or WEBP).',
        ]);

        $validator->sometimes('cancellation_reason', 'required|string|min:5|max:1000', function ($input) use ($reservation) {
            return $input->status === 'cancelled' && ($reservation->status === 'confirmed' || in_array($reservation->payment_status, ['paid', 'partially_paid']));
        });

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $newTotalAmount = null;
        $extraGuests = 0;
        $extraBedCharge = 0.00;
        $adults = (int) ($reservation->adults ?? $reservation->guests ?? 1);
        $children = (int) ($reservation->children ?? 0);
        $childrenAges = $reservation->children_ages ?? [];
        if (is_string($childrenAges)) {
            $childrenAges = json_decode($childrenAges, true) ?: [];
        }
        $totalGuests = max(1, $adults + $children);

        // If changing dates or guest count, check availability again and calculate new total price
        if ($request->has('check_in') || $request->has('check_out') || $request->has('guests')) {
            $checkIn = Carbon::parse($request->check_in ?? $reservation->check_in);
            $checkOut = Carbon::parse($request->check_out ?? $reservation->check_out);
            
            $isAvailable = !Reservation::where('room_id', $reservation->room_id)
                ->where('id', '!=', $id) // Exclude current reservation
                ->where(function ($query) use ($checkIn, $checkOut) {
                    $query->where('check_in', '<', $checkOut)
                          ->where('check_out', '>', $checkIn);
                })
                ->whereIn('status', ['pending', 'confirmed', 'checked-in'])
                ->exists();

            if (!$isAvailable) {
                return response()->json(['message' => 'The room is not available for the new dates.'], 422);
            }

            // Calculate new total amount based on new date duration and room type
            $room = Room::find($reservation->room_id);
            $days = (int) $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay());
            if ($days <= 0) $days = 1;
            
            $adults = (int) ($request->input('adults') ?? $reservation->adults ?? $request->input('guests') ?? $reservation->guests ?? 1);
            $children = (int) ($request->input('children') ?? $reservation->children ?? 0);
            $reqAges = $request->input('children_ages', $reservation->children_ages ?? []);
            if (is_string($reqAges)) {
                $childrenAges = json_decode($reqAges, true) ?: [];
            } else if (is_array($reqAges)) {
                $childrenAges = $reqAges;
            }

            $payingChildren = 0;
            if (!empty($childrenAges) && is_array($childrenAges)) {
                foreach ($childrenAges as $age) {
                    if ((int)$age >= 7) {
                        $payingChildren++;
                    }
                }
            }

            $chargeableGuests = max(1, $adults + $payingChildren);
            $totalGuests = max(1, $adults + $children);

            $maxAllowed = (int) $room->max_occupancy;
            if ($totalGuests > $maxAllowed) {
                return response()->json([
                    'message' => "Maximum capacity for {$room->room_type} is {$maxAllowed} guests."
                ], 422);
            }

            // Preserve the reservation's existing extra bedding count and charge on reschedules/extensions
            // so optional extra beds are not dropped or multiplied across additional nights
            $extraGuests = (int) ($reservation->extra_guests ?? 0);
            $extraBedCharge = (float) ($reservation->extra_bed_charge ?? 0.00);

            $isPerHead = in_array($room->room_type, ['Family Room', 'Barkadahan Room']);
            if ($isPerHead) {
                $headRate = ($room->is_promo && $room->promo_price_per_head > 0) ? (float) $room->promo_price_per_head : (float) $room->price_per_head;
                $baseAmount = $headRate * $chargeableGuests * $days;
            } else {
                $nightRate = ($room->is_promo && $room->promo_price > 0) ? (float) $room->promo_price : (float) $room->price_per_night;
                $baseAmount = $nightRate * $days;
            }

            $newTotalAmount = $baseAmount + $extraBedCharge;
        }

        $oldCheckout = $reservation->check_out;
        $additionalAmount = 0;
        if ($newTotalAmount !== null && $newTotalAmount > $reservation->total_amount) {
            $additionalAmount = $newTotalAmount - $reservation->total_amount;
        }

        $paymentScreenshotPath = null;
        if ($request->hasFile('payment_screenshot')) {
            $paymentScreenshotPath = $request->file('payment_screenshot')->store('payment_screenshots', 'public');
        }

        return DB::transaction(function () use (
            $request, 
            $reservation, 
            $newTotalAmount, 
            $oldCheckout, 
            $additionalAmount, 
            $isGuest, 
            $extraGuests, 
            $extraBedCharge,
            $totalGuests,
            $adults,
            $children,
            $childrenAges,
            $paymentScreenshotPath
        ) {
            $oldStatus = $reservation->status;
            $oldPaymentStatus = $reservation->payment_status;
            
            $updateData = $request->except(['payment_screenshot', '_method']);
            if ($paymentScreenshotPath) {
                $updateData['payment_screenshot'] = $paymentScreenshotPath;
            }
            if ($request->filled('check_in')) {
                $cIn = Carbon::parse($request->check_in);
                if (strlen(trim($request->check_in)) <= 10 || $cIn->format('H:i:s') === '00:00:00') {
                    $cIn->setTime(13, 0, 0);
                }
                $updateData['check_in'] = $cIn->format('Y-m-d H:i:s');
            }
            if ($request->filled('check_out')) {
                $cOut = Carbon::parse($request->check_out);
                if (strlen(trim($request->check_out)) <= 10 || $cOut->format('H:i:s') === '00:00:00') {
                    $cOut->setTime(11, 0, 0);
                }
                $updateData['check_out'] = $cOut->format('Y-m-d H:i:s');
            }
            if ($newTotalAmount !== null) {
                $updateData['guests'] = $totalGuests;
                $updateData['adults'] = $adults;
                $updateData['children'] = $children;
                $updateData['children_ages'] = $childrenAges;
                $updateData['total_amount'] = $newTotalAmount;
                $updateData['extra_guests'] = $extraGuests ?? 0;
                $updateData['extra_bed_charge'] = $extraBedCharge ?? 0.00;
                
                // If extension increases price, reset payment status to unpaid/partially_paid for the additional nights
                if ($newTotalAmount > $reservation->total_amount) {
                    $updateData['payment_status'] = ($oldPaymentStatus === 'paid') ? 'partially_paid' : 'unpaid';
                }
            }

            $reservation->update($updateData);

            // Notify Admins & Staff when a stay is extended
            if ($request->has('check_out')) {
                $newCheckOut = Carbon::parse($reservation->check_out);
                $originalCheckOut = Carbon::parse($oldCheckout);
                if ($newCheckOut->greaterThan($originalCheckOut)) {
                    $reservation->load(['room', 'user']);
                    $adminsAndStaff = \App\Models\User::whereIn('role', ['admin', 'staff']);
                    if ($request->user()) {
                        $adminsAndStaff->where('id', '!=', $request->user()->id);
                    }
                    Notification::send($adminsAndStaff->get(), new \App\Notifications\StayExtendedForAdmin($reservation, $oldCheckout));
                }
            }

            // Auto-refund if cancelled from paid/partially_paid
            if ($reservation->status === 'cancelled' && $oldStatus !== 'cancelled') {
                if (in_array($reservation->payment_status, ['paid', 'partially_paid'])) {
                    $reservation->update(['payment_status' => 'refunded']);
                    Payment::where('reservation_id', $reservation->id)
                        ->where('status', 'Succeeded')
                        ->update(['status' => 'Refunded']);
                }
            }

            // Auto-confirm if paid
            if ($reservation->payment_status === 'paid' && $reservation->status === 'pending') {
                $reservation->update(['status' => 'confirmed']);
                // Notify user
                $reservation->load(['room', 'user']);
                if ($reservation->user) {
                    $reservation->user->notify(new \App\Notifications\BookingConfirmed($reservation));
                }
            }

            $defaultPaymentMethod = $reservation->payment_screenshot ? 'QR Payment' : 'Cash/Manual';

            // Transition from unpaid to partially_paid (automatically record downpayment in payments)
            if ($reservation->payment_status === 'partially_paid' && $oldPaymentStatus === 'unpaid') {
                $paymentExists = Payment::where('reservation_id', $reservation->id)->exists();
                if (!$paymentExists) {
                    $downpayment = $reservation->downpayment_amount ?: ($reservation->total_amount / 2);
                    Payment::create([
                        'reservation_id' => $reservation->id,
                        'paymongo_payment_id' => 'MANUAL-DP-' . strtoupper(Str::random(10)),
                        'amount' => $downpayment,
                        'method' => $defaultPaymentMethod,
                        'status' => 'Succeeded',
                    ]);
                }
            }

            // Transition from unpaid/partially_paid to paid (automatically record remaining balance in payments)
            if ($reservation->payment_status === 'paid' && in_array($oldPaymentStatus, ['unpaid', 'partially_paid'])) {
                $totalPaid = Payment::where('reservation_id', $reservation->id)
                    ->where('status', 'Succeeded')
                    ->sum('amount');
                
                $remainingBalance = $reservation->total_amount - $totalPaid;
                
                if ($remainingBalance > 0) {
                    Payment::create([
                        'reservation_id' => $reservation->id,
                        'paymongo_payment_id' => 'MANUAL-BAL-' . strtoupper(Str::random(10)),
                        'amount' => $remainingBalance,
                        'method' => $defaultPaymentMethod,
                        'status' => 'Succeeded',
                    ]);
                }
            }

            // Senior logic: If status changed to completed, ensure full payment record exists
            if ($reservation->status === 'completed' && $oldStatus !== 'completed') {
                // Update payment status as well
                $reservation->update(['payment_status' => 'paid']);

                // Check if a payment record already exists (e.g. from Xendit) and record remaining balance
                $totalPaid = Payment::where('reservation_id', $reservation->id)
                    ->where('status', 'Succeeded')
                    ->sum('amount');

                $remainingBalance = $reservation->total_amount - $totalPaid;

                if ($remainingBalance > 0) {
                    Payment::create([
                        'reservation_id' => $reservation->id,
                        'paymongo_payment_id' => 'MANUAL-' . strtoupper(Str::random(10)), // Reusing column for manual payments
                        'amount' => $remainingBalance,
                        'method' => $defaultPaymentMethod,
                        'status' => 'Succeeded',
                    ]);
                }
            }

            // Auto-create payment entry if status changed to confirmed (e.g. manual confirmation)
            if ($reservation->status === 'confirmed' && $oldStatus !== 'confirmed') {
                // Only create if a payment record doesn't already exist
                $paymentExists = Payment::where('reservation_id', $reservation->id)->exists();
                if (!$paymentExists) {
                    Payment::create([
                        'reservation_id' => $reservation->id,
                        'paymongo_payment_id' => 'MANUAL-' . strtoupper(Str::random(10)),
                        'amount' => $reservation->total_amount,
                        'method' => $defaultPaymentMethod,
                        'status' => 'Succeeded',
                    ]);
                    // Also ensure payment_status is set to paid
                    $reservation->update(['payment_status' => 'paid']);
                }
            }

            // Notify user if status changed
            if ($oldStatus !== $reservation->status) {
                $reservation->load(['room', 'user']);
                if ($reservation->user) {
                    if ($reservation->status === 'confirmed') {
                        if ($oldStatus === 'cancellation_pending') {
                            $reservation->update(['cancellation_reason' => null]);
                            $reservation->user->notify(new \App\Notifications\CancellationRejectedForGuest($reservation));
                        } else {
                            $reservation->user->notify(new \App\Notifications\BookingConfirmed($reservation));
                        }
                    } else if ($reservation->status === 'cancelled') {
                        $reservation->user->notify(new \App\Notifications\BookingCancelled($reservation));
                    }
                }
            }

            // Record audit log for reservation update / status change
            try {
                $auditAction = null;
                if ($oldStatus !== $reservation->status) {
                    $auditAction = match ($reservation->status) {
                        'checked-in' => 'check_in',
                        'completed' => 'check_out',
                        'confirmed' => 'booking_confirmed',
                        'cancelled' => 'booking_cancelled',
                        default => 'booking_updated',
                    };
                } elseif ($oldPaymentStatus !== $reservation->payment_status) {
                    $auditAction = 'payment_updated';
                } elseif ($request->hasAny(['check_in', 'check_out', 'guests', 'adults', 'children', 'room_id'])) {
                    $auditAction = 'booking_updated';
                }

                if ($auditAction) {
                    AuditLog::recordBookingActivity(
                        $reservation,
                        $auditAction,
                        $request->user(),
                        null,
                        [
                            'status' => $oldStatus,
                            'payment_status' => $oldPaymentStatus,
                            'check_out' => $oldCheckout,
                        ],
                        [
                            'status' => $reservation->status,
                            'payment_status' => $reservation->payment_status,
                            'check_in' => $reservation->check_in,
                            'check_out' => $reservation->check_out,
                            'total_amount' => $reservation->total_amount,
                        ],
                        $request
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('AuditLog (update) failed: ' . $e->getMessage());
            }

            return response()->json($reservation->toArray());
        });
    }

    /**
     * Cancel the specified reservation.
     */
    public function cancel(Request $request, string $id)
    {
        $user = $request->user();
        $isGuest = $user && !in_array($user->role, ['admin', 'staff']);

        $query = Reservation::where('id', $id);
        if ($isGuest) {
            $query->where('user_id', $user->id);
        }
        $reservation = $query->first();

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found.'], 404);
        }

        // ❌ BLOCK: If guest is suspended
        if ($user && $user->is_suspended) {
            return response()->json([
                'message' => 'Your account is suspended. You cannot cancel reservations. Please contact support.',
                'is_suspended' => true
            ], 403);
        }

        if ($reservation->status === 'cancelled') {
            return response()->json(['message' => 'Reservation is already cancelled.'], 400);
        }

        if (in_array($reservation->status, ['checked-in', 'completed'])) {
            return response()->json(['message' => 'Cannot cancel after check-in or completion. Please contact administration.'], 400);
        }

        // Stay has already passed
        $checkOut = Carbon::parse($reservation->check_out);
        if ($checkOut->isPast()) {
            return response()->json(['message' => 'Cannot cancel a reservation whose stay period has already passed.'], 400);
        }

        // ❌ BLOCK: 1-week free cancellation window (7 days from booking creation)
        $createdAt = Carbon::parse($reservation->created_at);
        if ($isGuest && $createdAt->addDays(7)->isPast()) {
            return response()->json([
                'message' => 'The 1-week free cancellation window has expired. Reservations can only be cancelled within 7 days of booking.'
            ], 400);
        }

        $reason = $request->input('reason') ?: 'Cancelled within 1-week free cancellation window (100% refund)';
        $oldPaymentStatus = $reservation->payment_status;
        $willRefund = in_array($oldPaymentStatus, ['paid', 'partially_paid']);

        $reservation->update([
            'status' => 'cancelled',
            'payment_status' => $willRefund ? 'refunded' : $oldPaymentStatus,
            'cancellation_reason' => $reason
        ]);

        if ($willRefund) {
            Payment::where('reservation_id', $reservation->id)
                ->where('status', 'Succeeded')
                ->update(['status' => 'Refunded']);
        }

        $reservation->load(['room', 'user']);

        // Record audit log for booking cancellation
        try {
            AuditLog::recordBookingActivity(
                $reservation,
                'booking_cancelled',
                $request->user(),
                null,
                ['payment_status' => $oldPaymentStatus],
                [
                    'status' => $reservation->status,
                    'payment_status' => $reservation->payment_status,
                    'cancellation_reason' => $reason,
                ],
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AuditLog (cancel) failed: ' . $e->getMessage());
        }

        // Notify guest
        if ($reservation->user) {
            $reservation->user->notify(new \App\Notifications\BookingCancelled($reservation));
        }

        // Notify admins and staff
        $admins = \App\Models\User::whereIn('role', ['admin', 'staff'])->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new \App\Notifications\BookingCancelled($reservation));
        }

        return response()->json([
            'message' => $willRefund 
                ? 'Your reservation has been cancelled and 100% refunded.' 
                : 'Your reservation has been cancelled successfully.',
            'reservation' => $reservation
        ]);
    }

    /**
     * Reschedule an existing reservation to different dates.
     */
    public function reschedule(Request $request, string $id)
    {
        $user = $request->user();
        $isGuest = $user && !in_array($user->role, ['admin', 'staff']);

        $query = Reservation::with(['room', 'user'])->where('id', $id);
        if ($isGuest) {
            $query->where('user_id', $user->id);
        }
        $reservation = $query->first();

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found.'], 404);
        }

        // ❌ BLOCK: If guest is suspended
        if ($user && $user->is_suspended && !in_array($user->role, ['admin', 'staff'])) {
            return response()->json([
                'message' => 'Your account is suspended. You cannot modify reservations.',
                'is_suspended' => true
            ], 403);
        }

        // Can only reschedule active bookings (pending or confirmed)
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => "Only pending or confirmed bookings can be rescheduled. Current status: {$reservation->status}."
            ], 422);
        }

        // Check if original check-in has already passed
        $currentCheckIn = Carbon::parse($reservation->check_in);
        if (Carbon::now()->greaterThan($currentCheckIn->copy()->endOfDay())) {
            return response()->json([
                'message' => 'Reservations cannot be rescheduled after the original check-in date has passed.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $newCheckIn = Carbon::parse($request->check_in);
        if (strlen(trim($request->check_in)) <= 10 || $newCheckIn->format('H:i:s') === '00:00:00') {
            $newCheckIn->setTime(13, 0, 0);
        }

        $newCheckOut = Carbon::parse($request->check_out);
        if (strlen(trim($request->check_out)) <= 10 || $newCheckOut->format('H:i:s') === '00:00:00') {
            $newCheckOut->setTime(11, 0, 0);
        }

        // Check room availability for the new dates (excluding this reservation)
        $isAvailable = !Reservation::where('room_id', $reservation->room_id)
            ->where('id', '!=', $reservation->id)
            ->where(function ($query) use ($newCheckIn, $newCheckOut) {
                $query->where('check_in', '<', $newCheckOut)
                      ->where('check_out', '>', $newCheckIn);
            })
            ->whereIn('status', ['pending', 'confirmed', 'checked-in'])
            ->exists();

        if (!$isAvailable) {
            return response()->json(['message' => 'The room is not available for the selected dates.'], 422);
        }

        // Check blocked dates (global or room-specific)
        $isBlocked = \App\Models\BlockedDate::where(function ($q) use ($reservation) {
                $q->where('room_id', $reservation->room_id)
                  ->orWhereNull('room_id');
            })
            ->where('start_date', '<', $newCheckOut)
            ->where('end_date', '>', $newCheckIn)
            ->exists();

        if ($isBlocked) {
            return response()->json(['message' => 'The selected dates are currently blocked and unavailable for booking.'], 422);
        }

        // Calculate new total amount
        $room = $reservation->room ?: Room::find($reservation->room_id);
        $days = (int) $newCheckIn->copy()->startOfDay()->diffInDays($newCheckOut->copy()->startOfDay());
        if ($days <= 0) $days = 1;

        $adults = (int) ($reservation->adults ?? $reservation->guests ?? 1);
        $children = (int) ($reservation->children ?? 0);
        $childrenAges = $reservation->children_ages ?? [];
        if (is_string($childrenAges)) {
            $childrenAges = json_decode($childrenAges, true) ?: [];
        }

        $payingChildren = 0;
        if (!empty($childrenAges) && is_array($childrenAges)) {
            foreach ($childrenAges as $age) {
                if ((int)$age >= 7) {
                    $payingChildren++;
                }
            }
        }

        $chargeableGuests = max(1, $adults + $payingChildren);
        $totalGuests = max(1, $adults + $children);

        // Preserve the reservation's existing extra bedding count and charge on reschedule
        // so optional extra beds are not dropped or multiplied across additional nights
        $extraGuests = (int) ($reservation->extra_guests ?? 0);
        $extraBedCharge = (float) ($reservation->extra_bed_charge ?? 0.00);

        $isPerHead = in_array($room->room_type, ['Family Room', 'Barkadahan Room']);
        if ($isPerHead) {
            $headRate = ($room->is_promo && $room->promo_price_per_head > 0) ? (float) $room->promo_price_per_head : (float) $room->price_per_head;
            $baseAmount = $headRate * $chargeableGuests * $days;
        } else {
            $nightRate = ($room->is_promo && $room->promo_price > 0) ? (float) $room->promo_price : (float) $room->price_per_night;
            $baseAmount = $nightRate * $days;
        }

        $newTotalAmount = $baseAmount + $extraBedCharge;
        $oldTotalAmount = (float) $reservation->total_amount;
        $additionalAmount = max(0, $newTotalAmount - $oldTotalAmount);

        // Check total amount already successfully paid
        $totalPaid = Payment::where('reservation_id', $reservation->id)
            ->where('status', 'Succeeded')
            ->sum('amount');

        $remainingBalance = max(0, $newTotalAmount - $totalPaid);
        $oldCheckInStr = $reservation->check_in;
        $oldCheckOutStr = $reservation->check_out;

        return DB::transaction(function () use (
            $reservation, 
            $newCheckIn, 
            $newCheckOut, 
            $newTotalAmount, 
            $additionalAmount, 
            $remainingBalance, 
            $isGuest, 
            $oldCheckInStr, 
            $oldCheckOutStr, 
            $extraGuests, 
            $extraBedCharge,
            $room
        ) {
            $oldPaymentStatus = $reservation->payment_status;

            $updateData = [
                'check_in' => $newCheckIn->format('Y-m-d H:i:s'),
                'check_out' => $newCheckOut->format('Y-m-d H:i:s'),
                'total_amount' => max($reservation->total_amount, $newTotalAmount),
                'extra_guests' => $extraGuests,
                'extra_bed_charge' => $extraBedCharge,
            ];

            // If additional days increase total amount, update payment status
            if ($additionalAmount > 0) {
                $updateData['payment_status'] = ($oldPaymentStatus === 'paid') ? 'partially_paid' : 'unpaid';
            }

            $reservation->update($updateData);

            // Notifications
            $adminsAndStaff = \App\Models\User::whereIn('role', ['admin', 'staff'])->get();
            if ($adminsAndStaff->isNotEmpty()) {
                Notification::send($adminsAndStaff, new \App\Notifications\BookingRescheduledForAdmin($reservation, $oldCheckInStr, $oldCheckOutStr));
            }

            // Record audit log for booking reschedule
            try {
                AuditLog::recordBookingActivity(
                    $reservation,
                    'booking_rescheduled',
                    request()->user(),
                    null,
                    [
                        'check_in' => $oldCheckInStr,
                        'check_out' => $oldCheckOutStr,
                    ],
                    [
                        'check_in' => $reservation->check_in,
                        'check_out' => $reservation->check_out,
                        'total_amount' => $reservation->total_amount,
                    ],
                    request()
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('AuditLog (reschedule) failed: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Reservation rescheduled successfully.' . ($additionalAmount > 0 ? ' Please settle the remaining balance at the apartelle.' : ''),
                'reservation' => $reservation->fresh(['room', 'user', 'review', 'package']),
                'additional_amount' => $additionalAmount,
                'remaining_balance' => $remainingBalance,
            ]);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $reservation = Reservation::with(['room', 'user'])->find($id);

        if (!$reservation) {
            return response()->json(['message' => 'Reservation not found'], 404);
        }

        try {
            AuditLog::recordBookingActivity(
                $reservation,
                'booking_deleted',
                $request->user(),
                null,
                [
                    'status' => $reservation->status,
                    'payment_status' => $reservation->payment_status,
                    'total_amount' => $reservation->total_amount,
                ],
                null,
                $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AuditLog (destroy) failed: ' . $e->getMessage());
        }

        $reservation->delete();

        return response()->json(['message' => 'Reservation deleted successfully']);
    }

    /**
     * Sync payment status with Xendit (useful for localhost or failed webhooks)
     */
    public function syncPayment($id)
    {
        $reservation = Reservation::findOrFail($id);
        
        if (!$reservation->xendit_invoice_id) {
            return response()->json(['message' => 'No Xendit invoice associated with this reservation.'], 400);
        }

        $invoice = $this->xenditService->getInvoice($reservation->xendit_invoice_id);

        if (!$invoice) {
            return response()->json(['message' => 'Could not fetch invoice from Xendit.'], 500);
        }

        $status = $invoice['status'] ?? 'PENDING';
        
        if ($status === 'PAID' || $status === 'SETTLED') {
            $paymentId = $invoice['id'];
            $externalId = $invoice['external_id'] ?? '';
            $isAdditional = str_contains($externalId, '-ext-') || str_contains($externalId, '-resched-');

            // 1. Determine payment amount
            $paidAmount = $invoice['paid_amount'] ?? $invoice['amount'] ?? 0;
            if ($paidAmount == 0) {
                $paidAmount = ($reservation->payment_option === 'half')
                    ? ($reservation->downpayment_amount ?: ($reservation->total_amount / 2))
                    : $reservation->total_amount;
            }

            // 2. Record the payment
            // Check if payment already recorded
            $exists = Payment::where('reservation_id', $reservation->id)
                ->where('paymongo_payment_id', $paymentId)
                ->first();

            if (!$exists) {
                // Check for MANUAL- payment only if NOT a stay extension or reschedule
                $manualPayment = null;
                if (!$isAdditional) {
                    $manualPayment = Payment::where('reservation_id', $reservation->id)
                        ->where('paymongo_payment_id', 'LIKE', 'MANUAL-%')
                        ->where('status', 'Succeeded')
                        ->first();
                }

                if ($manualPayment) {
                    $manualPayment->update([
                        'paymongo_payment_id' => $paymentId,
                        'amount' => $paidAmount,
                        'method' => $invoice['payment_channel'] ?? ($invoice['payment_method'] ?? 'Xendit'),
                    ]);
                } else {
                    Payment::create([
                        'reservation_id' => $reservation->id,
                        'paymongo_payment_id' => $paymentId,
                        'amount' => $paidAmount,
                        'method' => $invoice['payment_channel'] ?? ($invoice['payment_method'] ?? 'Xendit'),
                        'status' => 'Succeeded',
                    ]);
                }
            }

            // 3. Recalculate payment status dynamically based on total paid amount
            $totalPaid = Payment::where('reservation_id', $reservation->id)
                ->where('status', 'Succeeded')
                ->sum('amount');

            if ($totalPaid >= $reservation->total_amount) {
                $paymentStatus = 'paid';
            } else if ($totalPaid > 0) {
                $paymentStatus = 'partially_paid';
            } else {
                $paymentStatus = 'unpaid';
            }

            // 4. Update reservation status safely
            $updateData = ['payment_status' => $paymentStatus];
            
            // Only transition status to confirmed if it is currently pending
            if ($reservation->status === 'pending') {
                $updateData['status'] = 'confirmed';
            }

            $reservation->update($updateData);

            return response()->json([
                'message' => 'Payment synchronized and confirmed.',
                'status' => $reservation->status,
                'payment_status' => $paymentStatus
            ]);
        }

        return response()->json([
            'message' => 'Invoice status is: ' . $status,
            'status' => $reservation->status
        ]);
    }
}

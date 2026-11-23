<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'actor_name',
        'actor_email',
        'actor_role',
        'action',
        'entity_type',
        'entity_id',
        'reservation_id',
        'room_id',
        'room_number',
        'room_type',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guests_count',
        'adults_count',
        'children_count',
        'check_in',
        'check_out',
        'total_amount',
        'payment_status',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'total_amount' => 'decimal:2',
        'guests_count' => 'integer',
        'adults_count' => 'integer',
        'children_count' => 'integer',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Record a reservation/booking activity into the audit log.
     */
    public static function recordBookingActivity(
        Reservation $reservation,
        string $action,
        ?User $actor = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null
    ): self {
        $request = $request ?? request();
        $actor = $actor ?? ($request ? $request->user() : null);

        $reservation->loadMissing(['room', 'user']);

        $room = $reservation->room;
        $guestUser = $reservation->user;

        $guestName = $reservation->guest_name
            ?: ($guestUser ? ($guestUser->name ?: trim(($guestUser->first_name ?? '') . ' ' . ($guestUser->last_name ?? ''))) : 'Guest');
        $guestEmail = $guestUser?->email;
        $guestPhone = $guestUser?->phone;

        $actorName = $actor
            ? ($actor->name ?: trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')))
            : $guestName;
        $actorEmail = $actor?->email ?? $guestEmail;
        $rawRole = strtolower((string) ($actor?->role ?? 'guest'));
        $actorRole = in_array($rawRole, ['admin', 'staff', 'guest'], true) ? $rawRole : 'guest';

        $guestsCount = max(1, (int) ($reservation->guests ?? 1));
        $adultsCount = max(1, (int) ($reservation->adults ?? $guestsCount));
        $childrenCount = max(0, (int) ($reservation->children ?? 0));

        if (!$description) {
            $roomLabel = $room
                ? "Room #{$room->room_number} ({$room->room_type})"
                : "Room #{$reservation->room_id}";
            $roleLabel = ucfirst($actorRole);

            $description = match ($action) {
                'booking_created' => "Booking registered for {$guestName} in {$roomLabel} for {$guestsCount} guest(s) by {$roleLabel} ({$actorName}).",
                'booking_confirmed' => "Booking #{$reservation->id} for {$guestName} in {$roomLabel} was confirmed by {$roleLabel} ({$actorName}).",
                'check_in' => "Guest {$guestName} checked in to {$roomLabel} (processed by {$roleLabel} {$actorName}).",
                'check_out' => "Guest {$guestName} checked out of {$roomLabel}. Stay completed (processed by {$roleLabel} {$actorName}).",
                'booking_cancelled' => "Booking #{$reservation->id} for {$guestName} in {$roomLabel} was cancelled by {$roleLabel} ({$actorName}).",
                'booking_rescheduled' => "Booking #{$reservation->id} for {$guestName} in {$roomLabel} was rescheduled by {$roleLabel} ({$actorName}).",
                'payment_updated' => "Payment status for Booking #{$reservation->id} ({$guestName}) updated to {$reservation->payment_status} by {$roleLabel} ({$actorName}).",
                'booking_deleted' => "Booking #{$reservation->id} for {$guestName} in {$roomLabel} was deleted by {$roleLabel} ({$actorName}).",
                default => "Booking #{$reservation->id} for {$guestName} in {$roomLabel} was updated by {$roleLabel} ({$actorName}).",
            };
        }

        return static::create([
            'user_id' => $actor?->id,
            'actor_name' => $actorName,
            'actor_email' => $actorEmail,
            'actor_role' => $actorRole,
            'action' => $action,
            'entity_type' => 'reservation',
            'entity_id' => $reservation->id,
            'reservation_id' => $action === 'booking_deleted' ? null : $reservation->id,
            'room_id' => $reservation->room_id,
            'room_number' => $room?->room_number,
            'room_type' => $room?->room_type,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'guest_phone' => $guestPhone,
            'guests_count' => $guestsCount,
            'adults_count' => $adultsCount,
            'children_count' => $childrenCount,
            'check_in' => $reservation->check_in,
            'check_out' => $reservation->check_out,
            'total_amount' => $reservation->total_amount,
            'payment_status' => $reservation->payment_status,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}

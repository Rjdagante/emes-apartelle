<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingRescheduledForAdmin extends Notification
{
    use Queueable;

    protected $reservation;
    protected $oldCheckIn;
    protected $oldCheckOut;

    /**
     * Create a new notification instance.
     */
    public function __construct($reservation, $oldCheckIn, $oldCheckOut)
    {
        $this->reservation = $reservation;
        $this->oldCheckIn = $oldCheckIn;
        $this->oldCheckOut = $oldCheckOut;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $guestName = $this->reservation->guest_name ?: ($this->reservation->user->name ?? 'Guest');
        $roomNumber = $this->reservation->room->room_number ?? '';
        $newDates = date('M d', strtotime($this->reservation->check_in)) . ' - ' . date('M d, Y', strtotime($this->reservation->check_out));
        $oldDates = date('M d', strtotime($this->oldCheckIn)) . ' - ' . date('M d, Y', strtotime($this->oldCheckOut));
        $hasProof = !empty($this->reservation->payment_screenshot);

        return [
            'type' => 'booking_rescheduled',
            'icon' => $hasProof ? 'bi-receipt-cutoff' : 'bi-calendar-range-fill',
            'title' => $hasProof ? 'Booking Rescheduled & Payment Proof!' : 'Booking Rescheduled',
            'message' => $hasProof
                ? "Guest {$guestName} rescheduled Room #{$roomNumber} from ({$oldDates}) to ({$newDates}) and uploaded a payment screenshot for verification."
                : "Guest {$guestName} rescheduled Room #{$roomNumber} from ({$oldDates}) to ({$newDates}).",
            'action_url' => '/admin/reservations',
            'action_text' => $hasProof ? 'Verify Payment' : 'View Reservations',
            'reservation_id' => $this->reservation->id,
            'payment_screenshot_url' => $this->reservation->payment_screenshot_url,
        ];
    }
}

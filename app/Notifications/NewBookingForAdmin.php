<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewBookingForAdmin extends Notification
{
    use Queueable;

    protected $reservation;

    /**
     * Create a new notification instance.
     */
    public function __construct($reservation)
    {
        $this->reservation = $reservation;
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
        $hasProof = !empty($this->reservation->payment_screenshot);

        return [
            'type' => 'reservation', // Custom type to distinguish for SweetAlert
            'icon' => $hasProof ? 'bi-receipt-cutoff' : 'bi-calendar-check-fill',
            'title' => $hasProof ? 'New Booking & Payment Proof!' : 'New Reservation!',
            'message' => $hasProof
                ? "Guest {$guestName} booked Room #{$this->reservation->room->room_number} for {$this->reservation->check_in} and uploaded a payment screenshot for verification."
                : "Guest {$guestName} booked Room #{$this->reservation->room->room_number} for {$this->reservation->check_in}.",
            'action_url' => '/admin/reservations',
            'action_text' => $hasProof ? 'Verify Payment' : 'View Details',
            'reservation_id' => $this->reservation->id,
            'payment_screenshot_url' => $this->reservation->payment_screenshot_url,
        ];
    }
}

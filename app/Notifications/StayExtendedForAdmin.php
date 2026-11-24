<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StayExtendedForAdmin extends Notification
{
    use Queueable;

    protected $reservation;
    protected $oldCheckout;

    /**
     * Create a new notification instance.
     */
    public function __construct($reservation, $oldCheckout)
    {
        $this->reservation = $reservation;
        $this->oldCheckout = $oldCheckout;
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
        $hasProof = !empty($this->reservation->payment_screenshot);

        return [
            'type' => 'stay_extended',
            'icon' => $hasProof ? 'bi-receipt-cutoff' : 'bi-calendar-plus-fill',
            'title' => $hasProof ? 'Stay Extended & Payment Proof!' : 'Stay Extended!',
            'message' => $hasProof
                ? "Guest {$guestName} extended stay in Room #{$roomNumber} until " . date('M d, Y', strtotime($this->reservation->check_out)) . " and uploaded a payment screenshot for verification."
                : "Guest {$guestName} extended stay in Room #{$roomNumber} until " . date('M d, Y', strtotime($this->reservation->check_out)) . ".",
            'action_url' => '/admin/reservations',
            'action_text' => $hasProof ? 'Verify Payment' : 'View Reservations',
            'reservation_id' => $this->reservation->id,
            'payment_screenshot_url' => $this->reservation->payment_screenshot_url,
        ];
    }
}

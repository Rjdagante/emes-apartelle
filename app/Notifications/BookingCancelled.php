<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingCancelled extends Notification
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
        $isAdmin = in_array($notifiable->role ?? '', ['admin', 'staff']);
        $guestName = $this->reservation->user->name ?? 'Guest';
        $roomInfo = ($this->reservation->room->room_type ?? 'Room') . ' #' . ($this->reservation->room->room_number ?? '');

        if ($isAdmin) {
            return [
                'type' => 'booking_cancelled',
                'icon' => 'bi bi-calendar-x-fill',
                'title' => 'Booking Cancelled & Refunded',
                'message' => "Reservation for {$roomInfo} by {$guestName} was cancelled (1-week free cancellation, 100% refund).",
                'action_url' => '/admin/reservations',
                'action_text' => 'View Reservations',
                'reservation_id' => $this->reservation->id
            ];
        }

        return [
            'type' => 'booking_cancelled',
            'icon' => 'bi bi-calendar-x-fill',
            'title' => 'Booking Cancelled',
            'message' => 'The reservation for ' . $roomInfo . ' has been cancelled.',
            'action_url' => '/my-bookings',
            'action_text' => 'View History',
            'reservation_id' => $this->reservation->id
        ];
    }
}

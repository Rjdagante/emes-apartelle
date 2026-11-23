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
        $guestName = $this->reservation->user->name ?? ($this->reservation->guest_name ?? 'Guest');
        $roomNumber = $this->reservation->room->room_number ?? '';
        $newDates = date('M d', strtotime($this->reservation->check_in)) . ' - ' . date('M d, Y', strtotime($this->reservation->check_out));
        $oldDates = date('M d', strtotime($this->oldCheckIn)) . ' - ' . date('M d, Y', strtotime($this->oldCheckOut));

        return [
            'type' => 'booking_rescheduled',
            'icon' => 'bi-calendar-range-fill',
            'title' => 'Booking Rescheduled',
            'message' => "Guest {$guestName} rescheduled Room #{$roomNumber} from ({$oldDates}) to ({$newDates}).",
            'action_url' => '/admin/reservations',
            'action_text' => 'View Reservations',
            'reservation_id' => $this->reservation->id
        ];
    }
}

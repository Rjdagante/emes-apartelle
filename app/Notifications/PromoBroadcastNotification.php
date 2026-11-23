<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PromoBroadcastNotification extends Notification
{
    use Queueable;

    public $subject;
    public $messageText;
    public $promoRooms;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $subject, string $messageText, array $promoRooms = [])
    {
        $this->subject = $subject;
        $this->messageText = $messageText;
        $this->promoRooms = $promoRooms;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = $notifiable->first_name ?: $notifiable->name ?: 'Valued Guest';

        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting("Hello {$recipientName}!")
            ->line($this->messageText);

        if (!empty($this->promoRooms)) {
            $mail->line('---');
            $mail->line('**Featured Special Offers:**');
            foreach ($this->promoRooms as $room) {
                $headOrNight = in_array($room['room_type'] ?? '', ['Family Room', 'Barkadahan Room']) ? '/ head / night' : '/ night';
                $rate = '₱' . number_format($room['effective_price'] ?? 0, 2) . ' ' . $headOrNight;
                $disc = !empty($room['promo_discount_percent']) ? "({$room['promo_discount_percent']}% OFF)" : '';
                $mail->line("• **Room #{$room['room_number']} ({$room['room_type']})**: {$rate} {$disc}");
            }
            $mail->line('---');
        }

        return $mail
            ->action('View Rooms & Book Now', url('/rooms'))
            ->line('Take advantage of these exclusive rates while rooms are available.')
            ->salutation("Warm regards,\nEME's Apartelle Management");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'promo',
            'icon' => 'bi bi-tag-fill',
            'title' => $this->subject,
            'message' => $this->messageText,
            'action_url' => '/rooms',
            'action_text' => 'View Deals'
        ];
    }
}


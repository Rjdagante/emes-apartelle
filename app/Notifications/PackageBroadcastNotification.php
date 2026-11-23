<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PackageBroadcastNotification extends Notification
{
    use Queueable;

    public $subject;
    public $messageText;
    public $packages;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $subject, string $messageText, array $packages = [])
    {
        $this->subject = $subject;
        $this->messageText = $messageText;
        $this->packages = $packages;
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

        if (!empty($this->packages)) {
            $mail->line('---');
            $mail->line('**Featured Room Packages:**');
            foreach ($this->packages as $pkg) {
                $price = '₱' . number_format((float) ($pkg['price'] ?? 0), 2);
                $details = array_filter([
                    $pkg['duration'] ?? null,
                    $pkg['pax'] ?? null,
                    !empty($pkg['room_number']) ? "Room #{$pkg['room_number']}" : ($pkg['room_type_label'] ?? null),
                ]);
                $meta = !empty($details) ? ' (' . implode(' • ', $details) . ')' : '';
                $mail->line("• **{$pkg['name']}**{$meta} — {$price}");
            }
            $mail->line('---');
        }

        return $mail
            ->action('View Packages & Book Now', url('/rooms'))
            ->line('Book your preferred room package today while slots are available.')
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
            'type' => 'package',
            'icon' => 'bi bi-box2-heart-fill',
            'title' => $this->subject,
            'message' => $this->messageText,
            'action_url' => '/rooms',
            'action_text' => 'View Packages',
        ];
    }
}

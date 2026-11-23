<?php

namespace App\Providers;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $logoPath = public_path('images/emes-email-logo.jpg');
            $htmlBody = $event->message->getHtmlBody();

            if (is_string($htmlBody) && str_contains($htmlBody, 'cid:emes-email-logo.jpg') && file_exists($logoPath)) {
                $event->message->embedFromPath($logoPath, 'emes-email-logo.jpg', 'image/jpeg');
            }
        });
    }
}

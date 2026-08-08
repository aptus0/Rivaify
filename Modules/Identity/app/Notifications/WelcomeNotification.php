<?php

namespace Modules\Identity\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Rivaify\'a hoş geldin!')
            ->greeting("Merhaba {$notifiable->name},")
            ->line('Rivaify ailesine katıldığın için teşekkürler! Artık mağazanı kurup ürünlerini satışa sunmaya bir adım daha yakınsın.')
            ->line('Sıradaki adım: mağazanı oluştur, işletme ve vergi bilgilerini tamamla, belgelerini yükle — birkaç dakika sürer.')
            ->action('Mağazamı Kur', rtrim(config('app.frontend_url'), '/').'/onboarding')
            ->line('Bir sorunla karşılaşırsan bize her zaman ulaşabilirsin.');
    }
}

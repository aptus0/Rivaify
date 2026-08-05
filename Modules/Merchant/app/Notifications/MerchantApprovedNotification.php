<?php

namespace Modules\Merchant\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Store\Models\Store;

class MerchantApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Store $store) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->store->name} onaylandı")
            ->greeting('Tebrikler!')
            ->line("\"{$this->store->name}\" mağazanız incelendi ve onaylandı.")
            ->line('Artık mağaza panelinize giriş yapıp satışa başlayabilirsiniz.')
            ->action('Mağaza Panelime Git', config('app.frontend_url', config('app.url')).'/dashboard');
    }
}

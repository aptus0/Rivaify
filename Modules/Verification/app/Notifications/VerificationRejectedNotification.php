<?php

namespace Modules\Verification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Verification\Models\VerificationRequest;

class VerificationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly VerificationRequest $verificationRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Doğrulama başvurunuzda eksik bilgi var')
            ->line('Mağaza doğrulama başvurunuzu inceledik ve bazı düzeltmeler gerekiyor.')
            ->line($this->verificationRequest->rejection_reason ?? 'Detaylar için panelinizi kontrol edin.')
            ->action('Başvuruyu Güncelle', config('app.frontend_url').'/onboarding/documents');
    }
}

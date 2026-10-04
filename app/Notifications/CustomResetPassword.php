<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomResetPassword extends Notification
{
    use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // Create reset password link
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('S2P: Permohonan Tukar Kata Laluan')
            ->greeting('Hai '.($notifiable->nama ?? 'Pengguna').'!')
            ->line('Kami menerima permohonan untuk menukar kata laluan akaun S2P anda.')
            ->action('Tukar Kata Laluan', $url)
            ->line('Pautan ini akan luput dalam masa 60 minit sahaja.')
            ->line('Jika anda tidak meminta pertukaran ini, sila abaikan e-mel ini.');
    }
}

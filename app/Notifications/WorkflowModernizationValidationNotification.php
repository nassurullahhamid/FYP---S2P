<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowModernizationValidationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tiket $ticket,
        public string $submittedByName,
        public ?string $subCategory = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $taskDescription =
            $this->subCategory !== null
            && trim($this->subCategory) !== ''
                ? $this->subCategory
                : $this->ticket->kategori;

        return [
            'id_tiket' => $this->ticket->id_tiket,
            'tajuk' => 'Validasi LKK Pemodenan Diperlukan',
            'pesanan' => sprintf(
                'LKK tiket %s (%s) telah diverifikasi oleh KUPP %s dan memerlukan validasi Ketua Wilayah.',
                $this->ticket->id_tiket,
                $taskDescription,
                $this->submittedByName
            ),
            'icon' => 'ShieldCheck',
            'url' => route(
                'tickets.show',
                [
                    'id_tiket' => $this->ticket->id_tiket,
                ]
            ),
        ];
    }
}

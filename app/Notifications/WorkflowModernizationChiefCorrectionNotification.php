<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowModernizationChiefCorrectionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tiket $ticket,
        public string $validatedByName,
        public string $reviewComment,
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
            'tajuk' => 'LKK Pemodenan Memerlukan Pembetulan KUPP',
            'pesanan' => sprintf(
                'LKK tiket %s (%s) telah dikembalikan oleh Ketua Wilayah %s untuk pembetulan KUPP. Ulasan: %s',
                $this->ticket->id_tiket,
                $taskDescription,
                $this->validatedByName,
                $this->reviewComment
            ),
            'icon' => 'RotateCcw',
            'url' => route(
                'tickets.show',
                [
                    'id_tiket' => $this->ticket->id_tiket,
                ]
            ),
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowNetworkCorrectionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tiket $ticket,
        public string $reviewedByName,
        public string $reviewComment,
        public ?string $subCategory = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $taskDescription = $this->subCategory !== null
            && trim($this->subCategory) !== ''
                ? $this->subCategory
                : $this->ticket->kategori;

        return [
            'id_tiket' => $this->ticket->id_tiket,
            'tajuk' => 'Laporan Tapak Perlu Pembetulan',
            'pesanan' => sprintf(
                'Laporan tapak tiket %s (%s) telah dikembalikan oleh KUTD %s untuk pembetulan. Ulasan: %s',
                $this->ticket->id_tiket,
                $taskDescription,
                $this->reviewedByName,
                $this->reviewComment
            ),
            'ulasan' => $this->reviewComment,
            'icon' => 'AlertTriangle',
            'url' => route(
                'tickets.show',
                ['id_tiket' => $this->ticket->id_tiket]
            ),
            'target_role' => 'juruteknik',
            'workflow_version' => $this->ticket->workflow_version,
        ];
    }
}

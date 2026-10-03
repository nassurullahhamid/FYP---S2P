<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowProcurementReviewNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tiket $ticket,
        public string $submittedByName
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'id_tiket' => $this->ticket->id_tiket,
            'tajuk' => 'Semakan Laporan Pembekalan Diperlukan',
            'pesanan' => sprintf(
                'Laporan hasil kajian tiket %s telah dihantar oleh %s dan memerlukan semakan KUPP.',
                $this->ticket->id_tiket,
                $this->submittedByName
            ),
            'icon' => 'ClipboardCheck',
            'url' => route(
                'tickets.show',
                ['id_tiket' => $this->ticket->id_tiket]
            ),
            'target_role' => 'ketua_upp',
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowReportReviewNotification extends Notification
{
    use Queueable;

    public string $targetRole = 'ketua_utd';

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
        $taskDescription = $this->subCategory !== null
            && trim($this->subCategory) !== ''
                ? $this->subCategory
                : $this->ticket->kategori;

        return [
            'id_tiket' => $this->ticket->id_tiket,
            'tajuk' => 'Semakan Laporan Tapak Diperlukan',
            'pesanan' => sprintf(
                'Laporan tapak tiket %s (%s) telah dihantar oleh %s dan memerlukan semakan anda.',
                $this->ticket->id_tiket,
                $taskDescription,
                $this->submittedByName
            ),
            'icon' => 'ClipboardCheck',
            'url' => route(
                'tickets.show',
                ['id_tiket' => $this->ticket->id_tiket]
            ),
            'target_role' => $this->targetRole,
        ];
    }
}

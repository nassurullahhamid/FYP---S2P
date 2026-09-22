<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowConfirmationNotification extends Notification
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
        $taskDescription = $this->subCategory !== null
            && trim($this->subCategory) !== ''
                ? $this->subCategory
                : $this->ticket->kategori;

        return [
            'id_tiket' => $this->ticket->id_tiket,
            'tajuk' => 'Pengesahan Tiket Diperlukan',
            'pesanan' => sprintf(
                'Tiket %s (%s) telah dikemas kini oleh %s dan memerlukan pengesahan anda.',
                $this->ticket->id_tiket,
                $taskDescription,
                $this->submittedByName
            ),
            'icon' => 'CheckCircle2',
            'url' => route(
                'tickets.show',
                ['id_tiket' => $this->ticket->id_tiket]
            ),
            'target_role' => 'ketua_utd',
            'workflow_version' => $this->ticket->workflow_version,
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowAssignmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tiket $ticket,
        public string $assignedByName,
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
            'tajuk' => 'Tugasan Tiket Baharu',
            'pesanan' => sprintf(
                'Anda telah dilantik oleh %s sebagai petugas bagi tiket %s (%s).',
                $this->assignedByName,
                $this->ticket->id_tiket,
                $taskDescription
            ),
            'icon' => 'UserCheck',
            'url' => route(
                'tickets.show',
                ['id_tiket' => $this->ticket->id_tiket]
            ),
            'target_role' => 'juruteknik',
            'workflow_version' => $this->ticket->workflow_version,
        ];
    }
}

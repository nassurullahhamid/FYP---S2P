<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowReviewNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tiket $ticket,
        public string $reviewRole
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $roleLabel = config(
            "s2p_workflow.roles.{$this->reviewRole}",
            $this->reviewRole
        );

        return [
            'id_tiket' => $this->ticket->id_tiket,
            'tajuk' => 'Semakan Tiket #'.$this->ticket->id_tiket,
            'pesanan' => sprintf(
                'Tiket kategori %s telah diklasifikasikan. Semakan oleh %s diperlukan.',
                $this->ticket->kategori,
                $roleLabel
            ),
            'url' => route(
                'tickets.show',
                ['id_tiket' => $this->ticket->id_tiket]
            ),
            'target_role' => $this->reviewRole,
        ];
    }
}

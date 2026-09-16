<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ValidasiKWNoti extends Notification
{
    use Queueable;

    protected $ticket;
    protected $namaKUTD;

    public function __construct($ticket, $namaKUTD)
    {
        $this->ticket = $ticket;
        $this->namaKUTD = $namaKUTD;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'id_tiket' => $this->ticket->id_tiket,
            'tajuk'    => 'Validasi Ketua Wilayah Diperlukan',
            'pesanan'  => 'Laporan LKK bagi Tiket #' . $this->ticket->id_tiket . ' telah disahkan oleh KUTD (' . $this->namaKUTD . ') & menunggu kelulusan validasi anda.',
            'icon'     => 'ShieldCheck',
            'url'      => '/tickets/' . $this->ticket->id_tiket,
        ];
    }
}

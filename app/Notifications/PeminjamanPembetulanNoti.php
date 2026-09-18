<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PeminjamanPembetulanNoti extends Notification
{
    use Queueable;

    protected $ticket;
    protected $namaKUTD;
    protected $ulasan;

    public function __construct($ticket, $namaKUTD, $ulasan)
    {
        $this->ticket = $ticket;
        $this->namaKUTD = $namaKUTD;
        $this->ulasan = $ulasan;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'id_tiket' => $this->ticket->id_tiket,
            'tajuk'    => 'Borang Peminjaman Perlu Pembetulan',
            'pesanan'  => 'Borang peminjaman bagi Tiket #' . $this->ticket->id_tiket
                . ' telah dikembalikan oleh KUTD (' . $this->namaKUTD
                . ') untuk pembetulan. Ulasan: ' . $this->ulasan,
            'icon'     => 'AlertTriangle',
            'url'      => '/tickets/' . $this->ticket->id_tiket,
        ];
    }
}

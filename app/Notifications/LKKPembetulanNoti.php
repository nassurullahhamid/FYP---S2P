<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LKKPembetulanNoti extends Notification
{
    use Queueable;

    protected $ticket;
    protected $namaKetua;

    public function __construct($ticket, $namaKetua)
    {
        $this->ticket = $ticket;
        $this->namaKetua = $namaKetua;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $jawatanKetua = ($this->ticket->kategori === 'Transformasi Digital') ? 'KUPP' : 'KUTD';

        return [
            'id_tiket' => $this->ticket->id_tiket,
            'tajuk'    => 'LKK Perlu Pembetulan',
            'pesanan'  => 'Laporan LKK bagi Tiket #' . $this->ticket->id_tiket . ' telah dikembalikan oleh ' . $jawatanKetua . ' (' . $this->namaKetua . ') untuk pembetulan semula.',
            'url'      => '/tickets/' . $this->ticket->id_tiket,
        ];
    }
}

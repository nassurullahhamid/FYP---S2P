<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TugasanPicNoti extends Notification
{
    use Queueable;

    protected $ticket;
    protected $namaAset;
    protected $olehNama;

    public function __construct($ticket, $namaAset, $olehNama)
    {
        $this->ticket = $ticket;
        $this->namaAset = $namaAset;
        $this->olehNama = $olehNama;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'id_tiket' => $this->ticket->id_tiket,
            'tajuk'    => 'Tugasan Peminjaman Baru',
            'pesanan'  => 'Anda telah dilantik oleh ' . $this->olehNama . ' sebagai PIC untuk menguruskan siri perkakasan ' . $this->namaAset . ' bagi tiket ' . $this->ticket->id_tiket . '.',
            'icon'     => 'Package',
            'url'      => '/tickets/' . $this->ticket->id_tiket,
        ];
    }
}

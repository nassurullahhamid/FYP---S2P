<?php

namespace App\Notifications;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewTicketNoti extends Notification
{
    use Queueable;

    public $ticket;
    public $jenisNoti;

    // Create a new notification instance
    public function __construct(Tiket $ticket, string $jenisNoti = 'tugasan_teknikal')
    {
        $this->ticket = $ticket;
        $this->jenisNoti = $jenisNoti;
    }

    // Delivery channels
    public function via($notifiable): array
    {
        return ['database'];
    }

    // Array representation of the notification
    public function toArray($notifiable): array
    {
        // A. Special message for KUPP (Document review / Early phase)
        if ($this->jenisNoti === 'semakan') {
            return [
                'id_tiket'    => $this->ticket->id_tiket,
                'tajuk'       => 'Semakan Dokumen #' . $this->ticket->id_tiket,
                'pesanan'     => 'Tiket telah diklasifikasi sebagai ' . $this->ticket->kategori . '. Sila semak dokumen pemohon.',
                'url'         => route('tickets.show', $this->ticket->id_tiket),
                'target_role' => 'ketua_upp',
            ];
        }

        // B. Info message for KUTD/KW
        if ($this->jenisNoti === 'makluman_klasifikasi') {
            return [
                'id_tiket'    => $this->ticket->id_tiket,
                'tajuk'       => 'Makluman Klasifikasi #' . $this->ticket->id_tiket,
                'pesanan'     => 'Tiket telah berjaya diklasifikasi ke kategori ' . $this->ticket->kategori . '.',
                'url'         => route('tickets.show', $this->ticket->id_tiket),
                'target_role' => ['ketua_utd', 'ketua_wilayah'],
            ];
        }

        // C. KUTD task assignment message
        if ($this->jenisNoti === 'tugasan_utd') {
            return [
                'id_tiket'    => $this->ticket->id_tiket,
                'tajuk'       => 'Tugasan UTD Baru #' . $this->ticket->id_tiket,
                'pesanan'     => 'Tiket diserahkan kepada UTD untuk tindakan teknikal & agihan pegawai.',
                'url'         => route('tickets.show', $this->ticket->id_tiket),
                'target_role' => ['ketua_utd', 'kutd'],
            ];
        }

        // D. PIC task message (Supports 'tindakan_pic' and 'tugasan_teknikal')
        if (in_array($this->jenisNoti, ['tindakan_pic', 'tugasan_teknikal'])) {
            return [
                'id_tiket'    => $this->ticket->id_tiket,
                'tajuk'       => 'Tugasan PIC Baharu #' . $this->ticket->id_tiket,
                'pesanan'     => 'Anda telah dilantik sebagai PIC untuk tiket ini. Sila ambil tindakan segera.',
                'url'         => route('tickets.show', $this->ticket->id_tiket),
                'target_role' => ['juruteknik', 'pic'],
            ];
        }

        // E. KUTD review message (PIC sends technical report to KUTD)
        if ($this->jenisNoti === 'semakan_kutd') {
            return [
                'id_tiket'    => $this->ticket->id_tiket,
                'tajuk'       => 'Semakan Laporan Teknikal #' . $this->ticket->id_tiket,
                'pesanan'     => 'PIC telah menghantar laporan kajian tapak untuk semakan KUTD.',
                'url'         => route('tickets.show', $this->ticket->id_tiket),
                'target_role' => ['ketua_utd', 'kutd'],
            ];
        }

        // F. KUPP summary message (KUTD verifies technical review for KUPP)
        if ($this->jenisNoti === 'rumusan_kupp') {
            return [
                'id_tiket'    => $this->ticket->id_tiket,
                'tajuk'       => 'Pengesahan LKK #' . $this->ticket->id_tiket,
                'pesanan'     => 'Laporan teknikal telah disahkan oleh KUTD. Sila lengkapkan rumusan LKK.',
                'url'         => route('tickets.show', $this->ticket->id_tiket),
                'target_role' => ['ketua_upp', 'kupp'],
            ];
        }

        // G. Default message / New ticket created (Pending classification)
        return [
            'id_tiket'    => $this->ticket->id_tiket,
            'tajuk'       => 'Tiket Baru #' . $this->ticket->id_tiket,
            'pesanan'     => 'Terdapat permohonan baru yang memerlukan tindakan klasifikasi kategori.',
            'url'         => route('tickets.show', $this->ticket->id_tiket),
            'target_role' => ['ketua_upp', 'ketua_utd', 'ketua_wilayah'],
        ];
    }
}

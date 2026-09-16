<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class PengesahanKetuaNoti extends Notification
{
    use Queueable;

    protected $ticket;
    protected $namaPenghantar;
    protected $isUntukKUTD;

    public function __construct($ticket, $namaPenghantar, $isUntukKUTD = false)
    {
        $this->ticket = $ticket;
        $this->namaPenghantar = $namaPenghantar;
        $this->isUntukKUTD = $isUntukKUTD;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $kategori = $this->ticket->kategori;
        $id_tiket = $this->ticket->id_tiket;

        // Fallback selamat: Jika relation tak wujud, kita force tarik sub_kategori dari table anak.
        $subKategori = $this->ticket->sub_kategori ?? null;

        if (empty($subKategori)) {
            if ($kategori === 'Meja Bantuan') {
                $subKategori = DB::table('meja_bantuan')->where('id_tiket', $id_tiket)->value('sub_kategori');
            } elseif ($kategori === 'Transformasi Digital') {
                $subKategori = DB::table('transformasi_digital')->where('id_tiket', $id_tiket)->value('sub_kategori');
            } elseif ($kategori === 'Konsultasi Rangkaian') {
                $subKategori = DB::table('konsultasi_rangkaian')->where('id_tiket', $id_tiket)->value('sub_kategori');
            }
        }

        $statusTiket = trim(strtolower($this->ticket->status_tiket ?? ''));
        $subKategoriLower = trim(strtolower($subKategori ?? ''));

        if ($statusTiket === 'lkk perlu pembetulan') {
            $tajuk = 'Laporan LKK Perlu Pembetulan';
            $pesanan = 'Tiket #' . $id_tiket . ' dipulangkan untuk pembetulan LKK. Sila semak dan kemaskini segera.';

        } elseif ($statusTiket === 'menunggu validasi') {
            $tajuk = 'Validasi Kelulusan LKK Diperlukan';
            $pesanan = 'Laporan LKK Tiket #' . $id_tiket . ' telah lengkap disahkan. Sila lakukan validasi kelulusan akhir.';

        } elseif ($statusTiket === 'dalam tindakan pegawai' && $kategori === 'Transformasi Digital' && str_contains($subKategoriLower, 'pembekalan')) {
            $tajuk = 'Tindakan Teknikal LKK Pembekalan ICT';
            $pesanan = 'Butiran Tiket #' . $id_tiket . ' telah disahkan oleh KUPP. Sila lengkapkan laporan teknikal.';

        } elseif ($statusTiket === 'menunggu kelulusan') {
            $tajuk = 'Kelulusan Peminjaman Diperlukan';
            $pesanan = 'Permohonan peminjaman peralatan bagi Tiket #' . $id_tiket . ' sedang menunggu tindakan kelulusan anda.';

        } elseif ($statusTiket === 'menunggu pengesahan') {
            if (str_contains($subKategoriLower, 'peminjaman')) {
                $tajuk = 'Pengesahan Peminjaman Peralatan ICT';
                $pesanan = 'Tiket peminjaman #' . $id_tiket . ' memerlukan pengesahan & penutupan tiket.';
            } elseif ($subKategori === 'Pemodenan Bilik Mesyuarat') {
                $tajuk = 'Semakan LKK TD Diperlukan';
                $pesanan = 'Laporan LKK bagi Tiket #' . $id_tiket . ' telah diisi. Sila buat semakan dan pengesahan lanjut.';
            } else {
                $tajuk = 'Semakan Laporan LKK Diperlukan';
                $pesanan = 'Laporan kerja bagi tiket #' . $id_tiket . ' telah dihantar oleh (' . $this->namaPenghantar . ') untuk semakan dan pengesahan.';
            }

        } else {
            $tajuk = 'Notifikasi Sistem Tiket';
            $pesanan = 'Terdapat kemaskini atau tindakan diperlukan pada Tiket #' . $id_tiket . ' (Oleh: ' . $this->namaPenghantar . ').';
        }

        return [
            'id_tiket' => $id_tiket,
            'tajuk'    => $tajuk,
            'pesanan'  => $pesanan,
            'url'      => '/tickets/' . $id_tiket,
        ];
    }
}

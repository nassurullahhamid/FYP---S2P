<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KonsultasiRangkaian extends Model
{
    protected $table = 'konsultasi_rangkaian';

    protected $primaryKey = 'id_kr';

    protected $fillable = [
        'sub_kategori', 'tarikh_lawatan', 'masa_lawatan', 'catatan_lawatan',
        'jenis_premis', 'bilik_server', 'rack_server', 'sumber_kuasa',
        'persekitaran_fizikal', 'liputan', 'jenis_capaian', 'kelajuan',
        'lan', 'ap', 'firewall', 'rumusan', 'ulasan_teknikal', 'lampiran', 'id_tiket',
    ];

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(Tiket::class, 'id_tiket', 'id_tiket');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Laporan extends Model
{
    use HasFactory;

    protected $table = 'laporan';
    protected $primaryKey = 'id_laporan';

    protected $fillable = [
        'pendahuluan',
        'ulasan_teknikal',
        'cadangan_penambahbaikan',
        'objektif',
        'kos_items',
        'jumlah_kos',
        'rumusan',
        'logical_diagram',
        'physical_diagram',
        'disediakan_oleh',
        'disemak_oleh',
        'pengguna_ic',
        'id_tiket',
    ];

    protected $casts = [
        'kos_items' => 'array',
    ];

    // Relationship Laporan to Tiket (1:1)
    public function tiket()
    {
        return $this->belongsTo(Tiket::class, 'id_tiket', 'id_tiket');
    }

    // Relationship Laporan to Pengguna (1:1)
    public function pengguna()
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_ic', 'no_ic');
    }


}

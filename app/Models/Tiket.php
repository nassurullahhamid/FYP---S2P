<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Tiket extends Model
{
    protected $table = 'tiket';
    protected $primaryKey = 'id_tiket';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_tiket',
        'perkara',
        'tarikh_terima',
        'saluran',
        'nama_pemohon',
        'emel_pemohon',
        'notel_pemohon',
        'agensi',
        'lokasi',
        'daerah',
        'kategori',
        'lampiran',
        'tahap_keutamaan',
        'sla',
        'tarikh_tutup',
        'tempoh_tiket',
        'status_tiket',
        'ulasan_semakan',
        'pengguna_ic',
        'catatan_penutupan',
        'bukti_penutupan',
    ];

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_ic', 'no_ic');
    }

    public function petugas(): BelongsToMany
    {
        return $this->belongsToMany(
            Pengguna::class,
            'tugasan_tiket',
            'id_tiket',
            'no_ic'
        )->withTimestamps();
    }

    // audit trail
    public function rekodLog($aktiviti, $pesanan, $statusBadge = 'DALAM PROSES')
{
    $user = Auth::user();

    DB::table('jejak_tiket')->insert([
        'id_tiket'       => $this->id_tiket,
        'nama_pelaku'    => $user ? $user->nama : 'Sistem',
        'peranan_pelaku' => $user ? $user->peranan : 'system',
        'aktiviti'       => $aktiviti,
        'pesanan'        => $pesanan,
        'status_badge'   => $statusBadge,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
}

protected static function boot()
{
    parent::boot();

    static::deleting(function ($ticket) {
        DB::table('jejak_tiket')->where('id_tiket', $ticket->id_tiket)->delete();
    });
}

    public function mejaBantuan(): HasOne
    {
        return $this->hasOne(MejaBantuan::class, 'id_tiket', 'id_tiket');
    }

    public function konsultasiRangkaian(): HasOne
    {
        return $this->hasOne(KonsultasiRangkaian::class, 'id_tiket', 'id_tiket');
    }

    public function transformasiDigital(): HasOne
    {
        return $this->hasOne(TransformasiDigital::class, 'id_tiket', 'id_tiket');
    }

    public function laporan(): HasOne
    {
        return $this->hasOne(Laporan::class, 'id_tiket', 'id_tiket');
    }
}

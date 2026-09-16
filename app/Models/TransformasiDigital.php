<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformasiDigital extends Model
{
    protected $table = 'transformasi_digital';
    protected $primaryKey = 'id_td';

    protected $fillable = [
        'sub_kategori', 'catatan_lawatan', 'tarikh_lawatan', 'masa_lawatan', 'lampiran', 'pelan', 'id_tiket'
    ];

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(Tiket::class, 'id_tiket', 'id_tiket');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MejaBantuan extends Model
{
    protected $table = 'meja_bantuan';
    protected $primaryKey = 'id_mb';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_tiket',
        'sub_kategori',
        'serial_no',
        'kuantiti_dipinjam'
    ];

    public function tiket(): BelongsTo
    {
        return $this->belongsTo(Tiket::class, 'id_tiket', 'id_tiket');
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'serial_no', 'serial_no');
    }
}

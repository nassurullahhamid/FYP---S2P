<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aset extends Model
{
    protected $table = 'aset';
    protected $primaryKey = 'serial_no';

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'serial_no', 'nama_aset', 'model', 'cpu', 'ram', 'hard_disk', 'os', 'status', 'pengguna_ic'
    ];

    // Relationship: An asset belongs to a specific Pengguna
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_ic', 'no_ic');
    }
}

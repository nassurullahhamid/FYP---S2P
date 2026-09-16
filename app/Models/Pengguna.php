<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\CustomResetPassword;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pengguna extends Authenticatable
{

    use Notifiable;

    //  connects to the 'pengguna' table
    protected $table = 'pengguna';

    // Define the custom primary key (IC Number)
    protected $primaryKey = 'no_ic';

    // primary key is NOT an incrementing integer
    public $incrementing = false;
    protected $keyType = 'string';

    // Mass assignable attributes
    protected $fillable = [
        'no_ic', 'nama', 'emel', 'kata_laluan', 'jawatan', 'gred', 'no_telefon', 'peranan', 'status_pengguna',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    //reset password
    public function getEmailForPasswordReset()
    {
        return $this->emel;
    }

    public function getEmailAttribute()
    {
        return $this->emel;
    }

    public function routeNotificationForMail($notification)
    {
        return $this->emel;
    }


    public function getAuthPassword()
    {
        return $this->kata_laluan;
    }

    // Relationship: One Pengguna can own many Assets
    public function asets(): HasMany
    {
        return $this->hasMany(Aset::class, 'pengguna_ic', 'no_ic');
    }

    // Relationship: Many-to-Many with Tiket via the 'tugasan_tiket' bridge table
    public function tikets(): BelongsToMany
    {
        return $this->belongsToMany(
            Tiket::class,
            'tugasan_tiket',
            'no_ic',
            'id_tiket'
        )->withTimestamps();
    }

    // Reset Password
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }

    // Relationship: One-to-Many for pengguna with laporan
    public function laporan()
{

    return $this->hasMany(Laporan::class, 'pengguna_ic', 'no_ic');
}

    public function penulis()
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_ic', 'no_ic');
    }
}

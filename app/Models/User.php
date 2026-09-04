<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_rol',
        'name',
        'email',
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relación de muchos a uno (user-rol)
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    // Relación de uno a muchos (user-registro_ingreso)
    public function registrosIngreso()
    {
        return $this->hasMany(RegistroIngreso::class, 'id_usuario', 'id');
    }

    // Relación de uno a muchos (user-incidencia)
    public function incidencias()
    {
        return $this->hasMany(Incidencia::class, 'id_usuario', 'id');
    }

    // Relación de uno a muchos (user-auditoria_log)
    public function auditorias()
    {
        return $this->hasMany(AuditoriaLog::class, 'id_usuario', 'id');
    }
}

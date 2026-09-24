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
        'id_estudiante',
        'name',
        'email',
        'email_verified_at',
        'username',
        'password',
        'debe_cambiar_password',
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
            'debe_cambiar_password' => 'boolean',
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

    // Relación de uno a muchos (user-grupo)
    public function grupos()
    {
        return $this->hasMany(Grupo::class, 'id_usuario', 'id');
    }

    // Relación de uno a uno (cuenta de usuario - estudiante)
    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    /**
     * ¿La cuenta pertenece a un estudiante y debe cambiar su contraseña inicial?
     */
    public function esCuentaEstudiantePendiente(): bool
    {
        return $this->id_estudiante !== null && $this->debe_cambiar_password;
    }
}

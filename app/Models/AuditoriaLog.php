<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditoriaLog extends Model
{
    use HasFactory;

    protected $table = 'auditoria_log';
    protected $primaryKey = 'id_log';
    public $timestamps = false;
    protected $fillable = [
        'id_usuario',
        'tabla_afectada',
        'id_registro_afectado',
        'accion',
        'datos_anteriores',
        'datos_nuevos',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'datos_anteriores' => 'array',
            'datos_nuevos' => 'array',
            'fecha_hora' => 'datetime',
        ];
    }

    // Relación de muchos a uno (auditoria_log-user)
    public function user()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }
}

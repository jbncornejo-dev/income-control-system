<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    use HasFactory;

    protected $table = 'rol';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;
    protected $fillable = ['nombre_rol', 'permisos', 'asignaturas_reportes'];

    protected $casts = ['permisos' => 'array', 'asignaturas_reportes' => 'array'];

    public const LONGITUD_NOMBRE = 50;

    public const PERMISOS_REPORTES = [
        'reportes.examenes' => 'Exportar exámenes',
        'reportes.incidencias' => 'Exportar incidencias',
    ];

    public const ROLES_BASE = [
        'administrador' => 'Gestión académica, usuarios, roles, auditoría, control de ingreso y reportes.',
        'docente' => 'Gestión de exámenes, habilitaciones, asistencia, incidencias e histórico.',
        'personal de control de ingreso' => 'Registro de ingreso, consulta de estudiantes, asistencia e incidencias.',
        'estudiante' => 'Consulta de sus exámenes y gestión de su código QR.',
    ];

    public function esPersonalizado(): bool
    {
        return $this->permisos !== null && ! array_key_exists($this->nombre_rol, self::ROLES_BASE);
    }

    public function requiereClaveSegura(): bool
    {
        return $this->nombre_rol === 'administrador' || $this->permisos !== null;
    }

    public function puedeExportar(string $permiso): bool
    {
        return array_key_exists($permiso, self::PERMISOS_REPORTES)
            && in_array($permiso, $this->permisos ?? [], true);
    }

    // Relación de uno a muchos (rol-users)
    public function users()
    {
        return $this->hasMany(User::class, 'id_rol', 'id_rol');
    }
}

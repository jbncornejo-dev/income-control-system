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

    public const RECURSOS = [
        'usuarios' => [
            'nombre' => 'Usuarios', 'inicio' => 'usuarios.index',
            'acciones' => [
                'usuarios.ver' => 'Consultar',
            ],
        ],
        'roles' => [
            'nombre' => 'Roles', 'inicio' => 'roles.index',
            'acciones' => [
                'roles.ver' => 'Consultar',
            ],
        ],
        'reportes' => [
            'nombre' => 'Reportes', 'inicio' => 'reportes.index',
            'acciones' => [
                'reportes.examenes' => 'Exportar exámenes',
                'reportes.incidencias' => 'Exportar incidencias',
            ],
        ],
        'auditoria' => [
            'nombre' => 'Auditoría', 'inicio' => 'auditoria.index',
            'acciones' => [
                'auditoria.ver' => 'Consultar',
            ],
        ],
        'estudiantes' => [
            'nombre' => 'Estudiantes', 'inicio' => 'estudiantes.index',
            'acciones' => [
                'estudiantes.ver' => 'Consultar',
                'estudiantes.store' => 'Registrar',
                'estudiantes.update' => 'Editar',
                'estudiantes.destroy' => 'Desactivar',
                'estudiantes.activate' => 'Reactivar',
                'estudiantes.importar' => 'Importar CSV',
                'estudiantes.qr' => 'Consultar QR',
            ],
        ],
        'asignaturas' => [
            'nombre' => 'Asignaturas', 'inicio' => 'asignaturas.index',
            'acciones' => [
                'asignaturas.ver' => 'Consultar',
                'asignaturas.store' => 'Crear',
                'asignaturas.update' => 'Editar',
                'asignaturas.destroy' => 'Eliminar',
            ],
        ],
        'ambientes' => [
            'nombre' => 'Ambientes', 'inicio' => 'ambientes.index',
            'acciones' => [
                'ambientes.ver' => 'Consultar',
                'ambientes.store' => 'Crear',
                'ambientes.update' => 'Editar',
                'ambientes.destroy' => 'Eliminar',
            ],
        ],
        'examenes' => [
            'nombre' => 'Gestión de exámenes', 'inicio' => 'examenes.index',
            'acciones' => [
                'examenes.ver' => 'Consultar',
            ],
        ],
        'historico' => [
            'nombre' => 'Histórico', 'inicio' => 'historico.index',
            'acciones' => [
                'historico.ver' => 'Consultar',
                'historico.incidencias.exportar' => 'Exportar incidencias',
            ],
        ],
        'grupos' => [
            'nombre' => 'Grupos', 'inicio' => 'grupos.index',
            'acciones' => [
                'grupos.ver' => 'Consultar',
                'grupos.store' => 'Crear',
                'grupos.update' => 'Editar',
                'grupos.destroy' => 'Eliminar',
            ],
        ],
        'panel-asistencia' => [
            'nombre' => 'Panel de asistencia', 'inicio' => 'panel-asistencia.index',
            'acciones' => [
                'panel-asistencia.ver' => 'Consultar',
            ],
        ],
        'incidencias' => [
            'nombre' => 'Incidencias', 'inicio' => 'incidencias.index',
            'acciones' => [
                'incidencias.ver' => 'Consultar',
                'incidencias.store' => 'Registrar incidencia',
            ],
        ],
        'estadisticas' => [
            'nombre' => 'Estadísticas', 'inicio' => 'incidencias.estadisticas',
            'acciones' => [
                'estadisticas.ver' => 'Consultar',
            ],
        ],
        'registro-ingreso' => [
            'nombre' => 'Registrar ingreso', 'inicio' => 'registro-ingreso.index',
            'acciones' => [
                'registro-ingreso.ver' => 'Consultar',
                'registro-ingreso.store' => 'Registrar ingreso',
            ],
        ],
    ];

    public static function catalogo(): array
    {
        $catalogo = [];
        foreach (self::RECURSOS as $recurso) {
            foreach ($recurso['acciones'] as $clave => $accion) {
                $catalogo[$clave] = $recurso['nombre'].' · '.$accion;
            }
        }
        return $catalogo;
    }

    public function permiteRuta(?string $ruta): bool
    {
        if (! $this->esPersonalizado()) {
            return false;
        }
        foreach (self::RECURSOS as $recurso) {
            $acciones = array_intersect(array_keys($recurso['acciones']), $this->permisos);
            if ($acciones && ($recurso['inicio'] === $ruta || in_array($ruta, $acciones, true))) {
                return true;
            }
        }
        return in_array($ruta, ['registro-ingreso.validar', 'registro-ingreso.sync'], true)
            && in_array('registro-ingreso.store', $this->permisos, true);
    }

    public function recursos(): array
    {
        $recursos = [];
        foreach (self::RECURSOS as $recurso) {
            if ($this->permiteRuta($recurso['inicio'])) {
                $recursos[] = route($recurso['inicio'], [], false);
            }
        }
        return $recursos;
    }

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

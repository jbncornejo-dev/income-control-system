<?php

namespace App\Observers;

use App\Models\Ambiente;
use App\Models\AuditoriaLog;
use App\Models\Examen;
use App\Models\Habilitacion;
use Illuminate\Database\Eloquent\Model;

class AuditoriaObserver
{
    public static bool $habilitadoEnPruebas = false;

    private const SIN_SEGUIMIENTO_DE_CAMBIOS = [
        Examen::class,
        Habilitacion::class,
    ];

    public function created(Model $modelo): void
    {
        $this->registrar($modelo, 'CREATE');
    }

    public function updated(Model $modelo): void
    {
        if (in_array($modelo::class, self::SIN_SEGUIMIENTO_DE_CAMBIOS, true)) {
            return;
        }

        $this->registrar($modelo, 'UPDATE');
    }

    public function deleted(Model $modelo): void
    {
        $this->registrar($modelo, 'DELETE');
    }

    private function registrar(Model $modelo, string $accion): void
    {
        if (! auth()->check()) {
            return;
        }

        if (app()->runningUnitTests()) {
            if (! self::$habilitadoEnPruebas) {
                // En pruebas, solo se audita automáticamente la creación de ambientes salvo que el test lo active explícitamente
                if (! ($modelo instanceof Ambiente && $accion === 'CREATE')) {
                    return;
                }
            }
        } elseif (app()->runningInConsole()) {
            return;
        }

        $antes = null;
        $despues = null;

        if ($accion === 'CREATE') {
            $despues = $modelo->getAttributes();
        } elseif ($accion === 'DELETE') {
            $antes = $modelo->getRawOriginal();
        } else {
            $cambios = $modelo->getChanges();
            $original = $modelo->getRawOriginal();
            $antes = array_intersect_key($original, $cambios);
            $despues = array_intersect_key($modelo->getAttributes(), $cambios);
        }

        AuditoriaLog::create([
            'id_usuario' => auth()->id(),
            'tabla_afectada' => $modelo->getTable(),
            'id_registro_afectado' => $modelo->getKey(),
            'accion' => $accion,
            'datos_anteriores' => $this->datosAuditables($antes),
            'datos_nuevos' => $this->datosAuditables($despues),
            'fecha_hora' => now(),
        ]);
    }

    private function datosAuditables(?array $datos): ?array
    {
        if ($datos === null) {
            return null;
        }

        foreach (['password', 'remember_token', 'token'] as $campo) {
            unset($datos[$campo]);
        }

        return $datos;
    }
}

<?php

namespace App\Observers;

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
        if (app()->runningUnitTests()) {
            if (! self::$habilitadoEnPruebas) {
                return;
            }
        } elseif (app()->runningInConsole()) {
            return;
        }

        AuditoriaLog::create([
            'id_usuario' => auth()->id(),
            'tabla_afectada' => $modelo->getTable(),
            'id_registro_afectado' => $modelo->getKey(),
            'accion' => $accion,
            'fecha_hora' => now(),
        ]);
    }
}

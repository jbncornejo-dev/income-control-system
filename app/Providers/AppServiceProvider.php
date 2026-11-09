<?php

namespace App\Providers;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\AuditoriaLog;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $auditedModels = [
            Ambiente::class => 'ambiente',
            Asignatura::class => 'asignatura',
            Examen::class => 'examen',
            Grupo::class => 'grupo',
            User::class => 'users',
        ];
        foreach ($auditedModels as $modelClass => $table) {
            $modelClass::created(fn (Model $model) => $this->registrarAuditoria($model, $table, 'CREATE'));
            $modelClass::updated(fn (Model $model) => $this->registrarAuditoria($model, $table, 'UPDATE'));
            $modelClass::deleted(fn (Model $model) => $this->registrarAuditoria($model, $table, 'DELETE'));
        }

        // Forzar HTTPS en producción y staging
        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
        }
    }

    private function registrarAuditoria(Model $model, string $table, string $action): void
    {
        if (! auth()->check()) {
            return;
        }
        $antes = null;
        $despues = null;

        if ($action === 'CREATE') {
            $despues = $model->getAttributes();
        } elseif ($action === 'DELETE') {
            $antes = $model->getRawOriginal();
        } else {
            $cambios = $model->getChanges();
            $original = $model->getRawOriginal();
            $antes = array_intersect_key($original, $cambios);
            $despues = array_intersect_key($model->getAttributes(), $cambios);
        }

        AuditoriaLog::create([
            'id_usuario' => auth()->id(),
            'tabla_afectada' => $table,
            'id_registro_afectado' => $model->getKey(),
            'accion' => $action,
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

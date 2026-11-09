<?php

namespace App\Providers;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\Inscripcion;
use App\Models\RegistroIngreso;
use App\Models\User;
use App\Observers\AuditoriaObserver;
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
        // Forzar HTTPS en producción y staging
        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
        }

        foreach ([
            Ambiente::class,
            Asignatura::class,
            Estudiante::class,
            Examen::class,
            Grupo::class,
            Habilitacion::class,
            Incidencia::class,
            Inscripcion::class,
            RegistroIngreso::class,
            User::class,
        ] as $modelo) {
            $modelo::observe(AuditoriaObserver::class);
        }
    }
}

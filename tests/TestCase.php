<?php

namespace Tests;

use App\Models\Periodo;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // compose.yaml inyecta el archivo .env como variables de entorno del
        // contenedor. PHP CLI precarga $_SERVER/$_ENV con APP_ENV=local, lo que
        // impide que phpunit.xml fije APP_ENV=testing y que Laravel omita el
        // CSRF en pruebas (runningUnitTests()). Forzamos el entorno de la app.
        $this->app['env'] = 'testing';
    }

    /**
     * Crea (o recupera) un periodo por defecto para las pruebas. Por defecto un
     * semestre (tipo 'semestre'); los exámenes exigen id_periodo NOT NULL, así
     * que los tests que crean exámenes directamente pueden vincularlos con este helper.
     */
    protected function crearPeriodo(string $gestion = '2026', int $numero = 1, string $tipo = 'semestre'): Periodo
    {
        return Periodo::firstOrCreate(
            ['gestion' => $gestion, 'tipo' => $tipo, 'numero' => $numero],
            ['fecha_inicio' => $gestion.'-01-01', 'fecha_fin' => $gestion.'-12-31']
        );
    }
}

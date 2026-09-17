<?php

namespace Tests;

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
}

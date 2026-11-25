<?php

/*
 * Arranque de pruebas.
 *
 * PHPUnit no logra fijar con autoridad las variables de entorno de la base en
 * este stack: compose inyecta .env como variables reales del contenedor
 * (env_file) y los adapters de Dotenv de Laravel leen $_ENV/$_SERVER, que el
 * <env> de phpunit.xml no modifica de forma fiable. Por eso se fuerzan acá,
 * ANTES de cargar Laravel, por las tres vías (putenv + $_ENV + $_SERVER):
 * así env('DB_DATABASE') resuelve a app_testing y RefreshDatabase
 * (migrate:fresh) jamás toca la base 'app' de desarrollo, que se llena por
 * separado con migrate:fresh --seed.
 */
foreach ([
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => 'app_testing',
] as $clave => $valor) {
    putenv($clave.'='.$valor);
    $_ENV[$clave] = $valor;
    $_SERVER[$clave] = $valor;
}

require __DIR__.'/../vendor/autoload.php';

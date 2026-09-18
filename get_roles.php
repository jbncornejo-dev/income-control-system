<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$roles = \App\Models\Rol::all();
foreach ($roles as $rol) {
    echo $rol->id_rol . ': ' . $rol->nombre_rol . "\n";
}

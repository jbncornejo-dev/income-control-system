<?php

use Illuminate\Support\Facades\Route;

// Captura cualquier solicitud y la dirige a la vista principal
Route::get('/{any}', function () {
    return view('welcome'); 
})->where('any', '.*');
<?php

use App\Http\Controllers\AiProbeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Evita fallo al redirigir invitados (middleware auth) cuando no existe ruta nombrada "login".
Route::get('/login', function () {
    return redirect('/');
})->name('login');

Route::get('/ai/probe', AiProbeController::class);

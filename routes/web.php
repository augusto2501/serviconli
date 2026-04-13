<?php

use App\Http\Controllers\AiProbeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// RF-020 / RF-016 — UI web (token Sanctum en sessionStorage vía serviconli-app.js)
Route::view('/login', 'auth.login')->name('login');
Route::view('/mis-afiliados', 'affiliates.index')->name('mis-afiliados');
Route::get('/afiliados/{affiliate}/ficha', function (int|string $affiliate) {
    return view('affiliates.ficha', ['affiliateId' => (int) $affiliate]);
})->whereNumber('affiliate')->name('affiliates.ficha');

// Flujo 3 — Aporte Individual
Route::get('/afiliados/{affiliate}/aporte', function (int|string $affiliate) {
    return view('contributions.individual', ['affiliateId' => (int) $affiliate]);
})->whereNumber('affiliate')->name('contributions.individual');

// Flujo 4 — Liquidación por Lotes
Route::view('/liquidacion-lotes', 'liquidation.batch')->name('liquidation.batch');

// Flujo 8 — Generación Archivo PILA
Route::view('/generar-pila', 'liquidation.pila-file')->name('liquidation.pila-file');

// Flujos 5/6/7 — Cartera y Facturación
Route::view('/cartera', 'billing.cartera')->name('billing.cartera');

// Flujo 10 — Cuadre de caja
Route::view('/cuadre-caja', 'cash.reconciliation')->name('cash.reconciliation');

// Sprint I — Asesores y terceros (UI)
Route::view('/asesores', 'advisors.index')->name('advisors.ui');
Route::view('/terceros', 'third_parties.index')->name('third_parties.ui');

Route::get('/ai/probe', AiProbeController::class);

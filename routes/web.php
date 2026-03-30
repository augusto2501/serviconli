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

Route::get('/ai/probe', AiProbeController::class);

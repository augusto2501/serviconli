<?php

use App\Modules\PILALiquidation\Controllers\PilaLiquidationController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::post('/pila/liquidations', [PilaLiquidationController::class, 'store']);
Route::get('/pila/liquidations/{publicId}', [PilaLiquidationController::class, 'show']);
Route::post('/pila/liquidations/{publicId}/confirm', [PilaLiquidationController::class, 'confirm']);
Route::post('/pila/liquidations/{publicId}/cancel', [PilaLiquidationController::class, 'cancel']);

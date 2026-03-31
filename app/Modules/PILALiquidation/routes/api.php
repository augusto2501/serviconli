<?php

use App\Modules\PILALiquidation\Controllers\IndividualContributionController;
use App\Modules\PILALiquidation\Controllers\PilaLiquidationController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

// Liquidación PILA masiva
Route::post('/pila/liquidations', [PilaLiquidationController::class, 'store']);
Route::get('/pila/liquidations/{publicId}', [PilaLiquidationController::class, 'show']);
Route::post('/pila/liquidations/{publicId}/confirm', [PilaLiquidationController::class, 'confirm']);
Route::post('/pila/liquidations/{publicId}/cancel', [PilaLiquidationController::class, 'cancel']);

// Flujo 3 — Aporte Individual
Route::get('/contributions/prepare/{affiliateId}', [IndividualContributionController::class, 'prepare']);
Route::post('/contributions/preview', [IndividualContributionController::class, 'preview']);
Route::post('/contributions', [IndividualContributionController::class, 'store']);

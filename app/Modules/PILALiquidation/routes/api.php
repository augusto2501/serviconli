<?php

use App\Modules\PILALiquidation\Controllers\BatchLiquidationController;
use App\Modules\PILALiquidation\Controllers\IndividualContributionController;
use App\Modules\PILALiquidation\Controllers\PilaLiquidationController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

// Liquidación PILA (cálculo directo)
Route::post('/pila/liquidations', [PilaLiquidationController::class, 'store']);
Route::get('/pila/liquidations/{publicId}', [PilaLiquidationController::class, 'show']);
Route::post('/pila/liquidations/{publicId}/confirm', [PilaLiquidationController::class, 'confirm']);
Route::post('/pila/liquidations/{publicId}/cancel', [PilaLiquidationController::class, 'cancel']);

// Flujo 3 — Aporte Individual
Route::get('/contributions/prepare/{affiliateId}', [IndividualContributionController::class, 'prepare']);
Route::post('/contributions/preview', [IndividualContributionController::class, 'preview']);
Route::post('/contributions', [IndividualContributionController::class, 'store']);

// Flujo 4 — Liquidación por Lotes
Route::get('/batches/payers', [BatchLiquidationController::class, 'payers']);
Route::get('/batches', [BatchLiquidationController::class, 'index']);
Route::post('/batches', [BatchLiquidationController::class, 'store']);
Route::get('/batches/{batchId}', [BatchLiquidationController::class, 'show']);
Route::put('/batches/{batchId}/lines/{lineId}', [BatchLiquidationController::class, 'updateLine']);
Route::post('/batches/{batchId}/lines/{lineId}/toggle', [BatchLiquidationController::class, 'toggleLine']);
Route::post('/batches/{batchId}/confirm', [BatchLiquidationController::class, 'confirm']);
Route::post('/batches/{batchId}/cancel', [BatchLiquidationController::class, 'cancel']);

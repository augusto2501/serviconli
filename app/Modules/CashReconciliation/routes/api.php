<?php

use App\Modules\CashReconciliation\Controllers\CashReconciliationController;
use Illuminate\Support\Facades\Route;

// Flujo 10 — Cuadre de caja
Route::get('/cash-reconciliation', [CashReconciliationController::class, 'show']);
Route::post('/cash-reconciliation/recalculate', [CashReconciliationController::class, 'recalculate']);
Route::post('/cash-reconciliation/close', [CashReconciliationController::class, 'close']);

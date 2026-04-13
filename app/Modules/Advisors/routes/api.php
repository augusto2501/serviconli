<?php

use App\Modules\Advisors\Controllers\AdvisorCommissionController;
use App\Modules\Advisors\Controllers\AdvisorController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::apiResource('advisors', AdvisorController::class);
Route::get('advisor-commissions', [AdvisorCommissionController::class, 'index']);
Route::patch('advisor-commissions/{advisor_commission}', [AdvisorCommissionController::class, 'update']);

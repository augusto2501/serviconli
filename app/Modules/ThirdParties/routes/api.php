<?php

use App\Modules\ThirdParties\Controllers\AdvisorReceivableController;
use App\Modules\ThirdParties\Controllers\BankDepositController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::post('third-parties/bank-deposits', [BankDepositController::class, 'store']);
Route::get('third-parties/advisor-receivables', [AdvisorReceivableController::class, 'index']);
Route::patch('third-parties/advisor-receivables/{advisor_receivable}', [AdvisorReceivableController::class, 'update']);

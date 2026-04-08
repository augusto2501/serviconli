<?php

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

use App\Modules\Affiliations\Controllers\MultiIncomeContractController;
use Illuminate\Support\Facades\Route;

Route::post('affiliates/{affiliate}/multi-income-contracts', [MultiIncomeContractController::class, 'store']);

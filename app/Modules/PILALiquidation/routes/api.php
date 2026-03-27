<?php

use App\Modules\PILALiquidation\Controllers\PilaLiquidationController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::post('/pila/liquidations', [PilaLiquidationController::class, 'store']);

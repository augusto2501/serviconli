<?php

use App\Modules\RegulatoryEngine\Controllers\PILACalculationProbeController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::match(['get', 'post'], '/pila/calculate', PILACalculationProbeController::class);

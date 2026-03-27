<?php

use App\Modules\Affiliates\Controllers\AffiliateController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::apiResource('affiliates', AffiliateController::class);

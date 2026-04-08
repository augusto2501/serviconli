<?php

use App\Modules\Disabilities\Controllers\AffiliateDisabilityController;
use App\Modules\Disabilities\Controllers\DisabilityExtensionController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::get('affiliates/{affiliate}/disabilities', [AffiliateDisabilityController::class, 'index']);
Route::post('affiliates/{affiliate}/disabilities', [AffiliateDisabilityController::class, 'store']);
Route::get('affiliates/{affiliate}/disabilities/{disability}', [AffiliateDisabilityController::class, 'show'])
    ->scopeBindings();
Route::patch('affiliates/{affiliate}/disabilities/{disability}', [AffiliateDisabilityController::class, 'update'])
    ->scopeBindings();
Route::delete('affiliates/{affiliate}/disabilities/{disability}', [AffiliateDisabilityController::class, 'destroy'])
    ->scopeBindings();

Route::post('affiliates/{affiliate}/disabilities/{disability}/extensions', [DisabilityExtensionController::class, 'store'])
    ->scopeBindings();

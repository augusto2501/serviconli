<?php

use App\Modules\RegulatoryEngine\Controllers\CatalogAdminController;
use App\Modules\RegulatoryEngine\Controllers\PILACalculationProbeController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::match(['get', 'post'], '/pila/calculate', [PILACalculationProbeController::class, 'single']);
Route::post('/pila/calculate-consolidated', [PILACalculationProbeController::class, 'consolidated']);

// RF-116: Admin CRUD catálogos normativos
Route::get('/admin/catalogs', [CatalogAdminController::class, 'catalogs']);
Route::get('/admin/catalogs/{table}', [CatalogAdminController::class, 'index']);
Route::get('/admin/catalogs/{table}/{id}', [CatalogAdminController::class, 'show']);
Route::post('/admin/catalogs/{table}', [CatalogAdminController::class, 'store']);
Route::put('/admin/catalogs/{table}/{id}', [CatalogAdminController::class, 'update']);
Route::delete('/admin/catalogs/{table}/{id}', [CatalogAdminController::class, 'destroy']);

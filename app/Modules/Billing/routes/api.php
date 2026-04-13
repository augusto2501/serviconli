<?php

use App\Modules\Billing\Controllers\CuentaCobroController;
use App\Modules\Billing\Controllers\InvoiceController;
use App\Modules\Billing\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;

// Cuentas de cobro — Flujo 5/6
Route::get('/cuentas-cobro', [CuentaCobroController::class, 'index']);
Route::post('/cuentas-cobro', [CuentaCobroController::class, 'store']);
Route::get('/cuentas-cobro/{id}', [CuentaCobroController::class, 'show']);
Route::post('/cuentas-cobro/{id}/regenerate', [CuentaCobroController::class, 'regenerate']);
Route::post('/cuentas-cobro/{id}/definitiva', [CuentaCobroController::class, 'makeDefinitiva']);
Route::post('/cuentas-cobro/{id}/pay', [CuentaCobroController::class, 'pay']);
Route::get('/cuentas-cobro/{id}/pdf', [CuentaCobroController::class, 'pdf']);
Route::post('/cuentas-cobro/{id}/cancel', [CuentaCobroController::class, 'cancel']);

// Cotizaciones — Sprint G / RN-19
Route::post('/quotations', [QuotationController::class, 'store']);

// Recibos / Facturas — Flujo 7
Route::get('/invoices', [InvoiceController::class, 'index']);
Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
Route::post('/invoices', [InvoiceController::class, 'store']);
Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel']);

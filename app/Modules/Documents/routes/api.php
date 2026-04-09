<?php

use App\Modules\Documents\Controllers\ContractDocumentController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::get('affiliates/{affiliate}/contract-documents/{code}', [ContractDocumentController::class, 'show'])
    ->where('code', '[a-z_]+');

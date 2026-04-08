<?php

use App\Modules\Communications\Controllers\CommNotificationController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::get('communications/notifications', [CommNotificationController::class, 'index']);
Route::patch('communications/notifications/{notification}', [CommNotificationController::class, 'markRead']);

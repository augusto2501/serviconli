<?php

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

use App\Modules\Security\Controllers\AuditLogController;
use App\Modules\Security\Controllers\DashboardController;
use App\Modules\Security\Controllers\GdprRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    // RF-114: Dashboard gerencial
    Route::get('dashboard', [DashboardController::class, 'index']);

    // RF-115: Reportes operativos
    Route::prefix('reports')->group(function (): void {
        Route::get('daily-contributions', [DashboardController::class, 'dailyContributions']);
        Route::get('mora', [DashboardController::class, 'mora']);
        Route::get('affiliates-by-advisor', [DashboardController::class, 'affiliatesByAdvisor']);
        Route::get('affiliates-by-employer', [DashboardController::class, 'affiliatesByEmployer']);
        Route::get('cash-reconciliation', [DashboardController::class, 'cashReconciliation']);
        Route::get('end-of-day', [DashboardController::class, 'endOfDay']);
    });

    // RF-109: Audit logs
    Route::get('audit-logs', [AuditLogController::class, 'index']);

    // RF-110: Habeas Data — gestión derechos titular
    Route::prefix('gdpr-requests')->group(function (): void {
        Route::get('/', [GdprRequestController::class, 'index']);
        Route::post('/', [GdprRequestController::class, 'store']);
        Route::get('summary', [GdprRequestController::class, 'summary']);
        Route::get('{gdprRequest}', [GdprRequestController::class, 'show']);
        Route::patch('{gdprRequest}/resolve', [GdprRequestController::class, 'resolve']);
    });
});

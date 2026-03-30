<?php

use App\Modules\Affiliates\Controllers\AffiliateController;
use App\Modules\Affiliates\Controllers\AffiliateNoteController;
use App\Modules\Affiliates\Controllers\BeneficiaryController;
use App\Modules\Affiliates\Controllers\EnrollmentController;
use App\Modules\Affiliates\Controllers\Ficha360Controller;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::get('affiliates/export', [AffiliateController::class, 'export']);
Route::apiResource('affiliates', AffiliateController::class);
Route::get('affiliates/{affiliate}/ficha-360', [Ficha360Controller::class, 'show']);
Route::get('affiliates/{affiliate}/beneficiaries', [BeneficiaryController::class, 'index']);
Route::post('affiliates/{affiliate}/beneficiaries', [BeneficiaryController::class, 'store']);
Route::get('affiliates/{affiliate}/notes', [AffiliateNoteController::class, 'index']);
Route::post('affiliates/{affiliate}/notes', [AffiliateNoteController::class, 'store']);

Route::post('enrollment/step-1', [EnrollmentController::class, 'step1']);
Route::post('enrollment/step-2', [EnrollmentController::class, 'step2']);
Route::post('enrollment/step-3', [EnrollmentController::class, 'step3']);
Route::post('enrollment/step-4', [EnrollmentController::class, 'step4']);
Route::post('enrollment/step-5', [EnrollmentController::class, 'step5']);
Route::post('enrollment/step-6/confirm', [EnrollmentController::class, 'confirm']);

<?php

use App\Modules\Affiliates\Controllers\AffiliateController;
use App\Modules\Affiliates\Controllers\AffiliateNoteController;
use App\Modules\Affiliates\Controllers\BeneficiaryController;
use App\Modules\Affiliates\Controllers\EnrollmentController;
use App\Modules\Affiliates\Controllers\Ficha360Controller;
use App\Modules\Affiliates\Controllers\NoveltyController;
use App\Modules\Affiliates\Controllers\PaymentCertificateController;
use App\Modules\Affiliates\Controllers\PortalCredentialController;
use App\Modules\Affiliates\Controllers\ReentryController;
use Illuminate\Support\Facades\Route;

// Rutas API del módulo (prefijo /api aplicado por ModuleServiceProvider).

Route::get('affiliates/export', [AffiliateController::class, 'export']);
Route::apiResource('affiliates', AffiliateController::class);
Route::get('affiliates/{affiliate}/ficha-360', [Ficha360Controller::class, 'show']);
Route::get('affiliates/{affiliate}/payment-certificate', [PaymentCertificateController::class, 'show']);
Route::get('affiliates/{affiliate}/payment-certificate/pdf', [PaymentCertificateController::class, 'pdf']);
Route::post('affiliates/{affiliate}/novelties', [NoveltyController::class, 'store']);
Route::get('affiliates/{affiliate}/beneficiaries', [BeneficiaryController::class, 'index']);
Route::post('affiliates/{affiliate}/beneficiaries', [BeneficiaryController::class, 'store']);
Route::get('affiliates/{affiliate}/notes', [AffiliateNoteController::class, 'index']);
Route::post('affiliates/{affiliate}/notes', [AffiliateNoteController::class, 'store']);

Route::get('affiliates/{affiliate}/portal-credentials', [PortalCredentialController::class, 'index']);
Route::post('affiliates/{affiliate}/portal-credentials', [PortalCredentialController::class, 'store']);
Route::patch('affiliates/{affiliate}/portal-credentials/{portal_credential}', [PortalCredentialController::class, 'update']);
Route::delete('affiliates/{affiliate}/portal-credentials/{portal_credential}', [PortalCredentialController::class, 'destroy']);

Route::post('enrollment/step-1', [EnrollmentController::class, 'step1']);
Route::post('enrollment/step-2', [EnrollmentController::class, 'step2']);
Route::post('enrollment/step-3', [EnrollmentController::class, 'step3']);
Route::post('enrollment/step-4', [EnrollmentController::class, 'step4']);
Route::post('enrollment/step-5', [EnrollmentController::class, 'step5']);
Route::post('enrollment/step-6/confirm', [EnrollmentController::class, 'confirm']);

Route::get('reentry/eligible', [ReentryController::class, 'eligible']);
Route::post('reentry/start', [ReentryController::class, 'start']);
Route::post('reentry/step-1', [ReentryController::class, 'step1']);
Route::post('reentry/step-2', [ReentryController::class, 'step2']);
Route::post('reentry/step-3', [ReentryController::class, 'step3']);
Route::post('reentry/confirm', [ReentryController::class, 'confirm']);

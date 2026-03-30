<?php

use App\Modules\Employers\Controllers\EmployerController;
use Illuminate\Support\Facades\Route;

Route::apiResource('employers', EmployerController::class);

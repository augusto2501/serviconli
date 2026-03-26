<?php

use App\Http\Controllers\AiProbeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/ai/probe', AiProbeController::class);

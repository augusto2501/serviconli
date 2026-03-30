<?php

namespace App\Http\Controllers;

use App\Support\ApiExceptionRenderer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Las respuestas de error JSON para rutas `api/*` se unifican en
 * {@see ApiExceptionRenderer} (registrado en bootstrap/app.php).
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}

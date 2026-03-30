<?php

namespace App\Support;

// BC-13 — respuestas JSON homogéneas para errores en /api/*

use App\Modules\RegulatoryEngine\Exceptions\MissingRegulatoryParameterException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public static function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public static function render(Request $request, Throwable $e): ?JsonResponse
    {
        if (! self::isApiRequest($request)) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($e instanceof MissingRegulatoryParameterException) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'MISSING_REGULATORY_PARAMETER',
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($e instanceof InvalidArgumentException) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'INVALID_ARGUMENT',
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'No autenticado.',
                'code' => 'AUTHENTICATION',
            ], SymfonyResponse::HTTP_UNAUTHORIZED);
        }

        if ($e instanceof AuthorizationException) {
            $status = $e->hasStatus()
                ? (int) $e->status()
                : SymfonyResponse::HTTP_FORBIDDEN;

            return response()->json([
                'message' => $e->getMessage() ?: 'No autorizado.',
                'code' => 'AUTHORIZATION',
            ], $status);
        }

        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Recurso no encontrado.',
                'code' => 'NOT_FOUND',
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $payload = [
                'message' => $e->getMessage() ?: SymfonyResponse::$statusTexts[$status] ?? 'Error HTTP.',
                'code' => 'HTTP_ERROR',
            ];
            if ($status === SymfonyResponse::HTTP_TOO_MANY_REQUESTS) {
                $payload['code'] = 'RATE_LIMIT';
            }

            return response()->json($payload, $status);
        }

        if ($e instanceof QueryException) {
            return response()->json([
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'Error al acceder a los datos. Intente de nuevo o contacte soporte.',
                'code' => 'DATABASE_ERROR',
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        $debug = config('app.debug');

        return response()->json([
            'message' => $debug
                ? $e->getMessage()
                : 'Error interno del servidor.',
            'code' => 'SERVER_ERROR',
            'exception' => $debug ? $e::class : null,
            'file' => $debug ? $e->getFile() : null,
            'line' => $debug ? $e->getLine() : null,
            'trace' => $debug && $request->boolean('debug_trace')
                ? collect($e->getTrace())->take(15)->all()
                : null,
        ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}

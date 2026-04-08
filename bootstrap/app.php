<?php

use App\Support\ApiExceptionRenderer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $tz = env('SCHEDULE_TIMEZONE', config('app.timezone', 'America/Bogota'));

        $schedule->command('daily:close')
            ->dailyAt(env('SCHEDULE_DAILY_CLOSE_AT', '23:00'))
            ->timezone($tz);

        $schedule->command('mora:detect')
            ->dailyAt(env('SCHEDULE_MORA_DETECT_AT', '08:00'))
            ->timezone($tz);

        $schedule->command('pila:transicion-periodo')
            ->monthlyOn((int) env('SCHEDULE_PILA_TRANSICION_DAY', 1), env('SCHEDULE_PILA_TRANSICION_AT', '02:00'))
            ->timezone($tz);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            return ApiExceptionRenderer::render($request, $e);
        });
    })->create();

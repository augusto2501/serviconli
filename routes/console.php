<?php

// DOCUMENTO_RECTOR §2.2 / §7 — contratos Artisan (stubs hasta implementación BC-05 / ETL SKILL)

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// pila:generar-planilla → App\Modules\PILALiquidation\Commands\GenerarPlanillaCommand
// pila:transicion-periodo → App\Modules\Affiliates\Commands\TransicionPeriodoCommand
// mora:detect → App\Modules\Affiliates\Commands\MoraDetectCommand
//
// Programación: bootstrap/app.php → withSchedule(). En producción: * * * * * php artisan schedule:run
// Variables: SCHEDULE_TIMEZONE, SCHEDULE_DAILY_CLOSE_AT, SCHEDULE_MORA_DETECT_AT,
// SCHEDULE_PILA_TRANSICION_DAY, SCHEDULE_PILA_TRANSICION_AT

// ETL commands: EtlMigrateExcelCommand, EtlMigrateAccessCommand (registrados en AppServiceProvider)

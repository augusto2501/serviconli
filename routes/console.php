<?php

// DOCUMENTO_RECTOR §2.2 / §7 — contratos Artisan (stubs hasta implementación BC-05 / ETL SKILL)

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// pila:generar-planilla → implementado en App\Modules\PILALiquidation\Commands\GenerarPlanillaCommand

Artisan::command('pila:transicion-periodo {periodo_anterior}', function (): void {
    $this->info('Stub pila:transicion-periodo — período anterior: '.$this->argument('periodo_anterior').' (DOCUMENTO_RECTOR).');
})->purpose('Transición de período PILA (implementación pendiente).');

Artisan::command('etl:migrate-excel {path}', function (): void {
    $this->info('Stub etl:migrate-excel — ruta: '.$this->argument('path').' (SKILL: mapeo 1:1, sin columnas inventadas).');
})->purpose('ETL desde Excel según SKILL (implementación pendiente).');

Artisan::command('etl:migrate-access {path}', function (): void {
    $this->info('Stub etl:migrate-access — ruta: '.$this->argument('path').' (SKILL).');
})->purpose('ETL desde Access según SKILL (implementación pendiente).');

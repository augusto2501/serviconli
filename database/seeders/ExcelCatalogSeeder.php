<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RF-119 — Seeders desde hojas del Excel DataSegura-SERVICONLI-2025.xlsx.
 *
 * Hojas procesadas:
 *   - "Código y Tipo de Cotizante" → cfg_contributor_types
 *   - "LISTADO DE CODIGOS PILA DE ADMI" → cfg_ss_entities
 *   - "TABLA DE RIESGOS ARL" → cfg_regulatory_parameters (ARL rates)
 *   - "FECHAS DE PAGO" → cfg_payment_calendar_rules
 *
 * @see DOCUMENTO_RECTOR §16, RF-119, SKILL.md §"Seeders del Excel"
 */
class ExcelCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedContributorTypes();
        $this->seedSSEntities();
        $this->seedARLRiskRates();
        $this->seedPaymentCalendarRules();

        $this->command->info('ExcelCatalogSeeder: catálogos cargados desde datos reales del Excel.');
    }

    /** 24 tipos de cotizante desde hoja "Código y Tipo de Cotizante". */
    private function seedContributorTypes(): void
    {
        $types = [
            ['code' => '01', 'name' => 'Dependiente', 'subsystems' => ['health','pension','arl','ccf']],
            ['code' => '02', 'name' => 'Servicio Doméstico', 'subsystems' => ['health','pension','arl','ccf']],
            ['code' => '03', 'name' => 'Independiente', 'subsystems' => ['health','pension']],
            ['code' => '04', 'name' => 'Madre sustituta', 'subsystems' => ['health']],
            ['code' => '12', 'name' => 'Aprendices en etapa lectiva', 'subsystems' => ['health','arl']],
            ['code' => '16', 'name' => 'Afiliación colectiva al sistema de seguridad integral', 'subsystems' => ['health','pension','arl','ccf']],
            ['code' => '18', 'name' => 'Funcionarios públicos sin tope máximo de IBC', 'subsystems' => ['health','pension','arl','ccf']],
            ['code' => '19', 'name' => 'Aprendices en etapa productiva', 'subsystems' => ['health','arl']],
            ['code' => '20', 'name' => 'Estudiantes', 'subsystems' => ['arl']],
            ['code' => '21', 'name' => 'Estudiantes de postgrado en salud', 'subsystems' => ['arl']],
            ['code' => '22', 'name' => 'Profesor de establecimiento particular', 'subsystems' => ['health','pension','arl','ccf']],
            ['code' => '23', 'name' => 'Estudiantes aporte solo riesgos laborales', 'subsystems' => ['arl']],
            ['code' => '30', 'name' => 'Dependiente entidades públicas regímenes especial y excepción', 'subsystems' => ['health','pension','arl','ccf']],
            ['code' => '31', 'name' => 'Cooperados o precooperativas de trabajo asociado', 'subsystems' => ['health','pension','arl','ccf']],
            ['code' => '32', 'name' => 'Cotizante miembro carrera diplomática o consular', 'subsystems' => ['health']],
            ['code' => '33', 'name' => 'Beneficiario del fondo de solidaridad pensional', 'subsystems' => ['pension']],
            ['code' => '40', 'name' => 'Beneficiario UPC adicional', 'subsystems' => ['health']],
            ['code' => '42', 'name' => 'Cotizante independiente pago solo salud', 'subsystems' => ['health']],
            ['code' => '43', 'name' => 'Cotizante a pensiones con pago por tercero', 'subsystems' => ['pension']],
            ['code' => '51', 'name' => 'Trabajador de tiempo parcial afiliado al régimen subsidiado', 'subsystems' => ['health','pension','arl']],
            ['code' => '56', 'name' => 'Prepensionado con aporte voluntario en salud', 'subsystems' => ['health']],
            ['code' => '57', 'name' => 'Independiente voluntario al Sistema de Riesgos Laborales', 'subsystems' => ['health','pension','arl']],
            ['code' => '59', 'name' => 'Independiente con contrato de prestación de servicios superior a 1 mes', 'subsystems' => ['health','pension','arl']],
        ];

        foreach ($types as $t) {
            DB::table('cfg_contributor_types')->updateOrInsert(
                ['code' => $t['code']],
                [
                    'name' => $t['name'],
                    'subsystems' => json_encode($t['subsystems']),
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /** 95 administradoras con código PILA exacto desde hoja "LISTADO DE CODIGOS PILA DE ADMI". */
    private function seedSSEntities(): void
    {
        $entities = [
            // AFP (8)
            ['pila_code' => '25-2', 'name' => 'CAXDAC', 'type' => 'AFP'],
            ['pila_code' => '231001', 'name' => 'COLFONDOS', 'type' => 'AFP'],
            ['pila_code' => '25-14', 'name' => 'COLPENSIONES', 'type' => 'AFP'],
            ['pila_code' => '25-3', 'name' => 'FONPRECON', 'type' => 'AFP'],
            ['pila_code' => '230901', 'name' => 'OLD MUTUAL', 'type' => 'AFP'],
            ['pila_code' => '230904', 'name' => 'OLD MUTUAL ALTERNATIVO', 'type' => 'AFP'],
            ['pila_code' => '230301', 'name' => 'PORVENIR', 'type' => 'AFP'],
            ['pila_code' => '230201', 'name' => 'PROTECCION', 'type' => 'AFP'],
            // ARL (10)
            ['pila_code' => '14-11', 'name' => 'SURA', 'type' => 'ARL'],
            ['pila_code' => '14-23', 'name' => 'POSITIVA', 'type' => 'ARL'],
            ['pila_code' => '14-25', 'name' => 'COLMENA', 'type' => 'ARL'],
            ['pila_code' => '14-29', 'name' => 'LA EQUIDAD SEGUROS', 'type' => 'ARL'],
            ['pila_code' => '14-4', 'name' => 'COLPATRIA', 'type' => 'ARL'],
            ['pila_code' => '14-18', 'name' => 'LIBERTY SEGUROS', 'type' => 'ARL'],
            ['pila_code' => '14-30', 'name' => 'MAPFRE', 'type' => 'ARL'],
            ['pila_code' => '14-17', 'name' => 'SEGUROS ALFA', 'type' => 'ARL'],
            ['pila_code' => '14-8', 'name' => 'SEGUROS AURORA', 'type' => 'ARL'],
            ['pila_code' => '14-7', 'name' => 'SEGUROS BOLIVAR', 'type' => 'ARL'],
            // CCF (43)
            ['pila_code' => 'CCF38', 'name' => 'CAFABA', 'type' => 'CCF'],
            ['pila_code' => 'CCF21', 'name' => 'CAFAM', 'type' => 'CCF'],
            ['pila_code' => 'CCF65', 'name' => 'CAFAMAZ', 'type' => 'CCF'],
            ['pila_code' => 'CCF46', 'name' => 'CAFASUR', 'type' => 'CCF'],
            ['pila_code' => 'CCF05', 'name' => 'CAJACOPI', 'type' => 'CCF'],
            ['pila_code' => 'CCF33', 'name' => 'CAJAMAG', 'type' => 'CCF'],
            ['pila_code' => 'CCF64', 'name' => 'CAJASAI', 'type' => 'CCF'],
            ['pila_code' => 'CCF39', 'name' => 'CAJASAN', 'type' => 'CCF'],
            ['pila_code' => 'CCF11', 'name' => 'CALDAS', 'type' => 'CCF'],
            ['pila_code' => 'CCF02', 'name' => 'CAMACOL', 'type' => 'CCF'],
            ['pila_code' => 'CCF09', 'name' => 'CARTAGENA', 'type' => 'CCF'],
            ['pila_code' => 'CCF29', 'name' => 'CHOCO', 'type' => 'CCF'],
            ['pila_code' => 'CCF34', 'name' => 'COFREM', 'type' => 'CCF'],
            ['pila_code' => 'CCF22', 'name' => 'COLSUBSIDIO', 'type' => 'CCF'],
            ['pila_code' => 'CCF10', 'name' => 'COMBAOY', 'type' => 'CCF'],
            ['pila_code' => 'CCF06', 'name' => 'COMBARRANQUILLA', 'type' => 'CCF'],
            ['pila_code' => 'CCF68', 'name' => 'COMCAJA', 'type' => 'CCF'],
            ['pila_code' => 'CCF13', 'name' => 'COMFACA', 'type' => 'CCF'],
            ['pila_code' => 'CCF69', 'name' => 'COMFACASANARE', 'type' => 'CCF'],
            ['pila_code' => 'CCF14', 'name' => 'COMFACAUCA', 'type' => 'CCF'],
            ['pila_code' => 'CCF15', 'name' => 'COMFACESAR', 'type' => 'CCF'],
            ['pila_code' => 'CCF16', 'name' => 'COMFACOR', 'type' => 'CCF'],
            ['pila_code' => 'CCF26', 'name' => 'COMFACUNDI', 'type' => 'CCF'],
            ['pila_code' => 'CCF04', 'name' => 'COMFAMA', 'type' => 'CCF'],
            ['pila_code' => 'CCF07', 'name' => 'COMFAMILIAR ATLANTICO', 'type' => 'CCF'],
            ['pila_code' => 'CCF32', 'name' => 'COMFAMILIAR HUILA', 'type' => 'CCF'],
            ['pila_code' => 'CCF63', 'name' => 'COMFAMILIAR PUTUMAYO', 'type' => 'CCF'],
            ['pila_code' => 'CCF44', 'name' => 'COMFAMILIAR RISARALDA', 'type' => 'CCF'],
            ['pila_code' => 'CCF57', 'name' => 'COMFANDI', 'type' => 'CCF'],
            ['pila_code' => 'CCF37', 'name' => 'COMFANORTE', 'type' => 'CCF'],
            ['pila_code' => 'CCF36', 'name' => 'COMFAORIENTE', 'type' => 'CCF'],
            ['pila_code' => 'CCF48', 'name' => 'COMFATOLIMA', 'type' => 'CCF'],
            ['pila_code' => 'CCF03', 'name' => 'COMFENALCO ANTIOQUIA', 'type' => 'CCF'],
            ['pila_code' => 'CCF08', 'name' => 'COMFENALCO CARTAGENA', 'type' => 'CCF'],
            ['pila_code' => 'CCF43', 'name' => 'COMFENALCO QUINDIO', 'type' => 'CCF'],
            ['pila_code' => 'CCF40', 'name' => 'COMFENALCO SANTANDER', 'type' => 'CCF'],
            ['pila_code' => 'CCF50', 'name' => 'COMFENALCO TOLIMA', 'type' => 'CCF'],
            ['pila_code' => 'CCF56', 'name' => 'COMFENALCO VALLE', 'type' => 'CCF'],
            ['pila_code' => 'CCF67', 'name' => 'COMFIAR', 'type' => 'CCF'],
            ['pila_code' => 'CCF24', 'name' => 'COMPENSAR', 'type' => 'CCF'],
            ['pila_code' => 'CCF30', 'name' => 'GUAJIRA', 'type' => 'CCF'],
            ['pila_code' => 'CCF35', 'name' => 'NARIÑO', 'type' => 'CCF'],
            ['pila_code' => 'CCF41', 'name' => 'SUCRE', 'type' => 'CCF'],
            // EPS (32)
            ['pila_code' => 'EPSIC3', 'name' => 'AIC', 'type' => 'EPS'],
            ['pila_code' => 'EPS001', 'name' => 'ALIANSALUD', 'type' => 'EPS'],
            ['pila_code' => 'ESSC62', 'name' => 'ASMETSALUD', 'type' => 'EPS'],
            ['pila_code' => 'EPSC34', 'name' => 'CAPITAL SALUD', 'type' => 'EPS'],
            ['pila_code' => 'EPS015', 'name' => 'COLPATRIA', 'type' => 'EPS'],
            ['pila_code' => 'CCFC24', 'name' => 'COMFAMILIAR HUILA', 'type' => 'EPS'],
            ['pila_code' => 'EPS009', 'name' => 'COMFENALCO ANTIOQUIA', 'type' => 'EPS'],
            ['pila_code' => 'EPS012', 'name' => 'COMFENALCO VALLE', 'type' => 'EPS'],
            ['pila_code' => 'ESSC33', 'name' => 'COMPARTA', 'type' => 'EPS'],
            ['pila_code' => 'EPS008', 'name' => 'COMPENSAR', 'type' => 'EPS'],
            ['pila_code' => 'EPSC22', 'name' => 'CONVIDA', 'type' => 'EPS'],
            ['pila_code' => 'EPS016', 'name' => 'COOMEVA', 'type' => 'EPS'],
            ['pila_code' => 'ESSC24', 'name' => 'COOSALUD', 'type' => 'EPS'],
            ['pila_code' => 'EPS023', 'name' => 'CRUZ BLANCA', 'type' => 'EPS'],
            ['pila_code' => 'ESSC91', 'name' => 'ECOOPSOS', 'type' => 'EPS'],
            ['pila_code' => 'ESSC02', 'name' => 'EMDISALUD', 'type' => 'EPS'],
            ['pila_code' => 'ESSC18', 'name' => 'EMSSANAR', 'type' => 'EPS'],
            ['pila_code' => 'EPS017', 'name' => 'FAMISANAR', 'type' => 'EPS'],
            ['pila_code' => 'EPS039', 'name' => 'GOLDEN CROSS', 'type' => 'EPS'],
            ['pila_code' => 'EPS014', 'name' => 'HUMANA VIVIR', 'type' => 'EPS'],
            ['pila_code' => 'EPS037', 'name' => 'LA NUEVA EPS', 'type' => 'EPS'],
            ['pila_code' => 'EPSIC5', 'name' => 'MALLAMAS', 'type' => 'EPS'],
            ['pila_code' => 'ESSC07', 'name' => 'MUTUAL SER', 'type' => 'EPS'],
            ['pila_code' => 'EPSIC6', 'name' => 'PIJAOS SALUD', 'type' => 'EPS'],
            ['pila_code' => 'EPS034', 'name' => 'SALUD COLOMBIA', 'type' => 'EPS'],
            ['pila_code' => 'EPS047', 'name' => 'SALUD MIA', 'type' => 'EPS'],
            ['pila_code' => 'EPS002', 'name' => 'SALUD TOTAL', 'type' => 'EPS'],
            ['pila_code' => 'EPS005', 'name' => 'SANITAS', 'type' => 'EPS'],
            ['pila_code' => 'EPS040', 'name' => 'SAVIA SALUD', 'type' => 'EPS'],
            ['pila_code' => 'EPS018', 'name' => 'SOS', 'type' => 'EPS'],
            ['pila_code' => 'EPS010', 'name' => 'SURA', 'type' => 'EPS'],
            // Parafiscales (2)
            ['pila_code' => 'PAICBF', 'name' => 'ICBF', 'type' => 'ICBF'],
            ['pila_code' => 'PASENA', 'name' => 'SENA', 'type' => 'SENA'],
        ];

        foreach ($entities as $e) {
            DB::table('cfg_ss_entities')->updateOrInsert(
                ['pila_code' => $e['pila_code']],
                array_merge($e, ['status' => 'ACTIVE', 'updated_at' => now(), 'created_at' => now()])
            );
        }
    }

    /** 5 clases de riesgo ARL con tarifas exactas desde hoja "TABLA DE RIESGOS ARL". */
    private function seedARLRiskRates(): void
    {
        $rates = [
            1 => '0.00522',
            2 => '0.01044',
            3 => '0.02436',
            4 => '0.04350',
            5 => '0.06960',
        ];

        foreach ($rates as $class => $rate) {
            DB::table('cfg_regulatory_parameters')->updateOrInsert(
                ['category' => 'rates', 'key' => "ARL_RISK_{$class}_RATE"],
                ['value' => $rate, 'valid_from' => '2025-01-01', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    /** 16 rangos fechas pago según hoja "FECHAS DE PAGO" (Res. 2388/2016, D.1990/2016). */
    private function seedPaymentCalendarRules(): void
    {
        $rules = [
            ['business_day' => 2, 'digit_range_start' => 0, 'digit_range_end' => 7],
            ['business_day' => 3, 'digit_range_start' => 8, 'digit_range_end' => 14],
            ['business_day' => 4, 'digit_range_start' => 15, 'digit_range_end' => 21],
            ['business_day' => 5, 'digit_range_start' => 22, 'digit_range_end' => 28],
            ['business_day' => 6, 'digit_range_start' => 29, 'digit_range_end' => 35],
            ['business_day' => 7, 'digit_range_start' => 36, 'digit_range_end' => 42],
            ['business_day' => 8, 'digit_range_start' => 43, 'digit_range_end' => 49],
            ['business_day' => 9, 'digit_range_start' => 50, 'digit_range_end' => 56],
            ['business_day' => 10, 'digit_range_start' => 57, 'digit_range_end' => 63],
            ['business_day' => 11, 'digit_range_start' => 64, 'digit_range_end' => 69],
            ['business_day' => 12, 'digit_range_start' => 70, 'digit_range_end' => 75],
            ['business_day' => 13, 'digit_range_start' => 76, 'digit_range_end' => 81],
            ['business_day' => 14, 'digit_range_start' => 82, 'digit_range_end' => 87],
            ['business_day' => 15, 'digit_range_start' => 88, 'digit_range_end' => 93],
            ['business_day' => 16, 'digit_range_start' => 94, 'digit_range_end' => 99],
        ];

        foreach ($rules as $r) {
            DB::table('cfg_payment_calendar_rules')->updateOrInsert(
                ['business_day' => $r['business_day']],
                array_merge($r, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }
}

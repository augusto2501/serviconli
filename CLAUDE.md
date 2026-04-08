# CLAUDE.md — Guía Maestra de Agente
# Serviconli — Sistema Integral de Gestión de Seguridad Social
# Auto-cargado en toda conversación. LEER ANTES DE CUALQUIER ACCIÓN.

## Proyecto

**Serviconli** (NIT 900966567-4) es un intermediario de seguridad social en Armenia, Quindío.
Reemplaza tres sistemas legados: Excel "DataSegura-SERVICONLI-2025", Access "AplicativoV6.accdb"
(113 tablas, 656 queries, 126 módulos VBA, ~15.000 líneas) y el Convertidor_ARUS.xlsm.
Gestiona ~891 afiliados (664 activos) con flujo completo PILA: afiliación → liquidación → archivo PILA → facturación → cuadre de caja.

## Stack

```
Backend:  Laravel 11, PHP 8.3+, MySQL 8
Frontend: Vue 3 + Vuetify + Tailwind CSS (Inertia.js)
Auth:     Laravel Sanctum
Testing:  PHPUnit (Feature + Unit)
PDF:      barryvdh/laravel-dompdf
Excel:    OpenSpout
Patrón:   Modular Monolith DDD — 13 Bounded Contexts
```

## Documentos de Referencia (LEER ANTES DE IMPLEMENTAR)

| Documento | Propósito |
|-----------|-----------|
| `DOCUMENTO_RECTOR_DEFINITIVO_V5_COMPLETO.md` | Fuente de verdad — toda decisión de diseño |
| `REQUISITOS_FUNCIONALES_SERVICONLI.md` | 125 RFs — lo que debe hacer el sistema |
| `LISTADO_FUNCIONALIDADES_SERVICONLI.md` | 18 módulos — detalle operativo completo |
| `SKILL.md` | Dominio, fórmulas, datos reales, formato ARUS, 24 casos de pago |
| `BACKLOG.md` | Cola priorizada de sprints pendientes |
| `CUMPLIMIENTO_RF_MATRIX.md` | Estado actual RF × implementación |

## Arquitectura — 13 Módulos DDD

```
app/Modules/
├── RegulatoryEngine/    Motor normativo, fórmulas PILA, strategies, RoundingEngine
├── Affiliates/          Personas, wizard afiliación, reingreso, novedades, mora, certificados
├── Affiliations/        Perfil SS versionado, vínculos cotizante-pagador
├── Employers/           Pagadores, validación NIT colombiano
├── PILALiquidation/     Liquidación individual/lotes, archivo PILA (ARUS + XLSX)
├── Billing/             Cuentas cobro (3 modos), recibos, cotizaciones
├── CashReconciliation/  Cuadre de caja diario (3 líneas + cierre)
├── Disabilities/        Incapacidades con prórrogas y CIE-10          [PENDIENTE]
├── Advisors/            Asesores, 2 tipos de comisión                  [PENDIENTE]
├── ThirdParties/        Terceros, consignaciones bancarias             [PENDIENTE]
├── Documents/           Gestión documental, 7 templates contratos      [PENDIENTE]
├── Communications/      WhatsApp Business, notificaciones internas     [PENDIENTE]
└── Security/            Auth, RBAC 5 roles, auditoría, Habeas Data     [PARCIAL]
```

## Prefijos de Tablas BD

```
cfg_*         → Normativo y configuración
afl_*         → Afiliados y personas
pay_*         → Liquidación PILA
bill_*        → Facturación y cartera
sec_*         → Seguridad y auditoría
wf_*          → Workflows (enrollment, reentry)
cash_*        → Cuadre de caja
doc_*         → Documentos y contratos
gdpr_*        → Habeas Data y consentimientos
comm_*        → Comunicaciones
pila_*        → Archivo PILA generado
empl_*        → Empleadores/pagadores
disability_*  → Incapacidades
adv_*         → Asesores y comisiones
third_*       → Terceros y consignaciones
```

---

## REGLAS ABSOLUTAS (NUNCA VIOLAR)

### R-01: CERO Hardcoding
- NUNCA poner valores monetarios, tasas o porcentajes en código fuente
- TODO valor normativo va en `cfg_regulatory_parameters` con `valid_from`/`valid_until`
- Un cambio regulatorio **NUNCA** debe requerir modificar código
- Ejemplos prohibidos: `$tasa = 0.125`, `SMMLV = 1423500`, `const ARL_RIESGO_I = 0.00522`

### R-02: Montos como INT
- TODOS los montos en pesos colombianos se almacenan como `INT UNSIGNED`
- SIN decimales para valores monetarios en PHP ni en BD
- Tarifas/porcentajes usan `DECIMAL(8,6)` en BD
- Columna correcta: `total_pesos INT UNSIGNED NOT NULL`
- Columna incorrecta: `total DECIMAL(12,2)` — **PROHIBIDA**

### R-03: RoundingEngine SIEMPRE
- NUNCA usar `round()`, `ceil()`, `floor()` nativos de PHP para cálculos PILA
- SIEMPRE usar `RoundingEngine::roundIBC()` → al millar superior (no HALF_UP)
- SIEMPRE usar `RoundingEngine::roundLegacy()` → al centenar superior (lógica Access)
- `RoundingEngine::roundPILA()` → HALF_UP estándar solo para validación cruzada
- `RoundingEngine::adjustBatchRounding()` → ajuste última línea si suma ≠ total

### R-04: Perfil SS Versionado — NUNCA sobreescribir
- NUNCA asignar directamente: `$perfil->eps_entity_id = $nuevo`
- SIEMPRE usar: `$profileService->versionProfileForTransfer($affiliate, 'eps_entity_id', $id)`
- SIEMPRE usar: `$profileService->versionProfileForSalaryChange($affiliate, $nuevoIBC)`
- Cada cambio de entidad (TAE/TAP) o salario (VSP) = nueva versión con `valid_from`/`valid_until`

### R-05: Mora escalonada — siempre UN solo nivel
- Escalada/desescalada SIEMPRE usa `AffiliateStatusMachine`
- NUNCA asignar `mora_status` o `status_id` directamente
- NUNCA saltar niveles: de MORA_60 solo se puede ir a MORA_30 o MORA_90
- Escalada: `$statusMachine->escalate($affiliate)`
- Desescalada: `$statusMachine->deescalate($affiliate)` o `::activateOnFirstPayment()`

### R-06: Trazabilidad obligatoria
- Toda clase de servicio DEBE incluir `@see DOCUMENTO_RECTOR §X` en el docblock
- Toda regla de negocio DEBE citar `// RF-XXX` o `// RN-XX` en línea
- Todo portado del Access DEBE citar `// Portado de Access: Form_005 [referencia]`
- Las migraciones DEBE incluir comentario `// DOCUMENTO_RECTOR §X Grupo {letra}`

### R-07: Excepciones antes de reglas estándar
- El motor de cálculo SIEMPRE llama a `OperationalExceptionService::getActive()` ANTES de calcular
- Si hay excepción activa `FEE_OVERRIDE`, `MORA_EXEMPT`, etc. → aplicar y registrar
- Campo `has_exception` + `exception_id` en la línea de liquidación cuando aplica

### R-08: Soft delete con motivo
- NUNCA eliminar físicamente registros de seguridad social
- `SoftDeletes` en TODOS los modelos de negocio críticos
- Al eliminar: siempre registrar `deleted_reason` (texto) + `deleted_by` (user_id)
- Los registros deben mantenerse mínimo 5 años (requerimiento legal colombiano)

### R-09: Tests son obligatorios
- TODA nueva funcionalidad incluye test Feature o Unit
- `tests/Feature/{Módulo}/{Feature}Test.php` usando `RefreshDatabase`
- Los tests existentes **NO** deben romperse — zero regresión
- Tests deben cubrir: happy path + casos de error + efectos secundarios

### R-10: Strategy Pattern para tipos de cotizante
- NUNCA hacer `if ($tipo == '01')` o `switch($tipo)` en servicios de cálculo
- SIEMPRE delegar a `StrategyResolver::resolve($tipoCode)->calculate($context)`
- Nuevos tipos de cotizante = nueva Strategy que implementa `ContributorCalculationStrategy`

---

## ANTIPATRONES PROHIBIDOS

| Antipatrón | Forma correcta |
|------------|----------------|
| `round($monto * 0.125)` | `RoundingEngine::roundLegacy(intval($ibc * $tasa))` |
| `$perfil->eps_entity_id = $id; $perfil->save()` | `$profileService->versionProfileForTransfer($afl, 'eps_entity_id', $id)` |
| `$afl->mora_status = 'ACTIVO'; $afl->save()` | `$statusMachine->deescalate($afl)` |
| `if ($cotizante == '01') { $ccf = $ibc * 0.04; }` | `$strategyResolver->resolve('01')->calculate($ctx)` |
| `const SMMLV = 1423500;` | `$repo->get('rates', 'SMMLV_PESOS', $fecha)` |
| `Column: total DECIMAL(12,2)` | `Column: total_pesos INT UNSIGNED` |
| `Affiliate::find($id)->delete()` | `$affiliate->softDeleteWithReason($reason, $userId)` |
| Migración sin prefijo de tabla | `cfg_`, `afl_`, `pay_`, `bill_`... |
| `$perfil->ibc = $nuevo; $perfil->save()` | `$profileService->versionProfileForSalaryChange($afl, $ibc)` |
| Test sin `RefreshDatabase` | Siempre `use RefreshDatabase;` en Feature tests |
| Lógica en Controller | Mover a Service; Controller solo orquesta |
| `sleep(1)` en tests | `Carbon::setTestNow(now()->addSeconds(1))` |
| Valor fijo en seeder hardcodeado | Seeder lee de catálogos o recibe parámetros |

---

## ORDEN DE CÁLCULO PILA (Form_005 Access — tolerancia cero)

```
1.  IBC      = roundIBC(intval(($salario / 30) * $diasEPS))
2.  Salud    = roundLegacy($ibc * $tasaEPS)
3.  ibc2     = roundIBC(intval(($salario / 30) * $diasAFP))   // puede diferir si RET-P
4.  Pensión  = roundLegacy($ibc2 * $tasaAFP)  // tipo 51: ($salario/4) * $semanas
5.  ARL      = roundLegacy(intval($ibc * $tasaARL))   // subtipo 11 = $0
6.  CCF      = roundLegacy($ibc * $tasaCCF)           // dep=4%, indep=2%
7.  Solidaridad = $ibc * escala6Tramos($ibc, $smmlv)
8.  Admin    = roundLegacy(intval(($valorAdmin / 30) * $diasEPS))
9.  TotalSS  = Salud + Pensión + ARL + CCF + Solidaridad
10. Mora     = round((((TotalSS / 30) * $tasaDiaria) * $diasMora) / 100, 0) * 100
11. Total    = TotalSS + Admin + Mora
```

---

## ESTADO DE IMPLEMENTACIÓN

### Completo — no modificar sin justificación documentada

- **Motor PILA**: `PILACalculationService`, 5 Strategies, `RoundingEngine`, `SolidarityFundCalculator`, `MoraInterestService`, `OperationalExceptionService`
- **Calendarios**: `PaymentCalendarService`, `ColombianHolidayChecker`, `PeriodDeterminationService`
- **Afiliados**: Wizard 6 pasos, Reingreso 3 pasos + confirm, `Ficha360ViewBuilder`, notas, beneficiarios, portal credentials, `RadicadoNumberGenerator`
- **Afiliaciones**: `SocialSecurityProfileService` con versionado temporal
- **Liquidación**: `ContributionService`, `BatchLiquidationService`, `PilaLiquidationStateService`, 4 estrategias de pago (`PaymentMethodResolver`)
- **Archivo PILA**: `PILAFileGenerationService`, `ARUSFileFormatter` (359+687 chars), `XLSXFileFormatter`, `PILACharNormalizer`
- **Facturación**: `CuentaCobroService`, `CuentaCobroPaymentService`, `ReciboCajaService`, `NumberToWordsService`, `ConsecutiveService`, `InvoiceCancellationService`
- **Caja**: `DailyReconciliationService`, `DailyCloseService`, `DailyCloseCommand`
- **Excepciones**: `OperationalExceptionService` + `cfg_operational_exceptions` (8 tipos)
- **Empleadores**: `EmployerController`, `EmployerNitValidationService`, NIT módulo 11
- **Maquina de estados**: `AffiliateStatusMachine` (9 estados, escalada/desescalada)
- **Cotizador**: `QuotationService`, `QuotationController` *(Sprint G, pendiente commit)*

### Sprint G en progreso — pendiente commitear

```
app/Modules/Affiliates/
  Services/NoveltyService.php             (TAE/TAP/VSP/VST/RET con efectos)
  Services/MoraPeriodTransitionService.php
  Services/PaymentCertificateService.php
  Controllers/NoveltyController.php
  Controllers/PaymentCertificateController.php
  Events/ARLRetirementReminderRequested.php
  Listeners/LogARLRetirementReminder.php
  Commands/TransicionPeriodoCommand.php
  Commands/MoraDetectCommand.php
app/Modules/Billing/
  Services/QuotationService.php
  Controllers/QuotationController.php
tests/Feature/Affiliates/
  SprintGNoveltyCertificateTest.php
  MoraTransicionCommandTest.php
tests/Feature/Billing/
  QuotationApiTest.php
```

### Pendiente — ver BACKLOG.md para priorización

- RF-061: 13 tipos novedad restantes (ING, LMA, LPA, IGE, IRL, SLN, LLU, TDE, TDP, VTE, AVP, VCT, COR)
- RF-063: Retiro por mora (1 día, provisiona deuda, admin=$0)
- RF-054: PDF cotizador con branding Serviconli
- RF-030: Contratos multi-ingreso independientes (IBC al 40%)
- Módulos completos pendientes: `Disabilities`, `Advisors`, `ThirdParties`, `Documents`, `Communications`
- RF-108: RBAC completo con Spatie Permission (5 roles)
- RF-109: Tabla `sec_audit_logs` completa
- RF-114-115: Dashboard gerencial + reportes operativos
- RF-118-120: ETL Excel (891 registros) + Access histórico

---

## CHECKLIST ANTES DE CADA COMMIT

```
[ ] ¿Todos los montos monetarios son INT (sin DECIMAL)?
[ ] ¿Se usa RoundingEngine en lugar de round()/ceil()/floor() nativo?
[ ] ¿Los parámetros normativos vienen de cfg_regulatory_parameters con fecha?
[ ] ¿Los cambios de perfil SS crean nueva versión (no sobreescriben)?
[ ] ¿La mora sube/baja exactamente UN solo nivel via AffiliateStatusMachine?
[ ] ¿El motor consulta OperationalExceptionService ANTES de las reglas estándar?
[ ] ¿Hay comentarios // DOCUMENTO_RECTOR §X y // RF-XXX donde corresponde?
[ ] ¿Hay test Feature o Unit para cada funcionalidad nueva?
[ ] ¿php artisan test pasa completamente (sin regresiones)?
[ ] ¿Las nuevas tablas usan el prefijo correcto (cfg_, afl_, pay_, bill_...)?
[ ] ¿Los soft deletes incluyen campo de motivo y usuario?
[ ] ¿CERO valores hardcodeados (tasas, SMMLV, días, porcentajes) en código?
[ ] ¿CUMPLIMIENTO_RF_MATRIX.md actualizado con el nuevo estado?
```

---

## COMANDOS ÚTILES

```bash
# Tests
php artisan test                                          # Todos los tests
php artisan test --filter=SprintG                        # Filtrar por nombre
php artisan test tests/Feature/Affiliates/               # Módulo específico

# BD
php artisan migrate:fresh --seed                         # Reset completo
php artisan migrate                                       # Solo migraciones nuevas

# Comandos de negocio
php artisan pila:transicion-periodo 2026-01 --dry-run   # Simular mora (sin cambios)
php artisan pila:transicion-periodo 2026-01             # Ejecutar transición mora
php artisan mora:detect --periodo=2026-01               # Detectar afiliados sin PILA
php artisan daily:close                                  # Ejecutar cuadre de caja
php artisan pila:generar-planilla                        # Generar archivo PILA
```

---

## CONVENCIONES DE CÓDIGO

```php
// Docblock obligatorio en todo Service
/**
 * Descripción del servicio.
 *
 * @see DOCUMENTO_RECTOR §X.Y
 */

// Comentario de trazabilidad en reglas de negocio
/** RF-031: IBC al millar superior — portado de Access Form_005 línea 11262 */
$ibc = $this->rounding->roundIBC(intval(($salario / 30) * $dias));

// Comentario en migraciones
// DOCUMENTO_RECTOR §4 Grupo B — Tabla de afiliados

// Leer parámetro regulatorio con fecha
$tasa = $this->repo->get('rates', 'SALUD_TOTAL_PERCENT', $onDate);
```

---

*Última actualización: Sprint G (abril 2026)*
*Proyecto Serviconli — Armenia, Quindío, Colombia*

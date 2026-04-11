# Matriz de Cumplimiento RF × Estado — Serviconli
# Referencia: REQUISITOS_FUNCIONALES_SERVICONLI.md
# Estados: No iniciado | En curso | Hecho (parcial) | Hecho | N/A
# Actualizado: Sprint K completo — RF-103, RF-114, RF-115, RF-018 (abril 2026)

---

## Módulo 1 — Gestión de Afiliados

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-001 | Hecho (parcial) | Wizard backend 6 pasos: `POST /api/enrollment/step-1..5` + `confirm`. Persistencia en `wf_enrollment_processes`. Comisión asesor opcional vía `advisor_id` en paso 5 + `PostEnrollmentCompletionService`. Pendiente: PDF contrato, WhatsApp |
| RF-002 | Hecho | `AffiliateClientType` enum: SERVICONLI / INDEPENDIENTE / DEPENDIENTE / COLOMBIANO_EXTERIOR |
| RF-003 | Hecho (parcial) | Catálogo `cfg_contributor_types` con 7 tipos activos + 17 adicionales. Motor soporta todos. Sin UI admin CRUD |
| RF-004 | Hecho (parcial) | Subtipos 11 y 12 en `afl_affiliates.subtipo`; lógica en strategies. Sin UI de gestión |
| RF-005 | Hecho | `Rule::in(['CC','CE','TI','PA','PPT','PTT','NIT'])` en paso 2 del wizard |
| RF-006 | Hecho | Campos obligatorios en paso 2: tipo doc, número, primer nombre, apellido, sexo, dirección, `required_without_all` para teléfonos |
| RF-007 | Hecho | `is_foreigner` y `is_type_51` en `wf_enrollment_processes` paso 1 |
| RF-008 | Hecho | `RadicadoNumberGenerator`: `radicado_yearly_sequences` + lock concurrencia; formato `RAD-{YYYY}-{NNNNNN}` |
| RF-009 | Hecho (parcial) | Paso 6: `habeas_data_accepted` obligatorio; `gdpr_consent_records` con IP, user-agent, `accepted_at`. Pendiente: gestión derechos titular |
| RF-010 | Hecho (parcial) | `PostEnrollmentCompletionService`: comisión nueva, tercero stub, WhatsApp bienvenida/confirmación. PDF contratos/certificados: `ContractPdfService` + `ContractTemplateRegistry` (v1, header `X-Contract-Template-Version`) |
| RF-011 | Hecho | `EnrollmentBillingPreviewService`: doble cálculo (primer mes proporcional + mensual 30 días) vía `PILACalculationService` |
| RF-012 | Hecho | `GET /api/reentry/eligible`: busca estados RETIRADO/INACTIVO por documento |
| RF-013 | Hecho | `POST /api/reentry/step-1..3`: actualiza persona, entidades SS, pagador |
| RF-014 | Hecho | `POST /api/reentry/confirm`: cierra perfil SS anterior, crea nueva versión, recibo tipo "03", estado AFILIADO |
| RF-015 | Hecho (parcial) | `Ficha360ViewBuilder`: persona, perfil SS vigente con pilaCode, pagador, beneficiarios, notas recientes, liquidaciones PILA confirmadas, facturas, excepciones activas, portal credentials. Pendiente: documentos adjuntos |
| RF-016 | Hecho (parcial) | Accesos rápidos en UI Vue (`/afiliados/{id}/ficha`). Actions conectados como "próximamente" hasta flujos definitivos |
| RF-017 | Hecho (parcial) | `GET/POST /api/affiliates/{id}/beneficiaries`: CRUD básico. Alertas automáticas implementadas (RF-018) |
| RF-018 | Hecho | `beneficiaries:alert` command: detecta beneficiarios próximos a cumplir 18 años (30d), certificados de estudiante por vencer, fin de protección. Genera `comm_notifications` + WhatsApp. Scheduler diario a las 07:00. `--dry-run` disponible |
| RF-019 | Hecho | `GET/POST /api/affiliates/{id}/notes`: tipos ADMINISTRATIVA/MÉDICA/GENERAL/PAGO, `user_id` Sanctum |
| RF-020 | Hecho | Notas visibles en `GET /api/affiliates` (conteo) y en ficha 360° (recientes) |
| RF-021 | Hecho (parcial) | `GET /api/affiliates`: nombre completo, cotizante, estado, mora, `paymentIndicator` (SI/NO/ANTICIPADO/NEUTRO), EPS/AFP/ARL/CCF, operador PILA, último período pagado, notas. Pendiente: paridad exacta todas las columnas del Excel |
| RF-022 | Hecho | Filtros: `contributor_type_code`, `payer_id`, `advisor_id`, `pila_operator_code`, `eps/afp/arl/ccf_entity_id`, `payments_on_track` (yes/no/ahead) |
| RF-023 | Hecho | `GET /api/affiliates/export?format=csv\|xlsx` (OpenSpout); búsqueda por nombre/documento |

---

## Módulo 2 — Empleadores

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-024 | Hecho | `empl_employers` + API CRUD completa. Campos: NIT, DV, razón social, representante, CIIU, dirección, ciudad, depto, teléfono, email |
| RF-025 | Hecho | `EmployerNitValidationService`: módulo 11 con pesos `[71,67,59,53,47,43,41,37,29,23,17,13,7,3]`, calcula DV automáticamente |
| RF-026 | Hecho | Normalización 3 formatos NIT: con punto, con guion-DV, solo número |
| RF-027 | Hecho | `generates_cuenta_cobro` en pagador y en `service_contracts`; RN-08: `GenerateCuentaCobroOnBatchConfirm` genera pre-cuenta al confirmar lote si aplica |

---

## Módulo 3 — Afiliaciones y Perfiles SS

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-028 | Hecho | `afl_affiliate_payer` N:M con `valid_from`/`valid_until`, tipo cotizante, salario, cargo, asesor |
| RF-029 | Hecho | `SocialSecurityProfileService`: versionado temporal con `valid_from`/`valid_until`, `versionProfileForTransfer()`, `versionProfileForSalaryChange()`. Nunca sobreescribe |
| RF-030 | Hecho (parcial) | `afl_multi_income_contracts` + `MultiIncomeContractService` (40% ingreso, tope 25 SMMLV). API `POST /api/affiliates/{id}/multi-income-contracts`. Pendiente: usar IBC consolidado como input único en liquidación individual desde UI |

---

## Módulo 4 — Motor de Cálculo Normativo

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-031 | Hecho | `RoundingEngine::roundIBC()`: `intval(($sal/30)*$dias)` al millar superior (mod 1000, no HALF_UP) |
| RF-032 | Hecho | `roundLegacy()` al múltiplo de 100 superior — replica exacta Access |
| RF-033 | Hecho | Pensión normal sobre IBC; tipo 51: `(Salario/4)*semanas` en `TiempoParcialSubsidiadoStrategy` |
| RF-034 | Hecho (parcial) | `ibc2` para días AFP diferentes existe en `PILACalculationService`. Sin tests específicos para retiro tipo P |
| RF-035 | Hecho | Subtipo 11: ARL=$0 automático. Subtipo 12: flag en contexto para ARL riesgo 4 |
| RF-036 | Hecho | `DependienteGeneralStrategy`: CCF 4%; `IndependienteGeneralStrategy`: CCF 2%. Desde `cfg_regulatory_parameters` |
| RF-037 | Hecho | `SolidarityFundCalculator`: 6 tramos ≥4 SMMLV→1% hasta ≥20 SMMLV→2%; tramos desde `cfg_regulatory_parameters` |
| RF-038 | Hecho | Fee admin proporcional `roundLegacy(intval((ValorAdmin/30)*DiasEPS))` |
| RF-039 | Hecho | `MoraInterestService`: `round((((TotalSS/30)*tasa)*dias)/100,0)*100`; base solo SS (sin admin); tasa desde `cfg_regulatory_parameters` |
| RF-040 | Hecho | `TotalPago = TotalSS + Admin + Mora` en secuencia exacta Form_005 |
| RF-041 | Hecho | Todos los montos `INT UNSIGNED` en PHP y BD; tarifas `DECIMAL(8,6)` |
| RF-042 | Hecho | 5 Strategies: `DependienteGeneral`, `IndependienteGeneral`, `TiempoParcialSubsidiado`, `BeneficiarioUPC`, `ContratistaPrestacionServicios` via `StrategyResolver` |
| RF-043 | Hecho | `RoundingEngine`: `roundIBC`, `roundLegacy`, `roundPILA`, `adjustBatchRounding` |
| RF-044 | Hecho (parcial) | `roundPILA()` existe. Pendiente: alerta activa cuando diferencia legacy vs PILA > 1% en período de transición |
| RF-045 | Hecho | `cfg_regulatory_parameters` con `valid_from`/`valid_until`, categoría, clave, tipo dato, base legal |
| RF-046 | Hecho (parcial) | Tabla + seeders completos. Sin UI admin CRUD para usuarios no técnicos |
| RF-047 | Hecho | `PaymentCalendarService`: 16 rangos Res. 2388/2016 desde `cfg_payment_calendar_rules` |
| RF-048 | Hecho | Dependientes: fecha límite mes SIGUIENTE; independientes: mes ACTUAL |
| RF-049 | Hecho | `ColombianHolidayChecker`: sábados, domingos y festivos CO desde `cfg_colombian_holidays` |
| RF-050 | Hecho | `cfg_payment_deadline_overrides`: overrides manuales por período específico |
| RF-051 | Hecho | `PeriodDeterminationService`: sin pagos → mes siguiente + días proporcionales; con pagos → siguiente al último pagado |
| RF-052 | Hecho (parcial) | Detección período adelantado en el servicio. Pendiente: confirmación explícita del usuario antes de proceder |
| RF-053 | Hecho | `QuotationService` (Sprint G): mismas fórmulas del motor real, almacena en `billing_quotations` |
| RF-054 | Hecho | `QuotationService::generatePdf`, vista `resources/views/pdf/quotation.blade.php`, `bill_quotations.pdf_path`, `barryvdh/laravel-dompdf` |

---

## Módulo 5 — Liquidación y Pagos PILA

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-055 | Hecho | `ContributionService`: período automático, perfil SS vigente, detección `generates_cuenta_cobro` |
| RF-056 | Hecho | Validación días < 30 sin novedad = error (excepto tipo 41) en `StoreContributionRequest` |
| RF-057 | Hecho | Validación período duplicado en `ContributionService` |
| RF-058 | Hecho | Normalización días tipo 51 a 7/14/21/30 en `TiempoParcialSubsidiadoStrategy` |
| RF-059 | Hecho (parcial) | Recálculo al cambiar días en el servicio. Pendiente: recálculo en tiempo real en UI Vue |
| RF-060 | Hecho | `UpdateMoraStatusOnPayment` listener en evento `ContributionSaved` |
| RF-061 | Hecho | `NoveltyService`: 18 códigos; efectos perfil para ING, TAE/TDE, TAP/TDP, VSP/VST, VTE, VCT, RET; resto solo registro para liquidación; validación ING+TAE/TDE |
| RF-062 | Hecho | `NoveltyService::processRetirement()`: X=RETIRADO, P=sigue ACTIVO, R=sigue ACTIVO |
| RF-063 | Hecho (parcial) | Causal `MORA_EN_APORTE` → retiro TOTAL forzado; pendiente provisión contable explícita de deuda y reglas admin $0 en Billing |
| RF-064 | Hecho | **Sprint G:** `ARLRetirementReminderRequested` event + `LogARLRetirementReminder` listener para retiro X o R |
| RF-065 | Hecho | **Sprint G:** `NoveltyService::processTransferEPS/AFP()` → `SocialSecurityProfileService::versionProfileForTransfer()` |
| RF-066 | Hecho | **Sprint G:** `NoveltyService::processSalaryChange()` → `SocialSecurityProfileService::versionProfileForSalaryChange()` |
| RF-067 | Hecho | `BatchLiquidationService`: carga afiliados AFILIADO/ACTIVO/SUSPENDIDO/PAGO_MES_SUBSIGUIENTE del pagador |
| RF-068 | Hecho | Días proporcionales para afiliados nuevos en lote + novedad ING automática |
| RF-069 | Hecho | Strategy por tipo cotizante en cada línea; `adjustBatchRounding()` si suma ≠ total |
| RF-070 | Hecho | `LiquidationBatch` model: BORRADOR → PRE_LIQUIDADO → LIQUIDADO → PAGADO → ANULADO |
| RF-071 | Hecho | `AffiliateStatusMachine`: AFILIADO→ACTIVO→SUSPENDIDO→MORA_30→MORA_60→MORA_90→MORA_120→MORA_120_PLUS→RETIRADO |
| RF-072 | Hecho | `MoraPeriodTransitionService` + `pila:transicion-periodo` / `mora:detect`; programación `bootstrap/app.php` (`schedule:run`, env `SCHEDULE_*`) |
| RF-073 | Hecho | `AffiliateStatusMachine::deescalate()`: siempre UN solo nivel hacia abajo |
| RF-074 | Hecho | Al pasar a nivel alerta beneficiarios (MORA_60+ desde debajo), evento `MoraBeneficiaryAlertNeeded` → `SendMoraBeneficiaryWhatsApp` → `comm_whatsapp_logs` (Twilio si hay credenciales, si no `provider=log` estado sent) |
| RF-075 | Hecho (parcial) | `PaymentMethodResolver` + 4 strategies (EFECTIVO/CONSIG/CRÉDITO/CUENTA_COBRO). Pendiente: flujo CONSIGNACIÓN con validación duplicada (Sprint I) |
| RF-076 | Hecho (parcial) | Casos 7 (primer aporte✅), 9 (RET-X✅), 10 (RET-P✅), 11 (RET-R✅), 14 (TAE✅), 15 (TAP✅), 16 (VSP✅), 17 (tipo 51✅), 18 (tipo 40✅), 19 (mora paga✅), 22 (indep actual✅), 23 (subtipo 11✅). Pendientes: 8, 12, 13, 20, 21, 24 |

---

## Módulo 6 — Cartera y Facturación

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-077 | Hecho | `CuentaCobroService`: modos PLENO, SOLO_APORTES, SOLO_AFILIACIONES (`includeAportes` / `includeAdmin`) |
| RF-078 | Hecho (parcial) | Estado draft/borrador existe en `bill_cuentas_cobro`. Separación estricta "no afecta datos" pendiente |
| RF-079 | Hecho (parcial) | Consecutivo `SC-{YYYY}-{NNNN}` funcional vía `ConsecutiveService`. Pendiente: exportación PDF |
| RF-080 | Hecho | `CuentaCobroPaymentService`: Total1 (oportuno) o Total2 (mora); no pagos parciales |
| RF-081 | Hecho | Post-pago: aportes incluidos en planilla PILA, estados afiliados actualizados |
| RF-082 | Hecho | `ConsecutiveService` + `ReciboCajaService`: `RC-{YYYY}-{NNNN}` con reinicio anual y lock concurrencia |
| RF-083 | Hecho | `NumberToWordsService`: montos a letras español colombiano. Detalle por concepto (EPS, AFP, ARL, CCF, Solidaridad, Admin, Afiliación, Intereses) |
| RF-084 | Hecho (parcial) | `InvoiceCancellationService` con campo causal. Validación completa de catálogo causales pendiente |
| RF-085 | Hecho (parcial) | Bloqueo anulación con aportes cargados en lógica del servicio. No completamente enforced en todos los paths |
| RF-086 | Hecho (parcial) | Estructura de 6 cascadas en `InvoiceCancellationService`. No todas implementadas completamente. Sprint I |
| RF-087 | Hecho (parcial) | 4 flujos de pago con `PaymentMethodResolver`. CRÉDITO con CxC y CONSIGNACIÓN con validación duplicada pendientes (Sprint I) |
| RF-088 | Hecho | `MoraInterestService`: base solo SS excluyendo admin explícitamente, tasa parametrizable |

---

## Módulo 7 — Cuadre de Caja

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-089 | Hecho | `DailyReconciliationService`: 3 líneas — `CashReconAffiliations`, `CashReconContributions`, `CashReconCuentas` |
| RF-090 | Hecho | `DailyCloseService` + `DailyCloseCommand`: totaliza 13 conceptos, marca CERRADA, notifica admin |

---

## Módulo 8 — Generación Archivo PILA

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-091 | Hecho | `PILAFileGenerationService`: selección automática ARUS vs XLSX según operador en `cfg_ss_entities` |
| RF-092 | Hecho | `ARUSFileFormatter`: texto plano ANSI sin BOM, sin línea final vacía, sin encabezados |
| RF-093 | Hecho | Registro tipo 01: 359 chars exactos con todos los campos del Rector §8 |
| RF-094 | Hecho | Registro tipo 02: 687 chars, 113 campos posicionales según Res. 2388/2016 Anexo Técnico 2 |
| RF-095 | Hecho | `PILACharNormalizer`: Ñ→N, elimina tildes, solo alfanuméricos y espacios |
| RF-096 | Hecho | 9 pasos implementados en `PILAFileGenerationService::generate()` |

---

## Módulo 9 — Incapacidades

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-097 | Hecho | `dis_affiliate_disabilities`: EPS_GENERAL/ARL_LABOR, `subtype_code`, `cfg_diagnosis_cie10`, fechas, `submitted_documents` JSON; API anidada `/api/affiliates/{id}/disabilities` |
| RF-098 | Hecho | `dis_disability_extensions`; `DisabilityDayCalculator` acumula días corridos; `over_180_alert` cuando total > 180 |

---

## Módulo 10 — Asesores y Comisiones

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-099 | Hecho | `sec_advisors`; CRUD `GET/POST/PUT/DELETE /api/advisors`; `commission_new` / `commission_recurring`; `authorizes_credits`; paso 5 enrollment `advisor_id` opcional; paso 3 reingreso `advisor_id` en `afl_affiliate_payer` |
| RF-100 | Hecho | `bill_advisor_commissions` con `CE-{YYYY}-{NNNN}` (`ConsecutiveService` prefijo CE); tipos NEW/RECURRING; estados CALCULADA → PAGADA/ANULADA (`PATCH /api/advisor-commissions/{id}`); cálculo al confirmar enrollment y al reingreso |

---

## Módulo 11 — Terceros y Consignaciones

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-101 | Hecho (parcial) | `POST /api/third-parties/bank-deposits` → `tp_bank_deposits` (LOCAL/NACIONAL); advertencia `duplicateReferenceWarning` si referencia repetida; excedente vía `expected_amount_pesos` + `surplusPesos` en respuesta |
| RF-102 | Hecho | `tp_advisor_receivables`; alta en reingreso con `payment_method` CREDITO y asesor con `authorizes_credits`; `GET/PATCH /api/third-parties/advisor-receivables` (PENDIENTE → PAGADA/ANULADA) |

---

## Módulo 12 — Documentos y Contratos

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-103 | Hecho (parcial) | 7 entregables PDF vía DomPDF: `operational_clauses`, `legal_association`, `affiliate_declaration`, `affiliation_type_declaration`, `voluntary_withdrawal`, `affiliation_certificate`, `payment_certificate` con `format=full|summary` + `year`/`month` (PILA confirmada). Ruta `GET /api/affiliates/{affiliate}/contract-documents/{code}`. Versionado v1 en registro PHP; almacenamiento histórico firmas pendiente |
| RF-104 | Hecho | `PaymentCertificateService` + JSON `GET .../payment-certificate` + PDF `GET .../payment-certificate/pdf` (`resources/views/pdf/payment-certificate.blade.php`) |
| RF-105 | Hecho | `NumberToWordsService` en módulo Billing |

---

## Módulo 13 — Comunicaciones

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-106 | Hecho | `comm_whatsapp_logs`; `WhatsAppOutboundService` plantillas (welcome, payment_reminder, mora_beneficiary_alert, confirmation); Twilio HTTP opcional (`config/services.php` twilio) |
| RF-107 | Hecho | `comm_notifications`; `GET /api/communications/notifications`, `PATCH .../notifications/{id}` marca leída; política por `user_id` |

---

## Módulo 14 — Seguridad y Auditoría

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-108 | Hecho | `spatie/laravel-permission` v7.2, 5 roles (ADMIN/AFILIACIONES/PAGOS/CARTERA/CONSULTA) con permisos granulares. 10 Policies actualizadas con `hasPermissionTo()`. User model con `HasRoles` trait. |
| RF-109 | Hecho | Tabla `sec_audit_logs` con usuario, acción, modelo, old/new values JSON, IP, user-agent. `Auditable` trait en Affiliate, Person, Employer, Novelty, PilaLiquidation, BillInvoice. `AuditLogService` + `AuditLogController` con API paginada. |
| RF-110 | Hecho | Tabla `gdpr_requests` con 4 tipos (CONSULTA/RECTIFICACION/SUPRESION/REVOCACION). `GdprRequestService` + `GdprRequestController` con API CRUD, resolución y resumen. |
| RF-111 | Hecho | Tabla `sec_credential_access_logs` registra cada acceso/descifrado de credenciales. `CredentialAccessService::logAccess()` y `::decryptAndLog()`. |
| RF-112 | Hecho | `SoftDeletesWithReason` trait con `softDeleteWithReason($reason)` que registra `deleted_reason` + `deleted_by`. Migración agrega columnas a 9 tablas críticas. |
| RF-113 | Hecho | Comando `files:purge` elimina archivos PILA >2 años (configurable `--months`), marca status=PURGED. Soporta `--dry-run`. Registros BD se mantienen indefinidamente. |

---

## Módulo 15 — Reportes y Dashboard

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-114 | Hecho | `GET /api/dashboard`: afiliados (activos/mora/inactivos), recaudo mes actual vs anterior con variación %, planillas (total/confirmadas/archivos), afiliaciones nuevas, distribución por tipo cliente y operador PILA, alertas (mora >90d, beneficiarios 18 años, certificados) |
| RF-115 | Hecho | 6 reportes operativos: `GET /api/reports/daily-contributions`, `/mora` (por nivel + top 20), `/affiliates-by-advisor`, `/affiliates-by-employer`, `/cash-reconciliation` (13 conceptos), `/end-of-day` (resumen ejecutivo) |

---

## Módulo 16 — Configuración y Administración

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-116 | Hecho (parcial) | 25+ catálogos con tablas + seeders completos. Sin UI admin CRUD para usuarios (Sprint L) |
| RF-117 | Hecho | `ConsecutiveService`: RC / SC / RAD / CE con reinicio anual, lock concurrencia, parametrizable desde `cfg_consecutive_formats` |

---

## Módulo 17 — Migración ETL

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-118 | En curso | Stub `etl:migrate-excel {path}` en `routes/console.php`. Implementación real: Sprint M (requiere Excel del cliente) |
| RF-119 | Hecho (parcial) | Seeders: `PaymentCalendarRuleSeeder`, `ContributorTypeSeeder`, festivos CO 2026. Pendiente: 95 administradoras reales, 24 tipos cotizante completos (Sprint M) |
| RF-120 | En curso | Stub `etl:migrate-access {path}`. Implementación real: Sprint M (requiere Access del cliente) |

---

## Módulo 18 — Excepciones Operativas

| RF | Estado | Evidencia / Notas |
|----|--------|-------------------|
| RF-121 | Hecho | `cfg_operational_exceptions` + `OperationalExceptionService`: 8 tipos, `target_type` (AFFILIATE/PAYER/AFFILIATE_PAYER), valor JSON |
| RF-122 | Hecho | `reason TEXT NOT NULL`, `authorized_by` FK a usuario, `valid_from`/`valid_until` obligatorios |
| RF-123 | Hecho | `PILACalculationService` llama `OperationalExceptionService::getActive()` antes de calcular; registra `has_exception` |
| RF-124 | Hecho | `Ficha360ViewBuilder` incluye `operational_exceptions` activas en el payload |
| RF-125 | Hecho (parcial) | Estructura de auditoría presente. Pendiente: integración completa con `sec_audit_logs` (Sprint L) |

---

## Resumen cuantitativo actualizado

| Estado | Cantidad | % |
|--------|----------|---|
| Hecho | 82 | 66 % |
| Hecho (parcial) | 31 | 25 % |
| No iniciado | 12 | 10 % |

**Avance ponderado (parciales al 50%): ~82%**

### Por módulo

| Módulo | RFs | Hecho | Parcial | No iniciado |
|--------|-----|-------|---------|-------------|
| 1 - Afiliados | 23 | 10 | 12 | 1 |
| 2 - Empleadores | 4 | 4 | 0 | 0 |
| 3 - Afiliaciones | 3 | 2 | 1 | 0 |
| 4 - Motor cálculo | 24 | 20 | 4 | 0 |
| 5 - Liquidación | 22 | 17 | 5 | 0 |
| 6 - Facturación | 12 | 6 | 6 | 0 |
| 7 - Cuadre caja | 2 | 2 | 0 | 0 |
| 8 - Archivo PILA | 6 | 6 | 0 | 0 |
| 9 - Incapacidades | 2 | 2 | 0 | 0 |
| 10 - Asesores | 2 | 2 | 0 | 0 |
| 11 - Terceros | 2 | 1 | 1 | 0 |
| 12 - Documentos | 3 | 3 | 0 | 0 |
| 13 - Comunicaciones | 2 | 2 | 0 | 0 |
| 14 - Seguridad | 6 | 0 | 6 | 0 |
| 15 - Reportes | 2 | 2 | 0 | 0 |
| 16 - Config | 2 | 1 | 1 | 0 |
| 17 - ETL | 3 | 0 | 2 | 1 |
| 18 - Excepciones | 5 | 4 | 1 | 0 |
| **TOTAL** | **125** | **82** | **31** | **12** |

---

*Última actualización: Sprint K completo (RF-103 PDFs, RF-114 dashboard, RF-115 reportes, RF-018 alertas beneficiarios) — abril 2026*
*Próximo sprint recomendado: L — Seguridad completa (RBAC Spatie, auditoría, Habeas Data) (BACKLOG.md)*

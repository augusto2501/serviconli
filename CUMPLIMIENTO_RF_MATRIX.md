# Matriz de cumplimiento RF × estado

Herramienta de seguimiento respecto a [REQUISITOS_FUNCIONALES_SERVICONLI.md](REQUISITOS_FUNCIONALES_SERVICONLI.md).  
Estados: **No iniciado** | **En curso** | **Hecho (parcial)** | **Hecho** | **N/A**

_Actualizado con el avance de implementación en código (marzo 2026)._

## 1. Gestión de afiliados

| RF | Estado | Nota breve |
|----|--------|-------------|
| RF-001 | Hecho (parcial) | Wizard backend 6 pasos: `POST /api/enrollment/step-1..5` + `POST /api/enrollment/step-6/confirm` con persistencia en `wf_enrollment_processes`, validación y bloqueo de salto de pasos |
| RF-002 | Hecho | `AffiliateClientType` + columna `client_type` |
| RF-003–RF-004 | En curso | Catálogo cotizante en motor; subtipos en esquema `afl_affiliates.subtipo` |
| RF-005–RF-007 | Hecho (parcial) | Paso 2: tipos documento RF-005 (`Rule::in`), obligatorios RF-006 (incl. al menos un teléfono vía `required_without_all`), `is_foreigner` RF-007; paso 1 `is_type_51` RF-007. Tests: `EnrollmentWizardApiTest` (incl. `test_step2_*`). **Flujo vertical API:** `tests/Feature/E2E/ServiconliVerticalFlowTest` (login → afiliados → ficha-360 → notas → export CSV) |
| RF-008 | Hecho (parcial) | `RadicadoNumberGenerator` + `radicado_yearly_sequences`; formato `RAD-{YYYY}-{NNNNNN}`; radicado en `wf_enrollment_processes.radicado_number` al confirmar |
| RF-009 | Hecho (parcial) | Paso 6: `habeas_data_accepted` obligatorio; `gdpr_consent_records` con IP, user agent y `accepted_at` |
| RF-010 | Hecho (parcial) | `PostEnrollmentCompletionService` como punto de enganche; recibo/PDF/comisión/tercero/WhatsApp pendientes |
| RF-011 | Hecho (parcial) | Paso 5: `raw_ibc_pesos` + `EnrollmentBillingPreviewService` → `billingPreview` (primer mes proporcional días ingreso→fin de mes, tope 30; total mensual base 30 días vía `PILACalculationService`) |
| RF-012–RF-014 | Hecho (parcial) | `GET /api/reentry/eligible`, `POST /api/reentry/start`, pasos 1–3 (persona, entidades SS + `valid_from`, `payer_id` + cotizante), `POST /api/reentry/confirm`: cierra perfil SS y vínculo pagador, nuevo perfil SS, `bill_invoices.tipo=03`, estado `AFILIADO`. Seed `cfg_affiliate_statuses` RETIRADO/INACTIVO/AFILIADO |
| RF-015 | Hecho (parcial) | `GET /api/affiliates/{id}/ficha-360` vía `Ficha360ViewBuilder`: persona ampliada, estado/código, perfil SS vigente con `pilaCode` por entidad, pagador vigente, beneficiarios/notas (recientes), liquidaciones PILA confirmadas con líneas por período, `lastPaidPeriod`, facturas recientes, excepciones operativas activas; **portales** con `afl_portal_credentials` + API `GET/POST/PATCH/DELETE /api/affiliates/{id}/portal-credentials` (contraseña en claro por defecto; `PORTAL_CREDENTIALS_ENCRYPT=true` activa cifrado Laravel); documentos aún como hint |
| RF-016 | Hecho (parcial) | Ficha 360° web: accesos rápidos en UI (`/afiliados/{id}/ficha`); acciones conectadas como “próximamente” hasta flujos definitivos |
| RF-017 | Hecho (parcial) | Tabla + API list/create beneficiarios |
| RF-018 | No iniciado | Alertas automáticas |
| RF-019 | Hecho (parcial) | Tabla + API notas; `user_id` rellenado con usuario Sanctum; respuesta incluye `userId` |
| RF-020 | Hecho (parcial) | Vista web **Mis afiliados** (`/mis-afiliados`) + login (`/login`) con token Sanctum en `sessionStorage`; listado y export CSV/XLSX vía API; enlace a ficha 360° |
| RF-021–RF-023 | Hecho (parcial) | `GET /api/affiliates` con payload tipo hoja DATA: nombre completo, tipo cotizante (vínculo pagador vigente), estado/códigos, mora, `paymentIndicator` (SI/NO/ANTICIPADO/NEUTRO desde `mora_status`), EPS/AFP/ARL/CCF, operador PILA (`afl_payers.pila_operator_code`), último período pagado (liquidaciones PILA confirmadas), notas operativas + conteo notas formales. **Filtros RF-022:** `contributor_type_code`, `payer_id`, `advisor_id`, `pila_operator_code`, `eps_entity_id` / `afp_entity_id` / `arl_entity_id` / `ccf_entity_id`, `payments_on_track` (`yes`/`no`/`ahead`) + existentes. **Export:** `GET /api/affiliates/export?format=csv|xlsx` (OpenSpout). Paridad fina con Excel/columnas legacy pendiente |

## 2. Empleadores

| RF | Estado | Nota breve |
|----|--------|-------------|
| RF-024–RF-027 | Hecho (parcial) | `empl_employers` + **API** `GET/POST/PATCH/DELETE /api/employers` con validación NIT y campos extendidos RF-024 (representante, CIIU, dirección, contacto). Pendiente: integración con procesos aguas abajo |

## 3. Otros módulos

| RF / BC | Estado | Nota breve |
|---------|--------|-------------|
| BC-01 Motor | Hecho (parcial) | PILA + `MoraInterestService` + `SolidarityFundCalculator` §3.5–3.6 |
| BC-05 Liquidación producto | Hecho (parcial) | Tablas `pay_liquidation_*`; comandos `pila:*` stub |
| BC-06 Facturación | Hecho (parcial) | `bill_invoices` mínima |
| BC-07 Caja | Hecho (parcial) | `cash_daily_closures` mínima |
| BC-13 Seguridad | Hecho (parcial) | Laravel Sanctum: tokens `personal_access_tokens`; `POST /api/login` (throttle 5/min), `POST /api/logout` + `GET /api/user` con `auth:sanctum`; rutas módulos bajo `middleware(['api','auth:sanctum'])`; políticas `Affiliate`, `Employer`, `EnrollmentProcess`, `ReentryProcess`, `PilaLiquidation` (extensible a roles); notas con `user_id` del usuario autenticado |
| RF-028–RF-029 | Hecho (parcial) | Perfiles SS versionados + `SocialSecurityProfileService` |
| ETL (SKILL) | Hecho (parcial) | `etl:migrate-excel` / `etl:migrate-access` stub |

---

Para cada nueva fase, revisar reglas en la carpeta **cursor-serviconli-rules** del entorno de trabajo (puede estar fuera del árbol de este repositorio).

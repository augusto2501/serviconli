# BACKLOG — Serviconli
# Cola priorizada de implementación — Sprints pendientes
# Actualizar CUMPLIMIENTO_RF_MATRIX.md al cerrar cada sprint.

---

## Estado (abril 2026) — cierre de brechas

Los ítems H-2..H-5 están **implementados en código** (novedades ampliadas, RF-063, PDF cotizador, RF-030 + API + test, certificado PDF, scheduler). Pendiente: **commit unificado** y validación UAT.

**Siguiente sprint operativo:** **Sprint I** (Asesores, terceros, consignaciones) — ver sección inferior.

---

## SPRINT H — Completar Sprint G + Novedades restantes *(histórico / mayormente cerrado)*
**Prerrequisito:** commitear todo el trabajo del Sprint G antes de iniciar.

### H-1: Commit Sprint G
Commitear los archivos pendientes del Sprint G (NoveltyService, PaymentCertificateService,
MoraPeriodTransitionService, QuotationService y sus controllers/tests).
```
git add app/Modules/Affiliates/Services/NoveltyService.php
        app/Modules/Affiliates/Services/MoraPeriodTransitionService.php
        app/Modules/Affiliates/Services/PaymentCertificateService.php
        app/Modules/Affiliates/Controllers/NoveltyController.php
        app/Modules/Affiliates/Controllers/PaymentCertificateController.php
        app/Modules/Affiliates/Events/ARLRetirementReminderRequested.php
        app/Modules/Affiliates/Listeners/LogARLRetirementReminder.php
        app/Modules/Affiliates/Commands/TransicionPeriodoCommand.php
        app/Modules/Affiliates/Commands/MoraDetectCommand.php
        app/Modules/Billing/Services/QuotationService.php
        app/Modules/Billing/Controllers/QuotationController.php
        app/Modules/Affiliates/routes/api.php
        app/Modules/Billing/routes/api.php
        app/Providers/AppServiceProvider.php
        routes/console.php
        tests/Feature/Affiliates/SprintGNoveltyCertificateTest.php
        tests/Feature/Affiliates/MoraTransicionCommandTest.php
        tests/Feature/Billing/QuotationApiTest.php
```

### H-2: RF-061 — 13 tipos novedad PILA restantes
RFs: RF-061
Archivo: `app/Modules/Affiliates/Services/NoveltyService.php` (extender)
Implementar efectos post-guardado para: ING, LMA, LPA, IGE, IRL, SLN, LLU, TDE, TDP, VTE, AVP, VCT, COR.
Cada tipo debe:
- Registrar la novedad en `afl_novelties`
- Aplicar el efecto correspondiente sobre el perfil SS o estado del afiliado
- Validar combinaciones (ING+RET, ING+TAE, IGE+cualquiera)
Referencias: DOCUMENTO_RECTOR §3.4, RF-061, SKILL.md §"Novedades PILA"
Test: `tests/Feature/Affiliates/NoveltiesRemainingTest.php`

### H-3: RF-063 — Retiro por mora
RFs: RF-063
Archivo: `app/Modules/Affiliates/Services/NoveltyService.php` (extender RET)
Causal "MORA EN APORTE": días=1, provisiona deuda pendiente, fee admin=$0, póliza=$0.
Referencias: RF-063, LISTADO §5.2, SKILL.md RN-06
Test: `tests/Feature/Affiliates/RetiroPorMoraTest.php`

### H-4: RF-054 — PDF cotizador con branding Serviconli
RFs: RF-054
Servicio: `app/Modules/Billing/Services/QuotationService.php` (extender `create()`)
Generar PDF con barryvdh/laravel-dompdf al crear cotización.
Actualizar `pdf_path` en el modelo `Quotation`.
Vista: `resources/views/pdf/quotation.blade.php`
Referencias: RF-053, RF-054, LISTADO §4.7
Test: `tests/Feature/Billing/QuotationPdfTest.php`

### H-5: RF-030 — Contratos multi-ingreso independientes
RFs: RF-030
Módulo: `app/Modules/Affiliations/`
IBC al 40% del ingreso reportado, consolidación de múltiples contratos, tope 25 SMMLV.
Nueva tabla: `afl_multi_income_contracts`
Referencias: RF-030, D.1273/2018, LISTADO §3.3
Test: `tests/Feature/Affiliations/MultiIncomeContractTest.php`

**Definition of Done Sprint H:**
- [ ] Sprint G commiteado con tests verdes
- [ ] RF-061 todos los tipos con tests
- [ ] RF-063 con test de escenario mora
- [ ] RF-054 PDF generado y almacenado
- [ ] RF-030 tabla + servicio + tests
- [ ] `php artisan test` sin regresiones
- [ ] CUMPLIMIENTO_RF_MATRIX.md actualizado

---

## SPRINT I — Asesores y Terceros

### I-1: RF-099 / RF-100 — Módulo Advisors
RFs: RF-099, RF-100
Módulo: `app/Modules/Advisors/`
CRUD asesores (código, documento, nombre, teléfono, email).
2 valores de comisión: `commission_new` (afiliación nueva) + `commission_recurring` (mensual).
Flag `authorizes_credits` (puede usar medio de pago CRÉDITO).
Cálculo automático de comisión al confirmar enrollment/reingreso (conectar con `PostEnrollmentCompletionService`).
Comprobante de egreso por comisión (CE-{YYYY}-{NNNN}).
Estados comisión: CALCULADA → PAGADA | ANULADA.
Referencias: RF-099, RF-100, LISTADO §10.1, §10.2
Tests: `tests/Feature/Advisors/AdvisorCrudTest.php`, `tests/Feature/Advisors/CommissionTest.php`

### I-2: RF-101 / RF-102 — Módulo ThirdParties
RFs: RF-101, RF-102
Módulo: `app/Modules/ThirdParties/`
Registro formal de consignaciones bancarias: banco, referencia, valor, tipo (local/nacional).
Validación referencia duplicada (warning, no bloqueo).
Registro de excedente si consignan más de lo debido.
CxC a asesores cuando medio de pago = CRÉDITO.
Seguimiento CxC: PENDIENTE → PAGADA | ANULADA.
Referencias: RF-101, RF-102, LISTADO §11.2, §11.3, SKILL.md CASO 2/3
Tests: `tests/Feature/ThirdParties/BankDepositTest.php`, `tests/Feature/ThirdParties/ReceivableTest.php`

### I-3: RF-010 — Completar PostEnrollmentCompletionService
RFs: RF-010
Archivo: `app/Modules/Affiliates/Services/PostEnrollmentCompletionService.php`
Conectar los hooks pendientes (requiere Sprint I-1 y I-2):
- Calcular y registrar comisión asesor
- Crear tercero si no existe
- WhatsApp bienvenida (stub hasta Sprint J-2)
Referencias: RF-010, LISTADO §1.1

**Definition of Done Sprint I:**
- [ ] CRUD asesores con flag comisión y créditos
- [ ] Cálculo automático comisión al afiliar
- [ ] Consignaciones con validación duplicada
- [ ] CxC a asesores funcional
- [ ] PostEnrollmentCompletionService completo (salvo WhatsApp)
- [ ] `php artisan test` sin regresiones
- [ ] CUMPLIMIENTO_RF_MATRIX.md actualizado

---

## SPRINT J — Incapacidades y Comunicaciones

### J-1: RF-097 / RF-098 — Módulo Disabilities
RFs: RF-097, RF-098
Módulo: `app/Modules/Disabilities/`
Registro de incapacidades EPS (enfermedad general) y ARL (riesgo laboral).
Subtipo con documentos requeridos por subtipo.
Diagnóstico CIE-10 (desde `cfg_diagnosis_cie10`).
Fechas inicio/fin → cálculo automático de días.
Prórrogas vinculadas: acumulación de días, alerta automática > 180 días.
Referencias: RF-097, RF-098, LISTADO §9.1, §9.2
Tests: `tests/Feature/Disabilities/DisabilityTest.php`, `tests/Feature/Disabilities/ExtensionTest.php`

### J-2: RF-106 / RF-107 — Módulo Communications
RFs: RF-106, RF-107
Módulo: `app/Modules/Communications/`
WhatsApp Business via Twilio (o Meta Business API como alternativa).
Templates predefinidos por evento: bienvenida, recordatorio pago, alerta mora, confirmación.
Log de envíos: `comm_whatsapp_logs` con estado (enviado/entregado/leído/fallido).
Notificaciones internas: `comm_notifications` por usuario, con tipos, marcación leídas, URL acción.
Referencias: RF-106, RF-107, LISTADO §13.1, §13.2
Tests: `tests/Feature/Communications/WhatsAppTest.php`, `tests/Feature/Communications/NotificationTest.php`

### J-3: RF-074 — Conectar alertas mora con WhatsApp
RFs: RF-074
Depende de J-2.
Cuando mora > 1 mes: evento → listener → WhatsApp al afiliado (D.780/2016).
Referencias: RF-074, LISTADO §5.4

**Definition of Done Sprint J:**
- [ ] Registro incapacidades EPS/ARL con CIE-10
- [ ] Prórrogas con alerta > 180 días
- [ ] WhatsApp funcional con log de envíos
- [ ] Notificaciones internas por usuario
- [ ] Alertas mora conectadas con WhatsApp
- [ ] `php artisan test` sin regresiones

---

## SPRINT K — Documentos y Reportes

### K-1: RF-103 — 7 Templates de contratos PDF
RFs: RF-103
Módulo: `app/Modules/Documents/`
7 plantillas Blade con interpolación de variables:
  1. Cláusulas operativas (8 cláusulas)
  2. Contrato de asociación legal (9 cláusulas)
  3. Declaración del afiliado (beneficiarios, fecha límite)
  4. Declaración de tipo de vinculación
  5. Declaración de retiro voluntario
  6. Certificado de afiliación
  7. Certificado de pago (2 formatos: completo y resumido)
barryvdh/laravel-dompdf para generación.
Versionado de templates (contratos antiguos mantienen su versión).
Referencias: RF-103, LISTADO §12.2
Tests: `tests/Feature/Documents/ContractTemplateTest.php`

### K-2: RF-114 / RF-115 — Dashboard gerencial y reportes operativos
RFs: RF-114, RF-115
Endpoint: `GET /api/dashboard` → indicadores en tiempo real
Indicadores: afiliados activos/mora/inactivos, recaudo mes vs anterior,
planillas generadas, afiliaciones nuevas, distribución tipo/operador, panel alertas.
Reportes: relación diaria aportes, gestión cobro mora, cuadre caja del día.
Referencias: RF-114, RF-115, LISTADO §15.1, §15.2
Tests: `tests/Feature/Dashboard/DashboardTest.php`

### K-3: RF-018 — Alertas automáticas de beneficiarios
RFs: RF-018
Job diario que detecta:
- Beneficiario próximo a cumplir 18 años (30 días antes)
- Certificado de estudiante próximo a vencer (30 días antes)
Depende de J-2 para enviar WhatsApp/notificación.
Referencias: RF-018

**Definition of Done Sprint K:**
- [ ] 7 templates PDF generando correctamente
- [ ] Dashboard con todos los indicadores del RF-114
- [ ] Reportes operativos del RF-115
- [ ] Job alertas beneficiarios funcionando
- [ ] `php artisan test` sin regresiones

---

## SPRINT L — Seguridad completa

### L-1: RF-108 — RBAC completo con Spatie Permission
RFs: RF-108
Instalar y configurar `spatie/laravel-permission`.
5 roles con permisos granulares:
  - ADMIN: acceso completo
  - AFILIACIONES: gestión afiliados y novedades (Marcela)
  - PAGOS: liquidación, planillas, cuentas de cobro (Katherine)
  - CARTERA: cobro, mora, recibos (Natalia)
  - CONSULTA: solo lectura
Migrar de Policies simples a Spatie + Policies.
Referencias: RF-108, LISTADO §14.1

### L-2: RF-109 — Tabla de auditoría completa
RFs: RF-109
Tabla `sec_audit_logs`: usuario, acción, modelo, ID, valores antes/después (JSON), IP, timestamp.
Observer base en todos los modelos críticos.
Obligatorio en: cambios de estado, aportes, planillas, anulaciones, credenciales.
Log específico `sec_credential_access_logs` para descifrado de credenciales.
Referencias: RF-109, LISTADO §14.2

### L-3: RF-110 / RF-111 / RF-112 / RF-113 — Seguridad complementaria
RFs: RF-110, RF-111, RF-112, RF-113
RF-110: Gestión derechos Habeas Data (consulta/rectificación/supresión/revocación).
RF-111: Log específico de cada acceso/descifrado de credenciales cifradas.
RF-112: Soft delete con motivo enforced en TODOS los modelos críticos.
RF-113: Comando `files:purge` para archivos PILA > 2 años.
Referencias: RF-110-113, LISTADO §14.3-14.5

**Definition of Done Sprint L:**
- [ ] 5 roles Spatie funcionando con permisos granulares
- [ ] Audit log en todas las acciones modificativas
- [ ] Habeas Data con gestión de derechos
- [ ] Soft delete con motivo enforced
- [ ] Comando files:purge funcional

---

## SPRINT M — ETL (máximo riesgo — requiere datos reales del cliente)

**AVISO:** Este sprint requiere acceso a los archivos reales del cliente:
- `DataSegura-SERVICONLI-2025.xlsx` (Excel con 891 registros)
- `AplicativoV6.accdb` (Access con historial)

### M-1: RF-119 — Seeders completos desde hojas del Excel
RFs: RF-119
Implementar seeders completos con datos reales:
  - 95 administradoras con código PILA exacto (hoja "LISTADO DE CODIGOS PILA DE ADMI")
  - 24 tipos cotizante (hoja "Código y Tipo de Cotizante")
  - 5 clases riesgo ARL con tarifas exactas (hoja "TABLA DE RIESGOS ARL")
  - 16 rangos fechas pago según Res. 2388/2016 (hoja "FECHAS DE PAGO")
NUNCA inventar códigos PILA — usar SOLO los del Excel real.
Referencias: RF-119, SKILL.md §"Seeders del Excel"

### M-2: RF-118 — ETL Excel 891 registros
RFs: RF-118
Implementar `etl:migrate-excel {path}` con 8 transformaciones de limpieza:
  1. Normalizar NIT (3 formatos → número + DV separado)
  2. Parsear MES_PAGO (22 variantes → período YYYYMM + estado)
  3. Cifrar credenciales AES-256 (800 operador + 1.336 portales)
  4. Limpiar teléfonos float ("3223109130.0" → "3223109130")
  5. Normalizar geografía ("QUINDIO"/"Quindío" → estándar)
  6. Unificar nulos (N/A, SIN INFORMACIÓN → NULL)
  7. Limpiar documentos float ("15296441.0" → "15296441")
  8. Agregar PPT/PTT al catálogo tipos documento
Mapeo 1:1 a hojas acordadas en SKILL — NO inventar columnas.
Preservar historial de novedades (ING 351, RET 120, TAE 2, TDE 1).
Referencias: RF-118, SKILL.md §"Problemas de Calidad del Excel"
Test: `tests/Feature/ETL/ExcelMigrationTest.php` (con fixture anonimizado)

### M-3: RF-120 — ETL Access histórico
RFs: RF-120
Implementar `etl:migrate-access {path}`.
Importar: historial aportes pagados, cuentas de cobro, recibos de caja.
Preservar consecutivos y radicados existentes.
Mapeo desde tablas del Access (113 tablas identificadas).
Referencias: RF-120, SKILL.md §"Migración desde Access"

**Definition of Done Sprint M:**
- [ ] Seeders con datos reales del Excel (no inventados)
- [ ] ETL Excel completo con 8 transformaciones validadas
- [ ] ETL Access con historial importado
- [ ] Consecutivos y radicados preservados
- [ ] Tests con fixtures anonimizados
- [ ] Paridad numérica verificada vs Access (tolerancia cero)

---

## Resumen de RFs pendientes por sprint

| Sprint | RFs | Estado inicial | Descripción |
|--------|-----|----------------|-------------|
| H | RF-061, RF-063, RF-054, RF-030 | Parcial/No iniciado | Novedades, mora, cotizador PDF, multi-ingreso |
| I | RF-099, RF-100, RF-101, RF-102, RF-010 | No iniciado | Asesores, terceros, post-enrollment |
| J | RF-097, RF-098, RF-106, RF-107, RF-074 | No iniciado | Incapacidades, comunicaciones, alertas mora |
| K | RF-103, RF-114, RF-115, RF-018 | No iniciado | Contratos PDF, dashboard, reportes, alertas beneficiarios |
| L | RF-108, RF-109, RF-110, RF-111, RF-112, RF-113 | Parcial | Seguridad completa |
| M | RF-118, RF-119, RF-120 | Stub | ETL Excel + Access |

## RFs parciales no asignados a sprint propio (resolver dentro del sprint natural)

| RF | Pendiente | Resolver en |
|----|-----------|-------------|
| RF-044 | Alerta activa cuando legacy vs PILA > 1% en período de transición | Sprint H |
| RF-052 | Confirmación explícita usuario para período adelantado | Sprint H |
| RF-059 | UI Vue recalcular en tiempo real al cambiar días | Sprint H |
| RF-079 | PDF cuenta de cobro definitiva | Sprint I |
| RF-084-086 | Cascadas de anulación completas (6 combinaciones) | Sprint I |
| RF-116 | UI admin CRUD para 25+ catálogos | Sprint L |

---

*Actualizado: Sprint G completado (pendiente commit) — abril 2026*

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
| RF-005–RF-007 | En curso | `core_people` alineado; validación API mínima; faltan reglas completas paso 2/7 |
| RF-008–RF-011 | No iniciado | Radicado, consentimiento, recibos, PDF |
| RF-012–RF-014 | No iniciado | Reingreso |
| RF-015 | Hecho (parcial) | `GET /api/affiliates/{id}/ficha-360` — sin aportes, portales, documentos |
| RF-016 | No iniciado | Acciones rápidas en UI |
| RF-017 | Hecho (parcial) | Tabla + API list/create beneficiarios |
| RF-018 | No iniciado | Alertas automáticas |
| RF-019 | Hecho (parcial) | Tabla + API notas; `user_id` pendiente de auth |
| RF-020 | En curso | Notas en API; falta integración vista Mis Afiliados UI |
| RF-021–RF-023 | En curso | Listado + `q` + `client_type` + `status_id` + `mora_status`; **export CSV** `GET /api/affiliates/export?format=csv` (BOM UTF-8, `;`) con columnas EPS/AFP/ARL/CCF desde perfil vigente. Falta paridad total con hoja Excel |

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
| BC-13 Seguridad | No iniciado | Sanctum/policies según ESTRUCTURA |
| RF-028–RF-029 | Hecho (parcial) | Perfiles SS versionados + `SocialSecurityProfileService` |
| ETL (SKILL) | Hecho (parcial) | `etl:migrate-excel` / `etl:migrate-access` stub |

---

Para cada nueva fase, revisar reglas en la carpeta **cursor-serviconli-rules** del entorno de trabajo (puede estar fuera del árbol de este repositorio).

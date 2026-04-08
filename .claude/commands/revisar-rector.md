---
description: Revisa que el código cumple el Documento Rector de Serviconli antes de commitear. Produce reporte APROBADO / RECHAZADO con issues críticos y warnings.
---

Eres un revisor de cumplimiento con cero tolerancia a antipatrones en el proyecto Serviconli.

## Paso 1: Identificar el alcance

```bash
git status    # archivos modificados/nuevos
git diff      # cambios exactos
```

## Paso 2: Checklist de cumplimiento

Para cada archivo revisado:

### Cálculos y Motor PILA — fallo = CRÍTICO
- [ ] ¿Hay `round()`, `ceil()`, `floor()` nativos para cálculos PILA?
- [ ] ¿Se usa `RoundingEngine::roundIBC()` para el IBC?
- [ ] ¿Se usa `RoundingEngine::roundLegacy()` para aportes?
- [ ] ¿Hay columnas `DECIMAL` para montos en pesos (deben ser `INT UNSIGNED`)?
- [ ] ¿Hay tasas o porcentajes hardcodeados en código?
- [ ] ¿Los parámetros normativos se leen con fecha de vigencia desde `cfg_regulatory_parameters`?

### Perfil SS y Estados — fallo = CRÍTICO
- [ ] ¿Hay asignaciones directas a `eps_entity_id`, `afp_entity_id`, `ibc` del perfil SS?
- [ ] ¿Los cambios de entidad usan `SocialSecurityProfileService::versionProfileForTransfer()`?
- [ ] ¿Los cambios de salario usan `SocialSecurityProfileService::versionProfileForSalaryChange()`?
- [ ] ¿Hay asignaciones directas a `status_id` o `mora_status` del afiliado?
- [ ] ¿Los cambios de mora usan `AffiliateStatusMachine`?
- [ ] ¿La mora sube o baja exactamente UN solo nivel?

### Excepciones Operativas
- [ ] ¿El motor consulta `OperationalExceptionService::getActive()` antes de calcular?

### Arquitectura DDD
- [ ] ¿Las nuevas tablas usan el prefijo correcto (`cfg_`, `afl_`, `pay_`, `bill_`, etc.)?
- [ ] ¿Hay lógica de negocio compleja en Controllers en lugar de Services?
- [ ] ¿Hay `if ($tipoC == '01')` en lugar de Strategy Pattern?

### Trazabilidad
- [ ] ¿Los Services tienen docblock con `@see DOCUMENTO_RECTOR §X`?
- [ ] ¿Las reglas tienen comentario `// RF-XXX` o `// RN-XX`?
- [ ] ¿Las migraciones tienen `// DOCUMENTO_RECTOR §X Grupo {letra}`?

### Seguridad de Datos
- [ ] ¿Se usa `delete()` físico en modelos de seguridad social?
- [ ] ¿Los soft deletes tienen `deleted_reason` y `deleted_by`?

### Tests
- [ ] ¿Existe test Feature para cada funcionalidad nueva?
- [ ] ¿Los tests usan `RefreshDatabase`?
- [ ] ¿`php artisan test` pasa sin regresiones?

## Paso 3: Reporte

```
REVISIÓN DE CUMPLIMIENTO — [archivos revisados]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Estado General: APROBADO | APROBADO CON OBSERVACIONES | RECHAZADO

ISSUES CRÍTICOS (bloquean el commit):
  [archivo:línea] — descripción — corrección exacta requerida

WARNINGS (corregir en el sprint):
  [archivo:línea] — descripción

APROBADO:
  [lista de verificaciones que pasaron]
```

## Paso 4: Issues críticos

Si hay issues críticos, NO commitear. Mostrar la corrección exacta:

```php
// MAL
$perfil->eps_entity_id = $nueva;
$perfil->save();

// BIEN
$this->profileService->versionProfileForTransfer(
    $affiliate, 'eps_entity_id', $nueva,
);
```

## Paso 5: Si todo pasa

```bash
git add [archivos específicos del sprint]
git commit -m "feat(sprint-{letra}): {descripción}"
```

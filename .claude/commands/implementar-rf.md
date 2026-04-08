---
description: Implementa un RF de Serviconli siguiendo el Documento Rector V5, con trazabilidad obligatoria, cero antipatrones y tests incluidos. Uso: /implementar-rf RF-XXX
---

Eres un desarrollador senior del proyecto Serviconli con cero tolerancia a antipatrones.

## Paso 1: Cargar el contexto completo

ANTES de escribir una sola línea de código, leer en este orden:

1. `CLAUDE.md` — reglas absolutas, antipatrones prohibidos, estado actual
2. El RF específico en `REQUISITOS_FUNCIONALES_SERVICONLI.md`
3. El estado actual del RF en `CUMPLIMIENTO_RF_MATRIX.md`
4. La sección del módulo en `LISTADO_FUNCIONALIDADES_SERVICONLI.md`
5. Las reglas de negocio relevantes en `SKILL.md`
6. La sección correspondiente en `DOCUMENTO_RECTOR_DEFINITIVO_V5_COMPLETO.md`

## Paso 2: Analizar el código existente

1. Identificar el módulo DDD: `app/Modules/{Módulo}/`
2. Leer Services, Models, Controllers y rutas del módulo
3. Leer migraciones existentes del módulo
4. Identificar dependencias con otros módulos
5. Buscar tests existentes en `tests/Feature/{Módulo}/`

## Paso 3: Declarar el plan

```
PLAN RF-XXX: [nombre]
━━━━━━━━━━━━━━━━━━━━━
Módulo:           app/Modules/{Módulo}/
Archivos NUEVOS:  [lista]
Archivos MODIFY:  [lista]
Migraciones:      [prefijo_tabla / ninguna]
Tests a crear:    [lista]
Referencia:       DOCUMENTO_RECTOR §X / RF-XXX
```

Confirmar con el usuario si el cambio afecta más de 5 archivos.

## Paso 4: Implementar — reglas del Documento Rector

**Montos y cálculos (CRÍTICO):**
- Montos en pesos → `INT UNSIGNED` (NUNCA `DECIMAL`)
- IBC → `RoundingEngine::roundIBC(intval(($salario / 30) * $dias))`
- Aportes → `RoundingEngine::roundLegacy($ibc * $tasa)`
- NUNCA `round()`, `ceil()`, `floor()` nativos para cálculos PILA
- Tasas → desde `cfg_regulatory_parameters` con fecha de vigencia

**Perfil SS (CRÍTICO):**
- NUNCA: `$perfil->eps_entity_id = $id; $perfil->save()`
- SIEMPRE: `$profileService->versionProfileForTransfer($afl, 'eps_entity_id', $id)`
- SIEMPRE: `$profileService->versionProfileForSalaryChange($afl, $nuevoIBC)`

**Mora (CRÍTICO):**
- NUNCA asignar `status_id` o `mora_status` directamente
- SIEMPRE: `$statusMachine->escalate($afl)` / `$statusMachine->deescalate($afl)`
- UN solo nivel por transición

**Excepciones operativas:**
- El motor SIEMPRE llama a `$exceptionService->getActive()` ANTES de calcular

**Strategy Pattern:**
- NUNCA: `if ($cotizante == '01') { ... }`
- SIEMPRE: `$strategyResolver->resolve($codigo)->calculate($context)`

**Trazabilidad obligatoria:**
```php
/**
 * @see DOCUMENTO_RECTOR §X.Y
 */
// RF-XXX: descripción de la regla
// Portado de Access: Form_005 [referencia]
```

## Paso 5: Escribir tests

```php
class {Feature}Test extends TestCase
{
    use RefreshDatabase; // SIEMPRE

    public function test_happy_path(): void { ... }
    public function test_error_esperado(): void { ... }
    public function test_efecto_secundario(): void { ... }
}
```

## Paso 6: Verificar no-regresión

```bash
php artisan test
```

NUNCA deshabilitar tests existentes. Si falla algo → corregir primero.

## Paso 7: Actualizar trazabilidad

1. Actualizar `CUMPLIMIENTO_RF_MATRIX.md` con el nuevo estado del RF
2. Verificar el checklist del `CLAUDE.md`

## Paso 8: Reporte final

```
IMPLEMENTACIÓN RF-XXX — [nombre]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Estado:    Hecho / Hecho (parcial)
Archivos:  [lista]
Tests:     [lista — todos verdes]
Pendiente: [si hay algo fuera de alcance]
Siguiente: [próximo RF según BACKLOG.md]
```

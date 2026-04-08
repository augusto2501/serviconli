---
description: Inicia el siguiente sprint del BACKLOG.md de Serviconli. Verifica el estado del repo, carga el contexto completo y coordina la implementación RF por RF con máximo rigor.
---

Eres el coordinador de desarrollo de Serviconli. Inicia el próximo sprint del backlog con máximo rigor.

## Paso 1: Verificar el estado del repositorio

```bash
git status        # ¿hay trabajo sin commitear?
php artisan test  # ¿todos los tests pasan?
```

- Si hay trabajo sin commitear → ejecutar `/revisar-rector` primero, luego commitear.
- Si hay tests rotos → corregirlos antes de iniciar. Un sprint no puede comenzar sobre base rota.

## Paso 2: Identificar el próximo sprint

Leer `BACKLOG.md` y determinar:
- ¿Cuál es el siguiente sprint pendiente?
- ¿Están cumplidas las dependencias declaradas?
- ¿Hay trabajo parcial de sprints anteriores que completar primero?

## Paso 3: Cargar el contexto del sprint

Para cada RF del sprint:
1. Leer RF en `REQUISITOS_FUNCIONALES_SERVICONLI.md`
2. Verificar estado en `CUMPLIMIENTO_RF_MATRIX.md`
3. Leer detalle en `LISTADO_FUNCIONALIDADES_SERVICONLI.md`
4. Identificar reglas en `SKILL.md` y `DOCUMENTO_RECTOR_DEFINITIVO_V5_COMPLETO.md`

## Paso 4: Declarar el plan al usuario

```
SPRINT {LETRA}: {NOMBRE}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

RFs a implementar:
  RF-XXX — [nombre] — [estado actual]

Módulos afectados:  app/Modules/{Módulo}/
Migraciones nuevas: [prefijo_tabla]
Dependencias:       [Sprint anterior / ninguna]
Riesgos:            [lista o "ninguno"]
```

**Esperar confirmación del usuario antes de proceder.**

## Paso 5: Ejecutar el sprint

Para cada RF, aplicar el workflow de `/implementar-rf`:
```
1. Leer RF y contexto completo
2. Analizar código existente del módulo
3. Implementar con reglas del Documento Rector
4. Escribir tests (happy path + errores + efectos secundarios)
5. php artisan test → verificar no-regresión
6. Actualizar CUMPLIMIENTO_RF_MATRIX.md
7. Reportar estado antes del siguiente RF
```

Reglas durante el sprint:
- NO implementar funcionalidades fuera del alcance declarado
- NO saltarse un RF por complejidad
- Si un RF bloquea → documentar en BACKLOG.md, continuar con el siguiente
- NO commitear con tests rotos

## Paso 6: Cierre del sprint

```bash
php artisan test   # TODOS deben pasar
```

Ejecutar `/revisar-rector` sobre todos los cambios.

Actualizar:
- `CUMPLIMIENTO_RF_MATRIX.md` — RFs marcados como Hecho
- `BACKLOG.md` — sprint marcado como completado

Commitear:
```bash
git commit -m "feat(sprint-{letra}): {descripción} — Flujos {X} (Grupo {N})

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"
```

## Reporte de cierre

```
SPRINT {LETRA} CERRADO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

RFs completados:      RF-XXX, RF-XXX
Tests creados:        X — todos verdes
Tests totales:        X passing
Pendientes hallados:  [lista o "ninguno"]

Próximo sprint: {LETRA+1} — [nombre]
```

## Definition of Done (todos los sprints)

- [ ] Todos los RFs del sprint tienen test Feature verde
- [ ] `php artisan test` sin regresiones
- [ ] Código cita `// DOCUMENTO_RECTOR §X` y `// RF-XXX`
- [ ] `CUMPLIMIENTO_RF_MATRIX.md` actualizado
- [ ] `/revisar-rector` sin issues críticos
- [ ] Commit hecho con mensaje de convención del proyecto

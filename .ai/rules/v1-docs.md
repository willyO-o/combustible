---
paths:
  - 'app/Http/Controllers/Api/V1/*.php,public/docs/openapi.yaml'
  - 'app/Http/Controllers/Api/V1/VehiculoController.php,routes/api.php,public/docs/openapi.yaml'
---

# V1 Docs

## Materiales API + control_cargas en colecciones documentados bajo el tag "Control de Cargas"
Api\V1\MaterialController (index/store, reutiliza MaterialRequest sin cambios) queda documentado en openapi.yaml bajo el tag existente "Control de Cargas" (no un tag nuevo "Materiales"), porque el catálogo de materiales (cola, broza, concentrado, etc.) solo tiene sentido en el contexto de viajes/cargas. ParametrosController::colecciones() ahora también expone `control_cargas.materiales` y `control_cargas.estados_carga` (ABIERTA/CERRADA/PAGADA) — documentado en el schema ColeccionesResponse. Antes de dar por corregida una discrepancia reportada entre código y openapi.yaml, verificar primero si ya está en sync (el caso de AuthController::userData() reportado en esta tarea ya estaba 100% documentado correctamente — no requirió cambios).

## VehiculoExterno API sigue el mismo patrón que Material
Api\V1\VehiculoExternoController (index/store, reutiliza VehiculoExternoRequest sin cambios) documentado en openapi.yaml bajo el mismo tag "Control de Cargas". ParametrosController::colecciones() ahora expone control_cargas.vehiculos_externos junto a control_cargas.materiales y control_cargas.estados_carga. Cualquier otro catálogo de apoyo para control de cargas (nuevo tipo de recurso "quick-add") debería seguir este mismo patrón: Api\V1\{Recurso}Controller con solo index+store, reutilizando el FormRequest web existente, + entrada en colecciones() + sección en openapi.yaml bajo "Control de Cargas".

## API V1: mantenimiento sugerido por vehículo (GET /vehiculos/{vehiculo}/mantenimiento-sugerido)
`Api\V1\VehiculoController::mantenimientoSugerido` expone el mismo cálculo de alertas de mantenimiento de Vehiculos/Show web: llama `IntervaloMantenimientoTipo::alertasMantenimiento([$vehiculo->id])` (todo el cómputo en servidor) y ADEMÁS agrega por alerta `unidad` (km/h), `estado_label` (Vencido/Próximo/Al día/Sin datos) y `descripcion` (frase lista para mostrar) para que la app no calcule nada. Devuelve `{data:{vehiculo, resumen{total,vencidos,proximos,al_dia,sin_datos,requiere_atencion}, alertas[]}}` ordenado por urgencia VENCIDO→PROXIMO→AL_DIA→SIN_DATOS. Acceso: `assertPuedeVer()` — conductor sólo sus vehículos asignados (`$vehiculo->conductorAsignado?->id === $user->id_persona`), resto sin restricción (mismo criterio que el resto de la API V1). Ruta bajo `auth:api` en routes/api.php, `api.v1.vehiculos.mantenimiento-sugerido`. Documentado en openapi.yaml (tag nuevo "Vehículos", schemas `MantenimientoSugeridoResponse`/`MantenimientoSugerido`) + CHANGELOG-openapi.md 1.2.0; `info.version` y `api_version` bumped a 1.2.0. Tests: tests/Feature/Api/V1/VehiculoMantenimientoControllerTest.php.

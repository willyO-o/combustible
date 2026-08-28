---
paths:
  - 'app/Models/OrdenTrabajo.php,app/Models/DetalleMantenimiento.php,app/Http/Controllers/OrdenTrabajoController.php'
---

# Http Controllers

## Flujo de mantenimiento Paso 2/3 vive en orden_trabajo + detalle_mantenimiento
La lógica de emisión/ejecución de órdenes de trabajo (Pasos 2 y 3 del flujo de mantenimiento) vive en OrdenTrabajo + DetalleMantenimiento, NO en plan_mantenimiento/mantenimiento_repuesto (tablas eliminadas, ya no existen). Puntos de diseño a respetar:
- `tipo_orden` (INTERNO/EXTERNO) no es columna: es un accessor derivado de `id_taller` (null = INTERNO).
- `id_usuario_ejecuta` es NOT NULL: el responsable de ejecución se asigna al EMITIR la orden (Paso 2), no al culminarla.
- `detalle_mantenimiento` NO maneja costos: sus columnas son id_orden_trabajo, id_repuesto (nullable = mano de obra), id_tipo_mantenimiento, fecha, horometro/kilometraje (según tipo_medicion del vehículo), cantidad. No hay `costo_unitario`/`subtotal`/`costo_total` (se quitaron).
- El técnico registra el detalle ítem por ítem: web vía OrdenTrabajoController::storeDetalle/updateDetalle/destroyDetalle, API móvil vía Api\V1\OrdenTrabajoController (ver .ai/rules/v1.md). Sólo modificable con estado_orden PENDIENTE/EN_EJECUCION.
- `nro_orden`/`gestion` se autogeneran en `OrdenTrabajo::boot()` (mismo patrón que SolicitudMantenimiento), con unique(nro_orden, gestion).
- Al crear un OrdenTrabajo/SolicitudMantenimiento directamente en tests (no vía HTTP), hace falta `actingAs()` primero porque boot() lee `auth()->id()`.

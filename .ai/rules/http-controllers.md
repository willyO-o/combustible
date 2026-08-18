---
paths:
  - 'app/Models/OrdenTrabajo.php,app/Models/DetalleMantenimiento.php,app/Http/Controllers/OrdenTrabajoController.php'
---

# Http Controllers

## Flujo de mantenimiento Paso 2/3 vive en orden_trabajo + detalle_mantenimiento
La lógica de emisión/ejecución de órdenes de trabajo (Pasos 2 y 3 del flujo de mantenimiento) vive en OrdenTrabajo + DetalleMantenimiento, NO en plan_mantenimiento/mantenimiento_repuesto (tablas eliminadas, ya no existen). Puntos de diseño a respetar:
- `tipo_orden` (INTERNO/EXTERNO) no es columna: es un accessor derivado de `id_taller` (null = INTERNO).
- `id_usuario_ejecuta` es NOT NULL: el responsable de ejecución se asigna al EMITIR la orden (Paso 2), no al culminarla.
- `costo_total`/subtotales no se guardan: se derivan sumando `detalle_mantenimiento.cantidad * costo_unitario` (accessor `subtotal` en DetalleMantenimiento).
- `nro_orden`/`gestion` se autogeneran en `OrdenTrabajo::boot()` (mismo patrón que SolicitudMantenimiento), con unique(nro_orden, gestion).
- Al crear un OrdenTrabajo/SolicitudMantenimiento directamente en tests (no vía HTTP), hace falta `actingAs()` primero porque boot() lee `auth()->id()`.

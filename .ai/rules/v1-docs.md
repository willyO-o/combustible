---
paths:
  - 'app/Http/Controllers/Api/V1/*.php,public/docs/openapi.yaml'
---

# V1 Docs

## Materiales API + control_cargas en colecciones documentados bajo el tag "Control de Cargas"
Api\V1\MaterialController (index/store, reutiliza MaterialRequest sin cambios) queda documentado en openapi.yaml bajo el tag existente "Control de Cargas" (no un tag nuevo "Materiales"), porque el catálogo de materiales (cola, broza, concentrado, etc.) solo tiene sentido en el contexto de viajes/cargas. ParametrosController::colecciones() ahora también expone `control_cargas.materiales` y `control_cargas.estados_carga` (ABIERTA/CERRADA/PAGADA) — documentado en el schema ColeccionesResponse. Antes de dar por corregida una discrepancia reportada entre código y openapi.yaml, verificar primero si ya está en sync (el caso de AuthController::userData() reportado en esta tarea ya estaba 100% documentado correctamente — no requirió cambios).

---
paths:
  - 'app/Http/Controllers/Api/V1/ValeController.php,app/Actions/Vale/ListValeAction.php'
---

# Vale

## Listado de vales (web y API): administrador > jefe-area > conductor
ListValeAction (compartida por ValeController web y Api\V1\ValeController) aplica el alcance por precedencia de rol, sin flags: administrador/super-admin (con cualquier combinación de roles) ven todo; si no, jefe-area (puro o combinado con conductor) ve los vales de vehículos de sus áreas a cargo (`whereHas('vehiculo.areasAsignadas', ...)` con `persona->encargadoAreas()`); si no, conductor ve sólo los suyos. Antes la web filtraba por `hasRole('conductor')` a secas, así que un usuario con los 3 roles sólo veía sus propios vales.

Tests en ValeControllerTest (web) y Api\V1\ValeControllerTest.

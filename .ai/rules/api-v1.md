---
paths:
  - 'app/Http/Controllers/Api/V1/ValeController.php,app/Http/Requests/ValeRequest.php,app/Http/Controllers/Api/V1/ParametrosController.php'
---

# Api V1

## API V1: emisión de vales (jefe-area)
Api\V1\ValeController expone index + store + show (`Route::resource(...)->only(['index','store','show'])`; se quitaron los routes rotos update/destroy que declaraba `->except(['create','edit'])`). `store()` es igual al web ValeController::store (id_tipo_combustible del vehículo, estado_vale PENDIENTE, resto autogenerado en Vale::boot()), envuelto en DB::transaction + try/catch → 500 con `error`.
- Gating: en el web "quién puede emitir un vale" es sólo v-can (frontend). ValeRequest::authorize() ahora, SÓLO para `isMethod('POST') && is('api/*')`, exige `hasRole('super-admin') || hasPermissionTo('vales.crear','web')` (guard explícito por el problema de auth:api → guard `api`; ver [[api-v1-permisos-guard-explicito]]). Se le añadió failedValidation()/failedAuthorization() con envelope JSON guardados por expectsJson(). El web sigue igual (authorize=true).
- La regla `reglaVehiculoDeAreaDeJefe()` (jefe-area sólo vehículos de sus áreas) ya cubría POST, funciona igual para la API.
- ParametrosController::colecciones() → cada `vehiculos[]` ahora trae `id_conductor` + `conductor_asignado {id,nombre_completo,ci}` (titular ACTIVO, `Vehiculo::conductorAsignado`), para autocompletar el conductor del vale. Se envuelve en EloquentCollection::make(...) antes de `->load()` porque el merge puede devolver un Support\Collection (sin ->load). Documentado en openapi.yaml (POST /vales, GET /vales/{vale}, schemas ValeStoreRequest/Response, VehiculoAsignado) + CHANGELOG-openapi.md. Tests: tests/Feature/Api/V1/ValeControllerTest.php.

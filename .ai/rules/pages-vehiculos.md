---
paths:
  - 'app/Http/Requests/VehiculoRequest.php,resources/js/Pages/Vehiculos/Create.vue,resources/js/Pages/Vehiculos/Index.vue'
---

# Pages Vehiculos

## vehiculo.nro_placa opcional, fotografia obligatoria sólo al crear
A pedido del usuario (2026-09): `nro_placa` pasó a `nullable` en `VehiculoRequest` (la columna ya era nullable+unique en la migración original, sólo la validación exigía el valor) — un vehículo puede registrarse sin placa (ej. maquinaria pesada que aún no la tiene). `fotografia` pasó a condicional: `$this->method() === 'POST' ? 'required' : 'sometimes'` (mismo patrón ya usado en OperacionStoreRequest.php) — obligatoria al crear (store), pero `sometimes` al editar (update, PUT spoofeado) para no romper "dejar vacío conserva la foto actual" (VehiculoController::update() ya hacía unset($data['fotografia']) sin archivo nuevo).

Vehiculos/Create.vue: el asterisco rojo se movió de "Nro. Placa" a "Seleccionar foto" (sólo al crear, no en "Cambiar foto" de edición). Vehiculos/Index.vue: los 3 sitios que asumían `nro_placa` siempre presente ahora tienen fallback (`?? 'Sin placa'` en las dos cards/tabla, `?? vehiculo.codigo ?? '#id'` en el mensaje de confirmación de borrado) — cualquier otro lugar del código que muestre `vehiculo.nro_placa` sin fallback puede mostrar vacío/"null", revisar si se toca.

Tests: VehiculoControllerTest ahora usa `Storage::fake('public')` en `setUp()` + `UploadedFile::fake()->image(...)` en todo `post(route('vehiculos.store'), ...)` (la fotografia es obligatoria ahí). Casos cubiertos: crear sin nro_placa, fotografia obligatoria al crear (falla sin ella), fotografia opcional al actualizar.

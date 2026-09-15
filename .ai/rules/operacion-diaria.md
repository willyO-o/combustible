---
paths:
  - 'app/Http/Controllers/Api/V1/OperacionDiariaController.php,app/Http/Requests/OperacionStoreRequest.php,app/Actions/OperacionDiaria/*.php'
---

# Operacion Diaria

## API operacion-diaria: id_conductor respeta roles combinados (conductor+jefe-area/administrador)
A pedido del usuario (2026-09), sólo para la API (la web usa otro método, `OperacionDiariaController::datosConConductorResuelto()`, que el usuario confirmó que ya funciona bien y NO se tocó): `Api\V1\OperacionDiariaController` ahora tiene su propio `datosConConductorResuelto()` privado, llamado en `store()` y `update()` antes de pasar los datos a la Action.

Criterio "conductor puro" = `hasRole('conductor') && !hasAnyRole(['jefe-area','administrador','super-admin'])` — MISMO criterio ya usado en `Api\V1\CargaMaterialController` (no incluye `tecnico-mantenimiento`, ese rol no gestiona conductores/áreas). Sólo si es conductor puro se fuerza `id_conductor` = su propio `persona->conductor->id` (se ignora cualquier valor enviado, en create Y en update — un vehículo puede tener varios conductores asignados a la vez, titular + provisionales, así que confiar en lo que mande el cliente permitiría atribuir la operación a otro). Para cualquier usuario con un rol de gestión (aunque también sea `conductor`), se respeta el `id_conductor` validado tal cual llega — la Action (`Create`/`UpdateOperacionDiariaAction`) ya valida que sea uno de los `conductoresAsignados()` del vehículo, lanzando `ConductorNoAsignadoException` si no.

BUG separado encontrado y corregido en el mismo cambio: `ConductorNoAsignadoException` no tenía catch propio en el controller API (sólo `AreaNoAsignadaException` sí) — caía en el catch genérico `\Exception` y devolvía `500` en vez de `422`. Se agregó `catch (ConductorNoAsignadoException $e)` → `422` en `store()` Y `update()` (antes `update()` ni siquiera tenía el catch de `AreaNoAsignadaException`, pero esa excepción sólo la lanza Create, no Update, así que no hacía falta ahí).

`OperacionStoreRequest` NO se tocó (es compartida con la web; cambiar su regla `Rule::requiredIf` habría alterado el comportamiento del formulario web, que el usuario pidió no tocar) — el campo sigue "nullable" a nivel de validación para cualquier rol; la lógica de "quién puede enviar qué" vive enteramente en el controller de la API.

openapi.yaml bump 1.6.0→1.7.0: `OperacionDiariaStoreRequest.id_conductor`/`OperacionDiariaUpdateRequest.id_conductor` documentados como propiedades reales (antes decía "se calcula en el servidor, no debe enviarse" — ya no era cierto, ver historia de v1.md/v1-docs.md sobre discrepancias doc-vs-código en este proyecto), + nuevo ejemplo `422` de `ConductorNoAsignadoException` en ambos endpoints.

Tests: 4 nuevos en `tests/Feature/Api/V1/OperacionDiariaControllerTest.php` — conductor puro ignora id_conductor ajeno (usa el suyo, probado con un conductor PROVISIONAL para no confundir con "coincide con el titular por casualidad"), conductor+jefe-area puede registrar para otro conductor asignado, conductor+administrador ídem, y 422 (no 500) si el id_conductor enviado no está asignado al vehículo.

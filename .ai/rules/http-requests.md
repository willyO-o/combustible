---
paths:
  - 'app/Http/Controllers/ConductorController.php,app/Models/Conductor.php,app/Http/Requests/ConductorRequest.php'
---

# Http Requests

## Conductor datos personales viven en Persona, no en Conductor
La tabla `conductor` sólo tiene `id` (FK a `persona.id`, misma PK compartida) y `estado_conductor`. Todo dato "personal" (ci, nombres, paterno, materno, foto, celular, direccion, fecha_nacimiento) vive en `persona`, NO en `conductor` — `Conductor`'s #[Fillable] es sólo ['id','estado_conductor'].

Al crear: primero `Persona::create([...])`, luego `Conductor::create(['id' => $persona->id, 'estado_conductor' => ...])` dentro de una transacción. Al actualizar: `$conductor->persona->update([...])` para los campos personales y `$conductor->update(['estado_conductor' => ...])` por separado. Reglas de unicidad de `ci` deben apuntar a `Rule::unique('persona','ci')`, no a la tabla `conductor` (no tiene esa columna).

Ya existe un flujo paralelo de alta/edición en app/Actions/Personas/{Create,Update}PersonaAction.php (usado por PersonaController con tipo=conductor/jefe-area) que sigue este mismo patrón — úsalo como referencia si se necesita reconciliar ambos flujos.

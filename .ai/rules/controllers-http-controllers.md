---
paths:
  - 'app/Http/Controllers/AreaController.php,app/Models/Persona.php,app/Http/Controllers/UserController.php,app/Http/Controllers/PersonaController.php'
---

# Controllers Http Controllers

## Roles: nunca syncRoles con un único rol; formularios precargan con Persona::rolesParaFormulario()
2026-10-14: AreaController::sincronizarUsuarioJefeArea hacía syncRoles(['jefe-area']) y borraba conductor/administrador/técnico al designar un jefe (dejaba conductores con vehículo asignado pero sin rol conductor -> no podían registrar operaciones de su vehículo). Ahora assignRole('jefe-area'). Regla: para agregar un rol usar assignRole; syncRoles sólo con el conjunto COMPLETO elegido en un formulario (UpdatePersonaAction, que además conserva los roles ocultos). Personas/Edit y Usuarios/Form precargan rolesAsignados con Persona::rolesParaFormulario(): roles de la cuenta sin ocultos (super-admin: UserRequest/PersonaRequest lo rechazarían al guardar) ∪ conductor si (sin cuenta o con asignación vigente) ∪ jefe-area si tiene encargo vigente — si no, quien conserva un vehículo/área sin el rol aparecía desmarcado y al guardar se le cerraban. Diagnóstico de datos en producción: usuarios con asignación vigente sin rol conductor, o con rol jefe-area sin encargo vigente (ver consulta en la conversación del 2026-10-14).

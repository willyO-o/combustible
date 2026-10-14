---
paths:
  - 'app/Actions/Personas/*.php,app/Http/Requests/PersonaRequest.php,app/Http/Controllers/PersonaController.php,app/Http/Controllers/UserController.php,resources/js/Pages/Personas/*.vue,resources/js/Pages/Usuarios/Form.vue,resources/js/Components/RolesSelector.vue'
---

# Usuarios Js Components

## Personas y Usuarios: varios roles por persona (roles[]), ya no un único "tipo"
Desde 2026-10-14 Personas/Create y Edit eligen uno o varios roles (casillas, componente RolesSelector.vue + Utils/rolUtil.js etiquetaRol), mismo catálogo que Usuarios: Role sin config('acl.roles_ocultos'). El campo `tipo` (conductor|jefe-area|personal) se eliminó de PersonaRequest; ahora `roles[]` (sin roles = personal). Create/UpdatePersonaAction tratan cada rol por separado: conductor -> Conductor + asignación; jefe-area -> encargo; quitar uno cierra SÓLO sus registros (antes, con tipo único derivado en UserController::tipoDesdeRoles, agregar conductor a un jefe le cerraba el encargo y conductor+jefe nunca creaba el encargo — bug real, causa probable de jefes sin vehículos). syncRoles() vive SÓLO en la Action y conserva los roles ocultos (super-admin) que la cuenta ya tenía (antes editar un super-admin desde Usuarios se lo quitaba). PersonaRequest: roles distintos de conductor/jefe-area (administrador, tecnico-mantenimiento, futuros) exigen crear_usuario o usuario existente. Edit precarga rolesAsignados = roles de la cuenta (sin ocultos) ∪ deducidos de registros (conductor existente, encargo activo). Persona::tipo_actual sigue igual (badge único del listado). Tests: PersonaControllerTest y UserControllerTest.

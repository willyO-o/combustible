---
paths:
  - 'resources/js/Components/CiInput.vue,resources/js/Pages/Personas/*.vue,resources/js/Pages/Conductores/Form.vue,app/Http/Controllers/PersonaController.php,app/Http/Controllers/ConductorController.php,app/Http/Requests/ConductorRequest.php'
---

# Controllers Http Controllers Http Requests

## CI duplicado: aviso en vivo (CiInput) y cuenta de usuario opcional al registrar un conductor
(1) Campo CI de Personas (Create/Edit) y Conductores/Form = resources/js/Components/CiInput.vue: consulta GET search.persona-por-ci (PersonaController::verificarCi, withTrashed — persona.ci es UNIQUE en la BD aun con soft delete y no hay "restaurar", así que una eliminada también bloquea) con debounce 450 ms (CI >= 3), aviso fijo bajo el campo + link (conductor → conductores.show, resto → personas.edit) y SweetAlert al salir del campo (showWarning en alertUtil.js, una vez por CI, nombre escapado). Edición pasa :ignorar-id. Sólo aviso: el unique de PersonaRequest/ConductorRequest sigue siendo la autoridad. Ojo: rutas web NO responden errores en JSON (bootstrap/app.php sólo api/*) → sin ci = redirect, sin sesión = redirect login; el componente ignora respuestas inesperadas.
(2) ConductorController::store: tarjeta "Cuenta de Usuario" (sólo alta, v-can usuarios.crear) → crear_usuario/email/estado_usuario/password (ContrasenaInicialInput). ConductorRequest::reglasCuentaDeUsuario() sólo en alta, Rule::excludeIf(!crear_usuario). Backend exige can('usuarios.crear') (403) y crea User con SOLO rol conductor dentro de la misma transacción (si falla, no queda persona/conductor a medias). Esos 4 campos están en el except() de $datosPersona. Tests: ConductorControllerTest, PersonaControllerTest (verificar_ci_*).
Pendiente detectado: UserController usa can('crear-usuario'|'editar-usuario'|'cambiar-estado-usuario'), permisos que NO existen (los reales: usuarios.crear/editar) → un administrador no super-admin recibe 403 en Usuarios; causa de 5 fallos de UserControllerTest. Se informó al usuario, sin cambiar.

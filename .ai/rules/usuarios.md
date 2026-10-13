---
paths:
  - 'app/Models/User.php,app/Actions/Personas/*.php,app/Http/Requests/PersonaRequest.php,app/Http/Requests/UserRequest.php,app/Http/Controllers/UserController.php,app/Http/Controllers/AreaController.php,resources/js/Components/ContrasenaInicialInput.vue,resources/js/Pages/Personas/*.vue,resources/js/Pages/Usuarios/Form.vue'
---

# Usuarios

## Contraseña inicial de cuentas nuevas: editable, por defecto CI#Plusmetals (User::contrasenaPorDefecto)
Desde 2026-10-13 (pedido del usuario) Personas/Create, Personas/Edit (sólo si la persona aún no tiene usuario) y Usuarios/Form (sólo al crear) muestran el campo `password` vía resources/js/Components/ContrasenaInicialInput.vue: prellenado con `${ci}#Plusmetals`, sigue el CI en vivo hasta que se edita a mano, botón "Usar formato" para restablecer, visible como texto (autocomplete=new-password). Formato único en backend: User::contrasenaPorDefecto($ci); User::contrasenaInicial($escrita, $ci) usa la escrita o, si viene vacía, el formato — la usan CreatePersonaAction y UpdatePersonaAction (sólo al CREAR la cuenta; nunca cambia la de un usuario existente) y AreaController (jefe de área, sin campo en UI → siempre el formato). Validación `nullable|string|max:255|Password::min(8)->letters()->numbers()` (mismo mínimo que UserPasswordRequest); PersonaRequest la excluye si !crear_usuario o la persona ya tiene user; UserRequest la excluye al editar ($isEdit → 'exclude'). search.personas-sin-usuario devuelve `ci` para prellenarla. Tests: PersonaControllerTest (escrita, vacía→formato, débil→422 sin crear nada, ignorada sin crear_usuario, edición crea cuenta, edición con usuario no cambia) y UserControllerTest (con super-admin: ese archivo tiene 5 fallos previos por el permiso crear-usuario no asignado al administrador del setUp).

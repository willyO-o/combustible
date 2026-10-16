---
paths:
  - 'app/Http/Controllers/ConductorController.php,resources/js/Pages/Conductores/Show.vue,routes/web.php'
---

# Conductores

## Perfil del operario: lápiz para cambiar sólo el cargo
A pedido del usuario (2026-10-16): en Conductores/Show.vue, junto al cargo, un lápiz (v-can conductores.editar) abre edición en línea con CargoSelect (incluye el "+" de alta rápida) y guarda con PATCH conductores/{conductor}/cargo (conductores.cargo.actualizar, middleware permission:conductores.editar) → ConductorController::actualizarCargo(): validación inline con el MISMO criterio que ConductorRequest (cargo ACTIVO o el que ya tiene aunque esté inactivo, o null) y sólo actualiza persona.id_cargo; vuelve con back() + flash success. show() pasa `cargos` = Cargo::seleccionables($persona->id_cargo). Tests en ConductorControllerTest (*actualizar_cargo*).

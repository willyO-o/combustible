---
paths:
  - 'app/Actions/Areas/*.php,app/Http/Controllers/AreaController.php,app/Http/Controllers/ProfileController.php,resources/js/Components/VehiculosAreaTabla.vue,resources/js/Components/HistorialVehiculosAreaTabla.vue,resources/js/Pages/Areas/Show.vue,resources/js/Pages/Profile/Edit.vue'
---

# Js Pages Profile

## Vehículos de un área: Actions + tablas compartidas entre ficha del Área y Mi Perfil
Desde 2026-10-14 los "vehículos vigentes" e "historial de asignaciones" de un área se arman SÓLO en app/Actions/Areas/ListVehiculosVigentesAreaAction y ListHistorialVehiculosAreaAction (código movido tal cual desde AreaController::show; los test_show_* de AreaControllerTest cubren que la forma no cambió). Los usan AreaController::show() y ProfileController::ficha() (ficha.areas[].vehiculos / .historial, por cada área a cargo del jefe). En el front, las tablas viven en resources/js/Components/VehiculosAreaTabla.vue (prop permitirCambiarAsignacion + emit cambiar-asignacion: el modal de reasignar sigue en Areas/Show.vue; el perfil no lo muestra) y HistorialVehiculosAreaTabla.vue; helpers de badges/nombre en resources/js/Utils/asignacionVehiculoUtil.js. Cambiar columnas o formato ahí afecta a ambas pantallas. Mi Perfil: tarjeta "Vehículos a mi Cargo" con selector de área (si tiene varias), pestañas Vue (no JS de Bootstrap, estilos .perfil-tabs) y link a areas.show con v-can areas.ver.

---
paths:
  - 'app/Http/Controllers/ValeController.php,app/Http/Requests/ValeRequest.php,resources/js/Pages/Vales/Create.vue'
---

# Pages Vales

## Vales: el conductor se elige entre TODOS los operarios activos
A pedido del usuario (2026-10-16, la asignación conductor-vehículo aún no es confiable): al crear un vale, Vales/Create.vue usa un Multiselect LOCAL con la prop `operarios` (ListOperariosDisponiblesAction, todos los ACTIVO) en vez del SearchSelect a search.conductores. Los asignados al vehículo (meta.conductores_asignados de searchVehiculos: titular + provisionales) van primero con insignia; al elegir vehículo se preselecciona el titular (o el único asignado), si no queda vacío con aviso "elige manualmente". NO cambiar searchConductores(): CargasCombustible/Create.vue lo usa con id_vehiculo esperando sólo el titular. ValeRequest: POST exige conductor ACTIVO (Rule::exists ... estado_conductor ACTIVO, sin borrar); PUT sólo exists (el conductor ya está fijo y puede haber quedado inactivo). API 1.18.0 documentado; la app usa operaciones_diarias.operarios.

**ACTUALIZADO 2026-10-26:** el conductor puro ya no elige operario al emitir un vale: recibe `operarios = []` + `operarioPropio` (su propia ficha, mostrada bloqueada) y ValeRequest::prepareForValidation() fuerza su id_conductor (web y API). Ver .ai/rules/cargas-combustible-js-pages-operacion.md.

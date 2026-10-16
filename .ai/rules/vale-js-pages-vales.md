---
paths:
  - 'app/Http/Controllers/ValeController.php,app/Http/Requests/ValeRequest.php,app/Actions/Vale/ListValeAction.php,resources/js/Pages/Vales/Create.vue'
---

# Vale Js Pages Vales

## Vales: TODOS los vehículos para cualquier usuario (sin filtro por área del jefe)
A pedido del usuario (2026-10-16), reemplaza la restricción "jefe-area sólo vehículos de sus áreas" (vale.md / api-v1.md / js-pages-vales.md): el alta de vales recibe vehiculosParaFormularioVale() = TODOS los ACTIVO, con los de las áreas del jefe primero y meta.propio (insignia "De mi área"; si tiene uno solo se preselecciona). ValeRequest ya NO tiene reglaVehiculoDeAreaDeJefe (web y API aceptan cualquier vehículo). ListValeAction: jefe-area ve los vales de sus áreas OR id_user = él (lo que emitió para otras áreas). search.vehiculos conserva el filtro por área del jefe (vehiculosParaVale($user, soloSusAreas: true)) porque lo usan CargasCombustible, Conductores/Index, Personas y Usuarios. Operarios ya eran todos los activos. API 1.20.0.

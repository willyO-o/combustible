---
paths:
  - 'app/Http/Controllers/ValeController.php,resources/js/Pages/Vales/Create.vue'
---

# Js Pages Vales

## Vales: vehículo precargado (lista completa) en vez de buscador
A pedido del usuario (2026-10-16): en el alta de vales el vehículo es un Multiselect LOCAL con la prop `vehiculos` (ValeController::create → vehiculosParaVale($user) SIN límite: jefe de área sólo los de sus áreas vía restringirVehiculosPorAreaDeJefe(), administrador todos los ACTIVO), ya cargados y filtrables al escribir. searchVehiculos() (search.vehiculos) sigue igual —limit 20— porque lo usan CargasCombustible, Conductores/Index, Personas Create/Edit y Usuarios/Form; ambos comparten vehiculosParaVale(). form.id_vehiculo ahora es el id (no objeto); @change → onVehiculoChange busca el vehículo en props.vehiculos y llama vehiculoSeleccionado(meta). Con un único vehículo se preselecciona en onMounted. TRAMPA ya ocurrida en producción: el v-else (select bloqueado de edición) debe ir inmediatamente después del v-if del Multiselect; insertar cualquier nodo entre ambos hace que el alta renderice el select de edición y lea props.vehiculoActual/conductorActual undefined.

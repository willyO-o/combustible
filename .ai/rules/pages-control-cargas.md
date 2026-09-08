---
paths:
  - 'app/Http/Controllers/CargaMaterialController.php,resources/js/Pages/ControlCargas/Index.vue'
---

# Pages Control Cargas

## Fletes y Viajes (ControlCargas/Index): filtros de fecha y placa
Index.vue (listado "Fletes y Viajes") filtra por rango de fecha_apertura (DateRangeFilter.vue, default-range="Este mes") y por id_vehiculo_externo (Multiselect searchable con vehiculoOptions, mismo patrón que Reportes/ControlCargasReporte.vue). CargaMaterialController::index() aplica el default "Este mes" en el servidor (whereDate fecha_apertura) igual que ValeController/CargaCombustibleController, y pasa vehiculosExternos (id, nro_placa, propietario) como prop para las opciones del select. El watcher del filtro en el .vue manda fecha_desde/fecha_hasta tal cual (sin "|| undefined") para que "Limpiar filtros" pueda quitar el rango.

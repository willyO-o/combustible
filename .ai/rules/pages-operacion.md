---
paths:
  - 'app/Http/Controllers/OperacionDiariaController.php,app/Actions/OperacionDiaria/*.php,app/Http/Requests/OperacionStoreRequest.php,resources/js/Pages/Operacion/Create.vue'
---

# Pages Operacion

## Operación Diaria: sección de mantenimiento (ámbito operacion_diaria)
Operacion/Create.vue tiene una sección "Mantenimiento en operación diaria" que lista TODOS los tipo_mantenimiento activos de ambito='operacion_diaria' (OperacionDiariaController::tiposMantenimientoOperacionDiaria(), prop tiposMantenimiento; en edit() también props.operacion.mantenimientos_edit vía OperacionDiaria::mantenimientosOperacionEdit()). Por cada tipo: si tipo_valor='cantidad' -> input numérico (v-decimal="2") con unidad_medida; si 'booleano' -> checkbox "Realizado". Todo es opcional (no todos aplican). El form.transform() manda sólo las entradas con algo cargado: {id_tipo_mantenimiento, valor|null, realizado:'SI'|null}. Persistencia: pivote mantenimiento_operacion_diaria (valor decimal, realizado enum SI/NO nullable), relación OperacionDiaria::mantenimientosOperacion() (belongsToMany a TipoMantenimiento, using MantenimientoOperacionDiaria pivot). SincronizarMantenimientosOperacionAction (inyectada en Create/UpdateOperacionDiariaAction) hace detach + attach sólo de filas con valor o realizado no nulos. destroy() del controller hace ->mantenimientosOperacion()->detach() antes de delete (FK restrict). Validación en OperacionStoreRequest: mantenimientos.*.id_tipo_mantenimiento con Rule::exists('tipo_mantenimiento','id')->where('ambito','operacion_diaria'). NO se usa la tabla intervalo_mantenimiento_tipo aquí (eso es taller/tipo de vehículo). Show.vue y el PDF de operación todavía NO muestran estos controles.

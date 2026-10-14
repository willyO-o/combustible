---
paths:
  - 'app/Actions/OperacionDiaria/*.php,app/Http/Controllers/OperacionDiariaController.php,resources/js/Pages/Operacion/Create.vue'
---

# Js Pages Operacion

## Editar operación diaria: el conductor original siempre es válido; verificadas no se editan
UpdateOperacionDiariaAction: id_conductor vacío -> se conserva el de la operación (columna NOT NULL; antes reventaba con SQL error); mantener el MISMO conductor no exige que siga asignado al vehículo (operaciones históricas tras reasignación); sólo un CAMBIO exige que el nuevo esté en conductoresAsignados(). Aplica a web y API (Action compartida). OperacionDiariaController::edit() agrega el conductor de la operación al meta.conductoresAsignados de su vehículo (incluirConductorDeLaOperacion(), etiqueta "— ya no asignado"), si no el watcher de id_vehiculo en Create.vue lo descartaba y lo reemplazaba por el actual en silencio. Web edit()/update() rechazan operaciones VERIFICADO (igual que destroy); Api\V1 update() todavía NO lo bloquea. Pendiente: operacion_diaria.id_verificador tiene FK a users.id, pero verificarOperacion() guarda id_persona y la relación verificador() es belongsTo(Persona) — falla si no existe un user con ese id.

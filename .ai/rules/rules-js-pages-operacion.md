---
paths:
  - 'app/Http/Requests/OperacionStoreRequest.php,app/Actions/OperacionDiaria/*.php,app/Models/OperacionDiaria.php,app/Rules/GreaterThanPreviousReading.php,resources/js/Pages/Operacion/Create.vue'
---

# Rules Js Pages Operacion

## Operación diaria: una por vehículo y día; regularización de fechas por rol
A pedido del usuario (2026-10-16), web y API (OperacionStoreRequest compartida, API 1.17.0):
- [REEMPLAZADO 2026-11-05: ahora una por vehículo, día y turno, ver sección "una por vehículo, día y turno"] Una sola operación por vehículo y por DÍA de fecha_inicio (sin importar turno). Validado en OperacionStoreRequest::validarFechaInicio() (after-hook, error en fecha_inicio, excluye la propia al editar) Y dentro de la transacción de Create/UpdateOperacionDiariaAction con Vehiculo::lockForUpdate() + OperacionDiaria::delVehiculoEnElDia() -> OperacionDiariaDuplicadaException (API la devuelve 422, web flash error). En producción no había duplicados previos.
- [DESDE 2026-11-05 ES SÓLO EL "MODO TRANSICIÓN", ver .ai/rules/js-directives.md "modo transición/estricto"] fecha_inicio: User::puedeRegularizarOperaciones() (jefe-area/administrador/super-admin) = CUALQUIER fecha, incluso futura (el usuario lo pidió explícito). El resto: hoy o ayer (OperacionStoreRequest::DIAS_ATRAS_SIN_REGULARIZAR = 1, cubre turno noche) y nunca futura. Al editar sin cambiar de día no se aplica el límite.
- GreaterThanPreviousReading acepta un 3er parámetro $hastaFecha: la operación diaria compara la lectura con la última carga con fecha_carga <= fecha_inicio (si no, regularizar una fecha pasada siempre fallaba). CargaCombustibleRequest no lo pasa (comportamiento previo).
- Create.vue: props puedeRegularizar/diasAtrasSinRegularizar limitan el DateTimeInput (nuevas props minDate/maxDate, pasar inicio/fin de día) y muestran un aviso; la autoridad es el servidor.

## Operación diaria: una por vehículo, día y turno (hasta 2 por día)
Desde 2026-11-05 (pedido del usuario) REEMPLAZA "una por vehículo y día sin importar turno": todos los vehículos (sin flag) pueden tener una operación DIA y una NOCHE por día de fecha_inicio. NO se valida la hora ni el cruce de horarios (el relevo 18:00/18:05 no es exacto) ni el turno vs hora: sólo id_vehiculo + DATE(fecha_inicio) + turno. OperacionDiaria::delVehiculoEnElTurno() (reemplazó delVehiculoEnElDia) se usa en OperacionStoreRequest::validarFechaInicio() y en Create/UpdateOperacionDiariaAction (lockForUpdate; Update también revisa cuando sólo cambia el turno). El error sigue en fecha_inicio (contrato API). Create.vue en alta propone 08–18 (Día) / 19–05 día siguiente (Noche) al cambiar de turno sólo si las horas siguen siendo las propuestas. API 1.24.0. Las lecturas entre turnos NO se encadenan (sólo contra la última carga).

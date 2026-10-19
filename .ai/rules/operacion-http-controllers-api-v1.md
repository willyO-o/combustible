---
paths:
  - 'app/Http/Requests/OperacionStoreRequest.php,app/Models/TipoVehiculo.php,app/Http/Requests/TipoVehiculoRequest.php,resources/js/Pages/TiposVehiculo/**,resources/js/Pages/Operacion/Create.vue,app/Http/Controllers/Api/V1/ParametrosController.php'
---

# Operacion Http Controllers Api V1

## Operación diaria: topes de lectura por jornada en el tipo de vehículo
Desde 2026-10-19 (decisión del usuario: topes por tipo de vehículo, bloqueo sin excepción, sólo operación diaria). tipo_vehiculo.km_maximo_jornada (decimal 10,2, default 1000) y horometro_maximo_jornada (decimal 6,2, default 24, máx. 24 en TipoVehiculoRequest); null = sin tope. TipoVehiculo::topeJornada($tipoMedicion) devuelve el que corresponde al tipo_medicion del VEHÍCULO (un tipo puede tener vehículos de ambas mediciones). OperacionStoreRequest::validarTopeDeLectura() (after-hook, web y API) agrega error en kilometraje_fin/horometro_fin si fin − inicio > tope, sólo si esas lecturas ya pasaron sus reglas; mensaje con número formateado es-BO y "¿falta un punto decimal?". La API NO admite PUT parcial (exige payload completo), así que no hay fallback a lecturas guardadas. Frontend: meta.tope_jornada en vehiculosAsignados (OperacionDiariaController::vehiculoOpt) → estado 'excede' (rojo) en la franja .op-calc; app Flutter: vehiculos[].tope_jornada en GET /parametros/colecciones (openapi 1.22.0). Pendiente, no pedido aún: comparar la lectura inicial con la operación anterior (hoy sólo con la última carga, GreaterThanPreviousReading) y aplicar topes a Carga de Combustible.

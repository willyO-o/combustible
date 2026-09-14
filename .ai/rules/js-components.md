---
paths:
  - 'app/Models/Vehiculo.php,app/Models/TipoVehiculo.php,app/Http/Requests/VehiculoRequest.php,app/Http/Requests/TipoVehiculoRequest.php,app/Http/Controllers/VehiculoController.php,resources/js/Pages/Vehiculos/**,resources/js/Pages/TiposVehiculo/**,resources/js/Components/UnidadCapacidadSelect.vue'
---

# Js Components

## Capacidad de vehículo: valor + unidad fija (m³/yd³/L/gal/t/kg/lb + Otro), no ficha técnica genérica
A pedido del usuario (2026-09), `vehiculo` tiene `capacidad` (decimal 8,2 nullable) + `capacidad_unidad` (string 20 nullable) para volquetas/excavadoras/cargadores/etc. ("3 cubos" = 3.00 m³). `tipo_vehiculo.unidad_capacidad_sugerida` (string 20 nullable) precarga esa unidad en el form de Vehiculo al elegir el tipo (Vehiculos/Create.vue observa `form.id_tipo_vehiculo` y sólo la setea si `capacidad_unidad` está vacío — no pisa lo que el usuario ya eligió).

`capacidad_unidad`/`unidad_capacidad_sugerida` NO son enum(): el selector ofrece 7 valores fijos (m³, yd³, L, gal, t, kg, lb — sólo volumen/peso, las dos categorías que describen "cuánto carga/contiene" algo) + "Otro" que habilita texto libre, mismo criterio que `tipo_mantenimiento.unidad_medida`. El componente compartido `resources/js/Components/UnidadCapacidadSelect.vue` encapsula ese select+"Otro"+input; v-model es un string plano (no objeto). Se usa en Vehiculos/Create.vue (`capacidad_unidad`) y TiposVehiculo/Create.vue (`unidad_capacidad_sugerida`) — cualquier tercer lugar que necesite elegir una unidad de capacidad debe reusar este componente, no reimplementar el patrón.

DECISIÓN DE ALCANCE explícita del usuario: esto es sólo "capacidad" (un valor por vehículo). Quedó fuera a propósito una "ficha técnica" más amplia (presión hidráulica, consumo L/h, temperatura, vibración, rendimiento t/h, etc.) — esas son especificaciones técnicas de cantidad variable por tipo de máquina, no encajan en un único campo "capacidad" con unidad fija, y necesitarían su propia tabla tipo `vehiculo_especificacion` (nombre+valor+unidad) si se pide más adelante. No agregues esas unidades al selector de capacidad ni asumas que esta tabla las cubre.

`capacidad_unidad` es `required_with:capacidad` en VehiculoRequest (un número sin unidad no sirve), pero `capacidad` NO es required_with `capacidad_unidad` — la unidad puede llegar sola por el default del tipo antes de que alguien cargue el valor real. Cobertura: VehiculoControllerTest (crear con capacidad+unidad, unidad requerida si hay capacidad, crear sin capacidad) y TipoVehiculoControllerTest (guardar/omitir unidad_capacidad_sugerida).

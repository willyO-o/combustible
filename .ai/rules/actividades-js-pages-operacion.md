---
paths:
  - 'app/Http/Requests/OperacionStoreRequest.php,app/Actions/Actividades/SincronizarActividadesRealizadasAction.php,resources/js/Pages/Operacion/*.vue'
---

# Actividades Js Pages Operacion

## Actividad realizada: cantidad y unidad_medida son opcionales
A pedido del usuario (2026-10-16): en el modal de actividad de operación diaria, cantidad y unidad_medida ya no son obligatorias (web y API 1.17.0). Validación nullable en OperacionStoreRequest (cantidad nullable|numeric|min:1, unidad_medida nullable|string|max:30) y en ambos validarActividad() (web y Api/V1). SincronizarActividadesRealizadasAction usa `?? null` (un cliente puede omitirlas). Columnas actividad_realizada.cantidad/unidad_medida y actividad.unidad_medida ya eran nullable. Vistas muestran "—" si faltan; reportes ya toleraban null (SUM/COALESCE, formatearCantidadReporte). Sólo `actividad` (+ lugar u origen/destino según tipo_medicion) siguen obligatorios.

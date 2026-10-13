---
paths:
  - 'app/Actions/Actividades/SincronizarActividadesRealizadasAction.php,app/Http/Requests/OperacionStoreRequest.php,resources/js/Pages/Operacion/*.vue,app/Libraries/Reportes.php,app/Http/Controllers/OperacionDiariaController.php,app/Http/Controllers/Api/V1/OperacionDiariaController.php'
---

# Controllers Api V1

## actividad_realizada.hora_inicio/hora_fin: obsoletas, ya no se capturan (no borrar columnas)
A pedido del usuario (2026-10-13) las actividades de operación diaria ya no llevan horas. Las columnas siguen en BD (nullable) por los registros históricos de producción.
- Validación: OperacionStoreRequest y ambos validarActividad() (web y Api/V1) ya NO tienen reglas hora_*; si un cliente viejo (app Flutter) las envía, validated() las descarta. Nueva regla `actividades_realizadas.*.id` nullable|integer.
- SincronizarActividadesRealizadasAction nunca toma horas del cliente: antes del detach() lee las horas guardadas keyed por pivot id y, si la actividad entrante trae ese `id` (el form de edición lo reenvía desde actividadesRealizadasEdit()), las conserva; actividades nuevas o ids de otra operación → null.
- UI: modal de Operacion/Create.vue sin campos de hora; "Horario" del resumen (Create) y columna Horario (Show, computed tieneHorario) sólo aparecen si alguna actividad antigua tiene horas. PDF (generarReporteOperacionDiaria): columnas DE/A sólo si alguna actividad tiene horas ($conHoras), si no su ancho pasa a ACTIVIDAD.
- openapi 1.14.0: hora_* deprecated/ignorados, `id` opcional en PUT. Tests en OperacionDiariaControllerTest (web y Api/V1).

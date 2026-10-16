---
paths:
  - 'app/Http/Controllers/OperarioReportController.php,resources/js/Pages/Reportes/OperarioReporte.vue,resources/js/Pages/Conductores/Show.vue'
---

# Pages Conductores

## Reporte por operario (actividades + cargas de combustible)
Creado 2026-10-16 a pedido del usuario. OperarioReportController::index (ruta operacion-diaria.reporte.operario.index, GET reportes/operacion-diaria/operario, permiso operacion-diaria.reporte.operario en UserSeeder::permisosOperacionDiaria → jefe-area + admin, nunca conductor) → Reportes/OperarioReporte.vue. Mismo patrón que la bitácora por vehículo: UN Multiselect (id_conductor, todos los operarios incl. inactivos con sufijo) + DateRangeFilter "Este mes", sin operario datos=null. Datos: operaciones del operario (id_conductor) con sus actividades (1 consulta, groupBy id_operacion_diaria), resumen por actividad+unidad en SQL (veces, jornadas, SUM cantidad), cargas_combustible por cc.id_conductor (las ANULADAS se listan tachadas pero NO suman en totales), totales en SQL. Enlace "Reporte de actividades y combustible" en el header de Conductores/Show.vue (v-can). Sin PDF/Excel todavía. Tras desplegar: `php artisan db:seed --class=Database\Seeders\ActualizarPermisosSeeder --force` o el menú/botón no aparecen. Tests: tests/Feature/OperarioReportTest.php.

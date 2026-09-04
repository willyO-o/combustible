---
paths:
  - 'app/Http/Controllers/**/*.php,app/Http/Requests/**/*.php,app/Actions/**/*.php'
---

# Requests Actions

## Filtros/condicionales de negocio: resolver en la BD, no con múltiples idas y vueltas en PHP
Cuando una validación o un filtro necesita comprobar varias condiciones sobre un mismo registro (existe + estado + "no tiene relación X"), resolverlo en UNA sola consulta a la BD (where/whereHas/whereDoesntHave/exists), no encadenando exists()+find()+exists() por separado — cada llamada es un round-trip aparte y esto va a crecer con el volumen de registros.

Ejemplo real corregido (OrdenTrabajoRequest::rules(), validación de id_solicitud_mantenimiento): antes hacía `Rule::exists(...)` + `SolicitudMantenimiento::find($value)` (para leer `estado`) + `OrdenTrabajo::where(...)->exists()` (para ver si ya tiene orden) = 3 consultas. Se colapsó a 1: `SolicitudMantenimiento::where('id', $value)->where('estado','PENDIENTE')->whereDoesntHave('ordenTrabajo')->exists()` (el NOT EXISTS lo resuelve el motor). Regla de oro: si vas a leer un modelo sólo para comprobar una condición (`->find()->estado === 'X'`, `->count() > 0`, etc.), esa condición casi siempre cabe como `->where(...)` o `->whereHas()/whereDoesntHave()` en la misma consulta que ya estás armando.

Mismo criterio para colecciones ya cargadas: filtrar/agrupar con `->where()`, `->groupBy()`, `whereIn`, subconsultas correlacionadas en `selectRaw`, etc. en el query builder — no traer todo con `->get()` y después `->filter()`/`->map()`/bucles en PHP para aplicar la condición (excepción: cuando el dato ya está en memoria por otra razón y filtrar ahí evita una consulta extra, ese caso sí es válido). Ver también la convención ya documentada de "agregación en la BD" en `.ai/rules/controllers.md` (Dashboard: gráfico de órdenes de trabajo) y en `.ai/rules/reportes-libraries.md` (obtenerResumen: fan-out con leftJoinSub pre-agregado).

Test de guarda sugerido cuando se corrige un caso así: contar queries reales con `DB::enableQueryLog()`/`DB::getQueryLog()` en el test (ver `OrdenTrabajoControllerTest::test_valida_la_solicitud_de_origen_en_una_sola_consulta`), filtrando por tipo de sentencia (SELECT) para no contar falsos positivos por nombres de columna/tabla coincidentes en otros INSERT/UPDATE.

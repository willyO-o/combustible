---
paths:
  - 'app/Actions/OperacionDiaria/ListOperacionesDiariasAction.php,app/Http/Controllers/OperacionDiariaController.php,app/Http/Controllers/Api/V1/OperacionDiariaController.php'
---

# Http Controllers Api V1

## Listado de operación diaria: jefe-area + conductor ve sus áreas Y sus propias operaciones
Regla de negocio confirmada por el usuario (2026-10-14): un jefe de área registra operaciones de los vehículos de sus áreas y, si además es conductor, también las de su propio vehículo aunque esté en otra área (vehiculosDisponibles() = unión). ListOperacionesDiariasAction (web y API) para jefe-area filtra whereIn(id_area, sus áreas) OR id_conductor = su id_persona si tiene rol conductor — antes sólo por área y sus propias operaciones desaparecían del listado. index() web agrega al propio jefe-conductor al combo de filtro de conductores. No se puede registrar su vehículo de otra área a nombre de otro operario (la Action exige conductor asignado). Tests: *jefe_conductor* en OperacionDiariaControllerTest (web) y Api/V1.

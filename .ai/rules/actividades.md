---
paths:
  - 'app/Http/Controllers/Api/V1/OperacionDiariaController.php,app/Http/Controllers/Api/V1/ParametrosController.php,app/Actions/Actividades/SincronizarActividadesRealizadasAction.php'
---

# Actividades

## API V1 operación diaria: mantenimientos_operacion y catálogo en colecciones
Paridad con el módulo web de operación diaria en la API V1:
- ParametrosController::colecciones() expone `operaciones_diarias.tipos_mantenimiento` (activos, ámbito operacion_diaria; id, tipo_mantenimiento, tipo_valor, unidad_medida) además de `operaciones_diarias.actividades_sugeridas`. Es el catálogo para armar el arreglo `mantenimientos` del POST/PUT.
- Api\V1\OperacionDiariaController store/show/update hacen ->load([... , 'mantenimientosOperacion']) para devolver los controles registrados (clave JSON `mantenimientos_operacion`). destroy ya hacía detach.
- SincronizarActividadesRealizadasAction lee origen/destino/lugar con `?? null` (son mutuamente excluyentes según tipo_medicion): omitir el par que no aplica NO debe romper. Igual que `id_material ?? null`.
Todo documentado en public/docs/openapi.yaml (v1.1.0) + public/docs/CHANGELOG-openapi.md. Tests: tests/Feature/Api/V1/OperacionDiariaControllerTest.php y ParametrosControllerTest.

---
paths:
  - 'app/Models/CargaCombustible.php,app/Http/Controllers/CargaCombustibleController.php,app/Http/Requests/CargaCombustibleRequest.php,database/migrations/2026_05_28_000010_create_carga_combustible_table.php'
---

# Requests Migrations

## CargaCombustible: nro_carga/gestion auto-numbering + concepto ("A utilizarse en")
CargaCombustible now follows the same nro_carga/gestion auto-numbering pattern as Vale/CargaMaterial/SolicitudMantenimiento/OrdenTrabajo: CargaCombustible::boot() (creating closure) sets `gestion` via calcularGestion() (reads ParametrosEmpresa::first()->parametros_vale->mes_ciclo_contable) and `nro_carga` via siguienteNroCarga() (lockForUpdate, scoped per gestion). The `nro` accessor (appended) formats it as `nro_carga` zero-padded (digitos_serie) + `/gestion`, same as Vale/CargaMaterial. Any test that creates a CargaCombustible (factory or ::create()) directly — not just through the store endpoint — now needs a ParametrosEmpresa row to exist first (see crearParametrosEmpresa() helpers in CargaCombustibleControllerTest / Api/V1/CargaCombustibleControllerTest / CargasCombustibleReportControllerTest::setUp()), otherwise boot() throws on ParametrosEmpresa::first() being null.

`concepto` (existing nullable string(255) column, label "A utilizarse en") is now wired end-to-end: validated in CargaCombustibleRequest (nullable|string|max:255), fillable on the model, shown/edited in CargasCombustible/Create.vue (disabled on edit like nro_factura), shown in Index.vue/CargaCombustibleDetalleModal.vue, returned by CargaCombustibleController::detalle(), and documented in openapi.yaml (CargaCombustibleStoreRequest + CargaCombustible schema).

Also fixed a pre-existing bug found while adding this: the carga_combustible migration's unique index was named 'unique_nro_carga_gestion', colliding with carga_material's index of the same name — harmless on MySQL (per-table namespace) but breaks a fresh SQLite migrate (global index namespace), failing EVERY feature test. Renamed to 'unique_carga_combustible_nro_gestion'. If a future migration reuses a unique/index name across tables, expect the same class of failure only under SQLite/tests.

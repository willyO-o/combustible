---
paths:
  - 'app/Http/Controllers/VehiculoController.php,app/Http/Controllers/ConductorController.php,app/Http/Controllers/PersonaController.php,app/Libraries/Reportes.php,app/Libraries/ReportesExcel.php'
---

# Controllers Libraries Libraries

## Listados vehículos/conductores/personas: vacío = celda en blanco; reportes nunca dan 500
A pedido del usuario (2026-10-13): en los PDF/Excel de los 3 listados, un campo nulo o sin registros ligados sale EN BLANCO — nada de "—", "Sin placa", "Sin asignar", "Sin operario", "Sin licencia", "Sin vehículo asignado", "Sin usuario" (Persona.tipo sin relaciones = null, igual que Persona::tipo_actual). Los textos de encabezado ("Sin filtros: …") y el mensaje de tabla vacía sí se mantienen. Los "—" de los OTROS reportes (cargas, control de cargas, operación diaria) no se tocaron.
- Nombres: se arman con trim(implode(' ', array_filter([...]))) ?: null, no con el accessor Persona::nombre_completo (deja espacios sobrantes). Listas (áreas, vehículos) hacen ->map()->filter()->implode() para no dejar ", " sueltas; conteo "sin vehículo" usa el flag tiene_vehiculo (asignacionesActivas->isNotEmpty()), no la etiqueta.
- Robustez: Reportes::textoCalibri() hace mb_scrub + @iconv y cae a mb_convert_encoding (antes iconv devolvía false con UTF-8 inválido/emoji → TypeError 500 en TODOS los PDF). ReportesExcel::textoSeguro() (StringHelper::sanitizeUTF8) en escribirValor() y en el encabezado (setCellValueExplicit TYPE_STRING, nunca fórmula).
- reportePdf/reporteExcel de los 3 controladores van en try/catch(\Throwable) → errorAlGenerarReporte(): report($e) + redirect a {modulo}.index con los mismos FILTROS_LISTADO y flash 'error'.
- Tests: el PDF se lee descomprimiendo streams y tomando sólo "(...) Tj" (helper textoDelPdf; el logo también es un stream y contiene bytes como \x97). Simular falla de servidor renombrando una tabla que el listado carga (DB::statement ALTER TABLE ... RENAME) + Exceptions::fake(); ojo que el filtro debe coincidir con alguna fila o no se ejecuta la carga eager.

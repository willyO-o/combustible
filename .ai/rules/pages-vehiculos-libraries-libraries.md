---
paths:
  - 'app/Models/Vehiculo.php,app/Http/Requests/VehiculoRequest.php,app/Http/Controllers/VehiculoController.php,resources/js/Pages/Vehiculos/**,app/Libraries/Reportes.php,app/Libraries/ReportesExcel.php'
---

# Pages Vehiculos Libraries Libraries

## vehiculo.chasis: opcional, único (como la placa) y en mayúsculas
Desde 2026-11-05 (pedido del usuario): vehiculo.chasis string(50) nullable, migración aditiva 2026_11_05_110812 SIN índice único en BD. VehiculoRequest::prepareForValidation() hace trim + mb_strtoupper (vacío -> null) y valida Rule::unique('vehiculo','chasis')->ignore($vehiculo)->whereNull('deleted_at'), igual que nro_placa. Se muestra en Vehiculos/Create, Show, Index (tabla y tarjetas), entra al buscador unificado `buscar` de VehiculoController::consultaListado(), en el PDF del listado (columna CHASIS, anchos suman 263 mm), en TODOS los Excel con filas de vehículos propios (listado, cargas, rendimiento, uso de operación diaria: columna tras PLACA, ojo con la fila TOTALES) y como "Chasis: X" en el encabezado de los detalles por vehículo (rendimiento detalle y bitácora, PDF y Excel). Decisión del usuario: NO va en vale, comprobante de egreso ni otros documentos. Factory: state conChasis(). API: sale sola en el esquema Vehiculo completo (openapi 1.25.0).

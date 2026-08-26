---
paths:
  - 'app/Libraries/Reportes.php,app/Http/Controllers/CargaCombustibleController.php'
---

# Libraries Http Controllers

## Comprobante de egreso de combustible: generación PDF + wiring
Reportes::generarComprobanteEgreso($carga, $modo='I', $nombreArchivo=null) genera el comprobante de egreso (nuevo, junto a generarVale()) sobre el fondo completo tamaño carta public/images/reportes/fondo-comprobante-egreso.png (mismo enfoque: el fondo trae impresas todas las cajas/líneas/íconos, el método sólo ubica texto). Datos que no vienen directo de CargaCombustible: Área/Encargado de área se resuelven vía $carga->vehiculo->areasAsignadas()->first() (Area) y $area->encargadosActivos()->first() (Persona) — ambos nullable, con fallback 'N/A'; "Automóvil" combina vehiculo->tipoVehiculo->tipo_vehiculo + nro_placa; "A utilizarse en" es carga->concepto (ver [[carga-combustible-nro-carga-gestion]] si existe, o requests-migrations.md). CargaCombustible sólo admite un tipo de combustible por registro, así que la tabla del comprobante siempre tiene una única fila — no hay lógica de múltiples conceptos.

CargaCombustibleController::imprimirComprobante() usa el patrón de CargasCombustibleReportController (generar con modo 'S' y devolver response($contenido, 200, ['Content-Type'=>'application/pdf', ...])), NO el patrón exit; de ValeController::imprimirVale()/generarVale() en modo 'I'. Ese segundo patrón corta el proceso PHP con exit y es intestable vía HTTP feature test (por eso vales.imprimir no tiene test) — para cualquier nuevo endpoint de impresión, replicar el patrón 'S' + response(), no el de exit.

Al posicionar texto sobre un fondo PNG nuevo sin líneas propias dibujadas en código: no asumir coordenadas por inspección visual del PNG a simple vista — se prestan a error (ver overlaps de un primer intento en esta tarea). Renderizar el fondo solo con FPDF a 215.9x279.4mm, convertir a PNG con PyMuPDF (`pip install pymupdf`, no había Ghostscript/pdftoppm instalados) y medir posiciones de etiquetas/líneas con Pillow (umbral de color) sobre ESE render, no sobre el PNG origen (evita distorsión de aspect-ratio). Cuidado con watermarks/íconos semitransparentes: pueden colar falsos positivos en una detección de "azul" con umbral laxo; usar un umbral más estricto (r bajo, b alto) o inspeccionar recortes visualmente cuando la detección dé resultados inconsistentes.

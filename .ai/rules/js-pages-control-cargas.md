---
paths:
  - 'app/Http/Controllers/CargaMaterialController.php,app/Libraries/Reportes.php,resources/js/Pages/ControlCargas/*.vue'
---

# Js Pages Control Cargas

## Informe individual de flete: CargaMaterialController::generarPDF() + Reportes::generarReporteFlete()
Ruta GET control-cargas/{cargaMaterial}/imprimir (control-cargas.imprimir) -> CargaMaterialController::generarPDF(): carga vehiculoExterno/usuarioApertura/usuarioCierre + viajes (con material/usuarioRegistro), y llama a (new Reportes)->generarReporteFlete($cargaMaterial, $viajes, 'S') devolviendo response($pdf, 200, ['Content-Type'=>'application/pdf', 'Content-Disposition'=>'inline; filename=...']) — mismo patrón que OperacionDiariaController::generarPDF(), no el patrón viejo de ValeController::imprimirVale() (exit; sin response()).

Reportes::generarReporteFlete($carga, $viajes, $modo, $nombreArchivo) es un reporte FPDF "de código" (sin imagen de fondo tipo generarVale/generarComprobanteEgreso): reutiliza pintarInfoEmpresa()/pintarPieDePagina()/logoEmpresa() y easyTable, mismo estilo que generarReporteControlCargasDetalle() (encabezado azul/verde + grilla de 2 columnas para datos generales + tabla de viajes). Gate en el frontend con v-can="'control-cargas.ver'" (no existe permiso 'control-cargas.imprimir' propio — ver config/acl.php); botón en Index.vue (icono ri-printer-line junto a Editar/Ver en cada card) y en Show.vue (botón "Imprimir informe" en la cabecera), ambos con target="_blank".

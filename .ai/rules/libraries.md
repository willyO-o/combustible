---
paths:
  - 'app/Libraries/*.php'
  - app/Libraries/Reportes.php
---

# Libraries

## app/Libraries wraps 3rd-party classes for document generation
Use app/Libraries for classes that extend a third-party base class (e.g. FPDF) to produce documents/reports. Instantiate them directly with new in the calling controller rather than via DI. Keep pure formatting helpers in app/Helpers instead.

## Sello "ANULADO" sobre el PDF del vale (generarVale)
Si $vale->estado_vale === 'ANULADO', generarVale() dibuja public/images/reportes/anulado.png centrado sobre toda la página, al final del método (justo antes de Output()), para quedar por encima del resto del contenido. Ese PNG ya trae el sello rotado en diagonal con fondo transparente (canal alpha); FPDF soporta PNG con alpha de forma nativa (SMask), no hace falta ninguna librería extra. El ancho se fija en 130mm y el alto se calcula a partir del aspect ratio real del PNG (getimagesize), no hardcodeado, para no romper si se reemplaza la imagen. Ningún test cubre esto (imprimirVale usa exit(), ver libraries-http-controllers.md); se verificó visualmente renderizando el PDF a PNG con PyMuPDF.

## PDF bitácora operación diaria: sin fondos sólidos (ahorro de tinta)
`generarReporteOperacionDiariaDetalle()` NO usa rellenos sólidos de color como fondo (pedido del usuario, ahorro de tinta): la fila de grupos, la de encabezados de columna y la de TOTALES usan `bgcolor:238,240,246` (tinte casi blanco) + texto en `$azul` negrita, NO `bgcolor:$azul` con texto blanco. El encabezado del recuadro RESUMEN va con `Rect(...,'D')` (sólo borde), no `'FD'` amarillo. El filete de acento superior es de 0.5 mm (antes 1.2). Si se toca esta función, mantener este criterio; el resto de PDFs de `Reportes.php` (uso, rendimiento, control de cargas) todavía usan barras/filas sólidas — replicar sólo si el usuario lo pide.

## PDF bitácora operación diaria: sin fondos sólidos (ahorro de tinta)
ACTUALIZADO: `generarReporteOperacionDiariaDetalle()` usa fondos sólidos pero CLAROS (poca tinta), no oscuros: fila de grupos `bgcolor:205,227,245` y encabezados de columna `bgcolor:224,238,250` (celeste claro); fila de TOTALES `bgcolor:255,240,191` y encabezado del recuadro RESUMEN `SetFillColor(255,240,191)` + `Rect(...,'FD')` (amarillo claro). Texto siempre en `$azul` negrita. Nada de `bgcolor:$azul`/blanco ni amarillo saturado (255,214,79). Filete de acento superior 0.5 mm. Resto de PDFs de `Reportes.php` sin cambiar.

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

---
paths:
  - 'public/fonts/fpdf-calibri/**'
---

# Fpdf Calibri

## Fluent_Calibri*.ttf tienen métricas rotas — font/ regenerado desde la Calibri estática de Windows
Causa de "mucha separación entre letras" en el reporte con Calibri ([[Fuente Calibri en FPDF: registrarFuenteCalibri() + textoCalibri()]]): los 4 `public/fonts/fpdf-calibri/makefont/Fluent_Calibri*.ttf` (Microsoft Fluent Fonts) tienen anchos de glifo/avance MUY inflados al parsearlos con `ttfparser.php` (probablemente son fuentes variables, que ese parser clásico no soporta correctamente) — el espacio salía en ~678/1000 en vez de ~226, y letras normales 15-70% más anchas de lo real. Confirmado comparando con la Calibri estática de Windows (C:\Windows\Fonts\calibri.ttf) vía el mismo ttfparser: esa da espacio=226, "A"=579, "e"=498 (valores sanos).

Fix aplicado: se regeneraron los 8 archivos de `public/fonts/fpdf-calibri/font/*.php+*.z` corriendo `MakeFont()` (mismo makefont.php, enc=cp1252/embed=true/subset=true) contra la Calibri estática real (regular/bold/italic/bold-italic de C:\Windows\Fonts\), NO contra los .ttf "Fluent" del repo. El .ttf fuente de Windows NO se copió/comprometió al repo (licencia de Microsoft no permite redistribuir el archivo completo; sólo se usó como entrada puntual para generar el subset embebido en los .z, igual que hace Word al exportar a PDF — eso sí está permitido). `registrarFuenteCalibri()` en Reportes.php no cambió: sigue apuntando a los mismos 8 nombres de archivo en font/, que ahora tienen contenido correcto.

IMPORTANTE: NO ejecutar `generar_fuentes.php` (usa los Fluent_Calibri*.ttf rotos) — sobreescribiría font/ con las métricas malas otra vez. Ver el aviso agregado en public/fonts/fpdf-calibri/README.md. Si se necesita regenerar en otra máquina, usar una Calibri estática con métricas sanas (Windows/Office la trae en Fonts\calibri.ttf), nunca los .ttf "Fluent" de este repo tal cual están.

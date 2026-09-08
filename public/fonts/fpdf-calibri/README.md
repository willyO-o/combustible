# Calibri para FPDF (PHP)

Archivos de definición de fuente **Calibri** listos para usar con **FPDF** (versión
clásica, no Unicode), generados a partir de las *Microsoft Fluent Fonts* que
contienen la métrica y los glifos de Calibri.

> ⚠️ **`font/` actualmente NO viene de los `.ttf` de `makefont/` (2026-09).**
> Los 4 `Fluent_Calibri*.ttf` de este repo tienen métricas de ancho de glifo
> rotas (probablemente por ser fuentes variables que `ttfparser.php` —no es
> compatible con fuentes variables— no lee bien): el ancho del espacio salía
> en ~678/1000 (debería ser ~226) y cada letra 15-70% más ancha de lo real,
> lo que se veía como texto con demasiada separación entre letras/palabras
> en los PDF generados. Los 8 archivos de `font/` se regeneraron a mano
> ejecutando `MakeFont()` (mismo `makefont.php`/`enc=cp1252, embed=true,
> subset=true` de siempre) contra la Calibri **estática** normal de Windows
> (`C:\Windows\Fonts\calibri.ttf` y sus variantes bold/italic/bold-italic —
> NO redistribuida en este repo por licencia, sólo se usó como entrada
> puntual para generar el subset embebido, igual que hace Word al exportar
> a PDF). Confirmado con `ttfparser.php`: esa Calibri da espacio=226,
> "A"=579, "e"=498, valores normales.
>
> **No ejecutes `generar_fuentes.php` para "regenerar" `font/`** — reescribiría
> estos archivos correctos con la versión rota de las Fluent Fonts. Si en
> algún momento se corrige/reemplaza `Fluent_Calibri.ttf` por una versión
> estática con métricas sanas, recién ahí tiene sentido volver a usarlo.

```
fpdf-calibri/
├── font/                     ← ESTO es lo que copias a tu proyecto
│   ├── calibri.php   calibri.z     (Regular)
│   ├── calibrib.php  calibrib.z    (Bold)
│   ├── calibrii.php  calibrii.z    (Italic)
│   └── calibribi.php calibribi.z   (Bold Italic)
│
├── generar_fuentes.php       ← convertidor (regenera font/ desde los .ttf)
├── makefont/                 ← utilidad MakeFont de FPDF + los .ttf de origen
│   ├── makefont.php  ttfparser.php  cp1252.map
│   └── Fluent_Calibri*.ttf
├── ejemplo/
│   ├── ejemplo_fpdf.php      ← ejemplo funcional
│   ├── prueba_espanol.php    ← prueba de cobertura de caracteres en español
│   ├── fpdf/fpdf.php         ← copia de FPDF 1.86 solo para el ejemplo
│   └── preview.pdf           ← PDF de muestra ya generado
└── Microsoft Fluent Fonts EULA.rtf
```

- **Codificación:** `cp1252` (Windows-1252 / Latin-1). Cubre español completo
  (`á é í ó ú Á É Í Ó Ú ñ Ñ ü Ü ¿ ¡`), `€`, `º ª ç`, comillas tipográficas
  (`« » “ ” ‘ ’`), guiones largos (`– —`), puntos suspensivos, viñetas…
- **Cobertura verificada:** MakeFont no reportó *ni un solo* carácter de cp1252
  ausente en los 4 `.ttf`, y el texto de `ejemplo/preview.pdf` se extrae correcto
  con `pdftotext`. Puedes reconfirmarlo con `ejemplo/prueba_espanol.php`.
- **Incrustada y *subset*:** la fuente viaja dentro del PDF, solo con los glifos
  de cp1252 → PDFs ligeros y que se ven igual en cualquier equipo.

---

## Implementación en el otro proyecto

### 1. Copiar los archivos

Copia **los 8 archivos** de `font/` a la carpeta de fuentes de FPDF de tu
proyecto. Normalmente es la carpeta `font/` que viene con FPDF. Deben ir juntos
el `.php` **y** su `.z`.

### 2. Indicar a FPDF dónde está esa carpeta (si no es la de por defecto)

```php
define('FPDF_FONTPATH', __DIR__ . '/ruta/a/font/');   // con la barra final
require 'ruta/a/fpdf.php';
```

Si usas Composer (`setasign/fpdf`), el paquete ya define su carpeta `font/`;
copia ahí los archivos o usa `FPDF_FONTPATH` como arriba.

### 3. Registrar la familia y usarla

```php
$pdf = new FPDF();

$pdf->AddFont('Calibri', '',   'calibri.php');
$pdf->AddFont('Calibri', 'B',  'calibrib.php');
$pdf->AddFont('Calibri', 'I',  'calibrii.php');
$pdf->AddFont('Calibri', 'BI', 'calibribi.php');

$pdf->AddPage();
$pdf->SetFont('Calibri', '', 12);          // normal
$pdf->SetFont('Calibri', 'B', 14);         // negrita
$pdf->SetFont('Calibri', 'BI', 12);        // negrita + cursiva
$pdf->Cell(0, 8, 'Hola mundo', 0, 1);
```

El primer argumento de `AddFont`/`SetFont` (`'Calibri'`) es el nombre de familia:
puedes poner el que quieras, pero debe coincidir en las 4 llamadas.

### 4. Texto con acentos (¡importante!)

FPDF clásico **no** trabaja en UTF-8, espera **cp1252**. Si tus archivos PHP
están en UTF-8 (lo normal hoy), convierte cada cadena al imprimir:

```php
function t(string $s): string {
    return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
}

$pdf->Cell(0, 8, t('Facturación número 3 — €50'), 0, 1);
```

(equivalente a la antigua `utf8_decode()`, retirada en PHP 9).

Si tu proyecto necesita caracteres fuera de Latin-1 (griego, cirílico, chino…),
entonces necesitas **tFPDF** o **FPDF Unicode**, que usan el `.ttf` directamente
(los tienes en `makefont/`) y no estos archivos.

---

## Regenerar los archivos (convertidor)

Ya están generados en `font/`. Solo necesitas esto si cambias los `.ttf` o la
codificación.

**Por navegador** (Apache + PHP 8.3):

```
http://localhost/fontcalibri/fpdf-calibri/generar_fuentes.php
```

**Por consola:**

```bash
php generar_fuentes.php
```

Requiere la extensión **zlib** (para comprimir el `.z`) — activa por defecto en
Laragon. El script llama a `MakeFont()` de FPDF con
`enc=cp1252, embed=true, subset=true` y renombra la salida al convenio de FPDF
(`calibri`, `calibrib`, `calibrii`, `calibribi`).

> Nota: `makefont/makefont.php` es la utilidad oficial de FPDF con **un cambio de
> una línea**: el bloque CLI del final está protegido con
> `!defined('MAKEFONT_AS_LIBRARY')` para poder incluirlo desde el convertidor sin
> que se autoejecute.

---

## Probar

```
http://localhost/fontcalibri/fpdf-calibri/ejemplo/ejemplo_fpdf.php
```

Debe mostrar un PDF con Calibri en sus 4 variantes y todos los acentos y signos
españoles correctos. Muestra ya generada: `ejemplo/preview.pdf`.

---

## Licencia

Las fuentes son las *Microsoft Fluent Fonts*; su uso está regido por el archivo
`Microsoft Fluent Fonts EULA.rtf`. Revísalo antes de distribuir PDFs con la
fuente incrustada. FPDF tiene licencia permisiva (`ejemplo/fpdf/license.txt`).

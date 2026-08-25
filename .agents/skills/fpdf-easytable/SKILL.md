---
name: fpdf-easytable
description: Genera tablas (filas/columnas, encabezado repetido en cada página, salto de página automático, colspan/rowspan, bordes y colores) dentro de reportes PDF de este proyecto, usando la librería complementaria fpdf-easytable (clases exFPDF/easyTable) en vez de dibujar celdas a mano con Rect()/Cell(). Usar al crear o modificar un reporte PDF (app/Libraries/Reportes.php u otra clase basada en FPDF) que necesite una tabla de datos, o cuando el usuario pida explícitamente usar EasyTable/fpdf-easytable.
---

# FPDF EasyTable

Complemento de `setasign/fpdf` (ya usado en este proyecto vía `App\Libraries\Reportes`) que arma
tablas — filas, columnas, colspan/rowspan, bordes, colores, salto de página automático con
encabezado repetido — sin tener que calcular y dibujar cada celda a mano con `Rect()`/`Cell()`
(que es como está hecho hoy `generarReporteCargasCombustible()`, `generarReporteRendimiento()`,
etc. en `Reportes.php`).

## Cuándo usar esta skill

- Al crear un reporte PDF nuevo que incluya una tabla de datos (filas variables, totales,
  encabezado que se repita si la tabla salta de página).
- Al modificar un reporte existente en `app/Libraries/Reportes.php` (u otra clase FPDF) que ya
  dibuja una tabla celda por celda y conviene simplificar.
- Cuando el usuario lo pida explícitamente ("usa easytable", "usa la librería de tablas").

No hace falta para elementos que no son tablas (encabezados, tarjetas de resumen, firmas, QR,
etc.) — eso se sigue dibujando con las primitivas normales de FPDF (`Cell`, `Rect`, `Image`,
`MultiCell`), igual que el resto de `Reportes.php`.

## Instalación en este proyecto

Ya está instalada. El repositorio oficial (`github.com/fpdf-easytable/fpdf-easytable`) no publica
un `composer.json` ni un paquete en Packagist — sólo distribuye los `.php` sueltos — así que se
declaró como un repositorio Composer de tipo `package` directamente en `composer.json`, apuntando
al repo real (no a un fork de terceros):

```json
"repositories": [
    {
        "type": "package",
        "package": {
            "name": "fpdf-easytable/fpdf-easytable",
            "version": "dev-master",
            "type": "library",
            "source": {
                "url": "https://github.com/fpdf-easytable/fpdf-easytable.git",
                "type": "git",
                "reference": "master"
            },
            "autoload": {
                "classmap": ["easyTable.php", "exfpdf.php"]
            }
        }
    }
]
```
con `"fpdf-easytable/fpdf-easytable": "dev-master"` en `require`. `composer update
fpdf-easytable/fpdf-easytable` trae los últimos cambios de la rama `master` del repo real.

Las clases que expone son **globales** (sin namespace): `exFPDF` y `easyTable`. Dentro de un
archivo con `namespace App\...` hace falta declararlas igual que ya se hace con `FPDF` en
`Reportes.php`:

```php
use exFPDF;
use easyTable;
```

## Requisito importante: exFPDF, no FPDF plano

`easyTable` necesita métodos que sólo existen en `exFPDF` (`PageBreak()`, manejo de fuentes/
colores, etc.), no en el `FPDF` base de `setasign/fpdf`. **`App\Libraries\Reportes` hoy extiende
`FPDF` directamente** (`class Reportes extends FPDF`), así que para usar `easyTable` dentro de un
método de esa clase hay que cambiar esa línea a `class Reportes extends exFPDF` (es un cambio
seguro: `exFPDF` extiende `FPDF` y sólo agrega métodos nuevos, no sobreescribe los que ya usa el
resto de la clase) **antes** de la primera vez que se use `easyTable` ahí. Si el reporte se genera
en una clase nueva, extenderla de `exFPDF` desde el inicio.

## API básica

```php
// Columnas iguales: número de columnas.
$table = new easyTable($pdf, 3, 'border:1; width:190');

// Anchos explícitos (en unidades del documento, deben sumar <= al width de la tabla).
$table = new easyTable($pdf, '{35, 45, 55}', 'width:135; border:1');

// Anchos por porcentaje (deben sumar <= 100).
$table = new easyTable($pdf, '%{35, 45, 55}', 'width:190; border:1');

$table->rowStyle('bgcolor:#e8e8e8; font-style:B');  // aplica a la próxima fila
$table->easyCell('Encabezado 1');
$table->easyCell('Encabezado 2');
$table->printRow(true);   // true = fila de encabezado: se repite en cada salto de página

$table->rowStyle('');     // limpiar estilo de fila antes de la fila de datos
$table->easyCell('Valor 1');
$table->easyCell('Valor 2', 'align:R');
$table->printRow();

$table->endTable();       // cierra la tabla y deja $pdf listo para seguir dibujando debajo
```

- `easyCell($texto, $estilo = '')` agrega una celda a la fila en construcción (se llama una vez
  por columna antes de `printRow()`).
- `printRow($esEncabezado = false)` imprime la fila acumulada; `true` la marca como encabezado
  (se reimprime automáticamente si la tabla salta de página).
- `endTable($margenInferior = 2)` cierra la tabla; después de esto `$pdf->GetY()` queda justo
  debajo de la tabla.

### Estilos (string `propiedad:valor; propiedad:valor`)

Se puede pasar en el constructor (nivel tabla), en `rowStyle()` (nivel fila) o en `easyCell()`
(nivel celda) — celda gana sobre fila, fila gana sobre tabla.

| Propiedad | Valores | Ejemplo |
| --- | --- | --- |
| `width` | unidades o `%` | `width:190;` / `width:70%;` |
| `border` | `0`, `1`, o combinación `L`/`T`/`R`/`B` | `border:1;` / `border:LTR;` |
| `border-color` | hex o RGB | `border-color:#333333;` / `border-color:79,140,200;` |
| `border-width` | numérico | `border-width:0.3;` |
| `bgcolor` | hex o RGB | `bgcolor:#e8e8e8;` |
| `font-family` | nombre de fuente FPDF | `font-family:Arial;` |
| `font-style` | combinación de `B`/`I`/`U` | `font-style:B;` |
| `font-size` | puntos | `font-size:9;` |
| `font-color` | hex o RGB | `font-color:#1e1e1e;` |
| `align` | `L`/`C`/`R`/`J` | `align:R;` |
| `valign` | `T`/`M`/`B` | `valign:M;` |
| `paddingX`, `paddingY` | unidades | `paddingX:2; paddingY:1;` |
| `colspan` | entero | `colspan:2;` (en `easyCell`) |
| `rowspan` | entero | `rowspan:2;` (en `easyCell`) |
| `img` | `archivo,wANCHO,hALTO` | `img:logo.png,w20,h10;` (en `easyCell`) |
| `line-height` | numérico | `line-height:1.2;` |
| `min-height` | unidades | `min-height:8;` (en `rowStyle`) |
| `split-row` | `true`/`false` (nivel tabla) | `split-row:false;` — por defecto una fila que no entra completa pasa entera a la próxima página; `true` la corta entre páginas. Una fila con `rowspan` siempre se corta, ignorando esta opción. |

## Ejemplo integrado al proyecto

Siguiendo las convenciones ya usadas en `Reportes.php` (fuentes core de FPDF en Latin-1 ⇒ pasar
todo texto por `utf8Decode()`; parámetro `$modo` para `Output()`: `'I'` inline, `'D'` descarga,
`'S'` string para la API):

```php
namespace App\Libraries;

use exFPDF;

class ReporteConTabla extends exFPDF
{
    public function generar($filas, string $modo = 'I', ?string $nombreArchivo = null)
    {
        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(true, 15); // necesario para que la tabla salte de página sola

        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, utf8Decode('REPORTE'), 0, 1, 'C');
        $this->Ln(4);

        $tabla = new \easyTable($this, '{40, 90, 30, 30}', 'width:190; border:1');

        $tabla->rowStyle('bgcolor:#273a5e; font-style:B; font-color:#ffffff');
        foreach (['Código', 'Descripción', 'Cantidad', 'Total'] as $encabezado) {
            $tabla->easyCell(utf8Decode($encabezado));
        }
        $tabla->printRow(true);

        foreach ($filas as $fila) {
            $tabla->rowStyle('');
            $tabla->easyCell(utf8Decode($fila['codigo']));
            $tabla->easyCell(utf8Decode($fila['descripcion']));
            $tabla->easyCell((string) $fila['cantidad'], 'align:C');
            $tabla->easyCell(number_format($fila['total'], 2, ',', '.'), 'align:R');
            $tabla->printRow();
        }

        $tabla->endTable();

        return $this->Output($modo, $nombreArchivo ?? 'reporte.pdf');
    }
}
```

## Notas y errores comunes

- Crear la tabla (`new easyTable(...)`) **después** de `$this->AddPage()` y `$this->SetFont()`
  iniciales — necesita un documento con página activa.
- `SetAutoPageBreak(true, ...)` debe estar activo (como en los reportes largos existentes, p. ej.
  `generarReporteCargasCombustible()`) para que la tabla realmente salte de página sola; con
  `SetAutoPageBreak(false)` (como en `generarVale()`, documentos de una sola página) no hace falta
  y no debe activarse.
- No mezclar `Cell()`/`Rect()` manuales para dibujar la MISMA tabla que ya se está armando con
  `easyTable` — o se usa `easyTable` para la tabla completa, o se dibuja a mano, no ambas a la vez.
- Igual que el resto de `Reportes.php`: todo texto va por `utf8Decode()` (fuentes core de FPDF,
  sin soporte UTF-8 nativo) salvo que se registre una fuente TTF con `AddFont()`.
- Error "Some data has already been output, can't send PDF file": algo se imprimió (echo, espacio
  en blanco, error PHP) antes de que el controlador llame a `Output()` — no es un problema de
  EasyTable en sí.

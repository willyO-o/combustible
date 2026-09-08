<?php

/**
 * Ejemplo mínimo: usar Calibri con FPDF clásico (no Unicode).
 *
 *   http://localhost/fontcalibri/fpdf-calibri/ejemplo/ejemplo_fpdf.php
 *
 * FPDF clásico trabaja en codificación cp1252 (Windows-1252 / Latin-1).
 * Si tu código fuente está en UTF-8, convierte cada cadena antes de imprimirla
 * con la función t() de abajo.
 */

require __DIR__.'/fpdf/fpdf.php';

// Le decimos a FPDF dónde están los archivos de definición de fuente (.php + .z)
define('FPDF_FONTPATH', dirname(__DIR__).'/font/');

/** UTF-8  ->  cp1252 (lo que espera FPDF clásico) */
function t(string $s): string
{
    return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
}

$pdf = new FPDF;

// Registrar las 4 variantes bajo la misma familia "Calibri"
$pdf->AddFont('Calibri', '', 'calibri.php');
$pdf->AddFont('Calibri', 'B', 'calibrib.php');
$pdf->AddFont('Calibri', 'I', 'calibrii.php');
$pdf->AddFont('Calibri', 'BI', 'calibribi.php');

$pdf->AddPage();

$pdf->SetFont('Calibri', '', 22);
$pdf->Cell(0, 12, t('Calibri en FPDF'), 0, 1);

$pdf->SetFont('Calibri', '', 12);
$pdf->MultiCell(0, 7, t(
    "Texto normal con acentos y signos españoles: áéíóú ñ Ñ ¿ ¡ ü — «citas» “comillas” • € 2026.\n"
));

$pdf->SetFont('Calibri', 'B', 12);
$pdf->Cell(0, 8, t('Negrita — ÁÉÍÓÚ'), 0, 1);

$pdf->SetFont('Calibri', 'I', 12);
$pdf->Cell(0, 8, t('Cursiva — abcdefghijk'), 0, 1);

$pdf->SetFont('Calibri', 'BI', 12);
$pdf->Cell(0, 8, t('Negrita cursiva'), 0, 1);

// 'I' = mostrar en el navegador. Usa 'D' para descargar o 'F' para guardar en disco.
$pdf->Output('I', 'ejemplo-calibri.pdf');

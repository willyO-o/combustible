<?php

/**
 * Prueba de cobertura de caracteres en español para las 4 variantes de Calibri.
 *   http://localhost/fontcalibri/fpdf-calibri/ejemplo/prueba_espanol.php
 */
require __DIR__.'/fpdf/fpdf.php';
define('FPDF_FONTPATH', dirname(__DIR__).'/font/');
function t($s)
{
    return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
}

$pdf = new FPDF;
$pdf->AddFont('Calibri', '', 'calibri.php');
$pdf->AddFont('Calibri', 'B', 'calibrib.php');
$pdf->AddFont('Calibri', 'I', 'calibrii.php');
$pdf->AddFont('Calibri', 'BI', 'calibribi.php');
$pdf->AddPage();

$pdf->SetFont('Calibri', 'B', 18);
$pdf->Cell(0, 12, t('Calibri para FPDF — cobertura de español'), 0, 1);
$pdf->Ln(2);

$bloques = [
    'Minúsculas acentuadas' => 'á é í ó ú',
    'Mayúsculas acentuadas' => 'Á É Í Ó Ú',
    'Eñe y diéresis' => 'ñ Ñ ü Ü',
    'Signos de apertura' => '¿ ¡',
    'Comillas y guiones' => '« » “ ” ‘ ’ – — …',
    'Símbolos' => '€ $ % º ª & @ · • ç',
    'Frase completa' => 'El veloz murciélago hindú comía feliz cardillo y kiwi. ¿Añañí? ¡Sí!',
];

foreach (['', 'B', 'I', 'BI'] as $st) {
    $et = ['' => 'Regular', 'B' => 'Negrita', 'I' => 'Cursiva', 'BI' => 'Negrita cursiva'][$st];
    $pdf->SetFont('Calibri', 'B', 13);
    $pdf->Ln(3);
    $pdf->Cell(0, 8, t("— $et —"), 0, 1);
    foreach ($bloques as $titulo => $txt) {
        $pdf->SetFont('Calibri', '', 9);
        $pdf->Cell(48, 7, t($titulo), 0, 0);
        $pdf->SetFont('Calibri', $st, 12);
        $pdf->Cell(0, 7, t($txt), 0, 1);
    }
}

$pdf->Output('I','prueba-espanol.pdf');

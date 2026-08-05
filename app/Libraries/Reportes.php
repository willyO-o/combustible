<?php

namespace App\Libraries;

use FPDF;


class Reportes extends FPDF
{

    public function generarVale($vale)
    {
        // logo======
        // DATOS DE EJEMPLO (reemplazar por variables dinámicas luego)
        // ==========================================================
        $empresaLocal   = "PLUS METALS LTDA.";
        $numeroVale     = $vale->nro; // Número de vale (formateado con ceros a la izquierda)
        $empresa        = $vale->grifo->razon_social;
        $direccion      = $vale->grifo->direccion;
        $telefono       = "Tel. " . $vale->grifo->telefono;
        $ciudad         = $vale->grifo->ciudad;

        $cliente        = $vale->conductor->persona->nombre_completo;
        $licencia       = $vale->conductor->persona->ci;
        $vehiculo       = $vale->vehiculo->codigo;
        $marca          = $vale->vehiculo->marca;
        $placa          = $vale->vehiculo->nro_placa;

        $litros         = $vale->litros;
        $bolivianos     = $vale->precio;
        // $tipoGasolina   = true;   // marca el checkbox "Gasolina"
        $tipoDiesel     = false;  // marca el checkbox "Diesel"
        $autorizadoPor  = $vale->user?->name;
        // $facturaNro     = '000452';
        $tipoCombustible = $vale->tipoCombustible->tipo_combustible;;

        // Colores base (verde institucional / rojo para el correlativo)
        // $verde  = [20, 110, 60];
        $verde  = [24, 125, 170];
        $rojo   = [190, 30, 30];
        $negro  = [30, 30, 30];
        $gris   = [90, 90, 90];

        $this->AddPage('L', [219, 140]);
        $this->SetMargins(5, 5, 5);
        $this->SetAutoPageBreak(false);

        $pageW = 219;

        // ----------------------------------------------------------
        // ENCABEZADO
        // ----------------------------------------------------------
        // Título "VALE"
        $this->Image(public_path('images/logo/logo-min.png'), 6, 5.5, 18);

        $this->SetXY(23, 5);
        $this->SetFont('Arial', 'BI', 34);
        $this->SetTextColor($verde[0], $verde[1], $verde[2]);
        $this->Cell(40, 16, utf8Decode('VALE'), 0, 0, 'L');

        $this->Image(public_path('images/logo/gas.jpg'), 198.5, 5.5, 15);

        // Recuadro rojo con el número de vale (arriba a la derecha)
        // $this->SetDrawColor($rojo[0], $rojo[1], $rojo[2]);
        // $this->SetLineWidth(0.4);
        // $this->Rect(40, 5, 16, 17);
        $this->SetXY(23, 19);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($rojo[0], $rojo[1], $rojo[2]);
        $this->Cell(27, 4, utf8Decode('N°' . $numeroVale), 0, 2, 'C');
        // $this->SetFont('Arial', 'B', 12);
        // $this->Cell(27, 6, $numeroVale, 0, 0, 'C');
        // Datos de la empresa (alineados a la derecha, arriba)

        $this->SetXY(123, 6);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->Cell(74, 4, utf8Decode($empresa), 0, 2, 'R');
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->Cell(74, 3.5, utf8Decode($direccion), 0, 2, 'R');
        $this->Cell(74, 3.5, utf8Decode($telefono), 0, 2, 'R');
        $this->Cell(74, 3.5, utf8Decode($ciudad), 0, 2, 'R');



        // Barra verde separadora con el logotipo "SOCINBOL"
        $this->SetFillColor($verde[0], $verde[1], $verde[2]);
        $this->Rect(5, 27, $pageW - 10, 6, 'F');
        $this->SetXY(5, 27);
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($pageW - 12, 6, utf8Decode($empresaLocal), 0, 0, 'R');

        // ----------------------------------------------------------
        // CUERPO: 2 cuadros (Cliente / Combustible)
        // ----------------------------------------------------------
        $bodyY = 34;
        $bodyH = 100;
        $leftX = 5;
        $leftW = 108;
        $rightX = 116;
        $rightW = 98;

        $this->SetDrawColor($verde[0], $verde[1], $verde[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($leftX, $bodyY, $leftW, $bodyH);   // cuadro izquierdo
        $this->Rect($rightX, $bodyY, $rightW, $bodyH); // cuadro derecho

        // --- Cuadro izquierdo: datos del cliente ---
        $this->SetXY($leftX + 4, $bodyY + 3);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($verde[0], $verde[1], $verde[2]);
        $this->Cell($leftW - 8, 6, utf8Decode('CLIENTE'), 'B', 1, 'L');

        $filas = [
            ['Nombre:', $cliente],
            ['Licencia:', $licencia],
            ['Vehículo:', $vehiculo],
            ['Marca:', $marca],
            ['Placa:', $placa],
        ];

        $y = $bodyY + 14;
        foreach ($filas as $fila) {
            [$etiqueta, $valor] = $fila;

            $this->SetXY($leftX + 4, $y);
            $this->SetFont('Arial', 'B', 9);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->Cell(24, 6, utf8Decode($etiqueta), 0, 0, 'L');

            $this->SetFont('Arial', '', 9);
            $this->Cell($leftW - 4 - 24 - 4, 6, utf8Decode($valor), 'B', 0, 'L');

            $y += 14.5;
        }

        // --- Cuadro derecho: litros / importe ---
        $rx = $rightX + 4;
        $rw = $rightW - 8;

        // Tabla Lt. / Bs.
        $tblY = $bodyY + 3;
        $tblH = 22;
        $colW = $rw / 2;

        $this->SetDrawColor($verde[0], $verde[1], $verde[2]);
        $this->Rect($rx, $tblY, $rw, $tblH);
        $this->Line($rx + $colW, $tblY, $rx + $colW, $tblY + $tblH);
        $this->Line($rx, $tblY + 6, $rx + $rw, $tblY + 6);

        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($verde[0], $verde[1], $verde[2]);
        $this->SetXY($rx, $tblY);
        $this->Cell($colW, 6, 'Lt.', 0, 0, 'C');
        $this->SetXY($rx + $colW, $tblY);
        $this->Cell($colW, 6, 'Bs.', 0, 0, 'C');

        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($rx, $tblY + 6);
        $this->Cell($colW, 16, $litros, 0, 0, 'C');
        $this->SetXY($rx + $colW, $tblY + 6);
        $this->Cell($colW, 16, $bolivianos, 0, 0, 'C');

        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($rx, $tblY + $tblH + 1);
        $this->Cell($colW, 3, 'Litros', 0, 0, 'C');
        $this->SetXY($rx + $colW, $tblY + $tblH + 1);
        $this->Cell($colW, 3, 'Bolivianos', 0, 0, 'C');

        $y2 = $tblY + $tblH + 10;
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($rx, $y2);
        // $this->Cell(30, 6, utf8Decode('Tipo Combustible: '), 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell($rw, 6, utf8Decode(numeroLiteral($litros, 'LITROS')), 'B', 0, 'C');

        $y2 += 10;
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($rx, $y2);
        // $this->Cell(30, 6, utf8Decode('Tipo Combustible: '), 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell($rw , 6, utf8Decode(monedaLiteral($bolivianos)), 'B', 0, 'C');

        $y2 += 10;
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($rx, $y2);
        $this->Cell(30, 6, utf8Decode('Tipo Combustible: '), 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell($rw - 28, 6, utf8Decode($tipoCombustible), 'B', 0, 'L');

        // Checkboxes Gasolina / Diesel
        // $chkY = $tblY + $tblH + 8;
        // $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
        // $this->SetLineWidth(0.25);

        // $this->Rect($rx, $chkY, 4, 4);
        // if ($tipoGasolina) {
        //     $this->SetFont('Arial', 'B', 9);
        //     $this->SetXY($rx, $chkY - 0.5);
        //     $this->Cell(4, 5, 'X', 0, 0, 'C');
        // }

        // $this->SetFont('Arial', '', 9);
        // $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        // $this->SetXY($rx, $chkY - 0.8);
        // $this->Cell(30, 5, utf8Decode('Tipo combustible: ' . $tipoCombustible), 0, 0, 'L');



        // $this->Rect($rx + 40, $chkY, 4, 4);
        // if ($tipoDiesel) {
        //     $this->SetFont('Arial', 'B', 9);
        //     $this->SetXY($rx + 40, $chkY - 0.5);
        //     $this->Cell(4, 5, 'X', 0, 0, 'C');
        // }
        // $this->SetFont('Arial', '', 9);
        // $this->SetXY($rx + 46, $chkY - 0.8);
        // $this->Cell(30, 5, 'Diesel', 0, 0, 'L');

        // Autorizado por / Factura N°
        // $y2 = $chkY + 10;
        $y2 += 10;
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($rx, $y2);
        $this->Cell(28, 6, utf8Decode('Autorizado por:'), 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell($rw - 28, 6, utf8Decode($autorizadoPor), 'B', 0, 'L');

        $y2 += 14;
        // $this->SetFont('Arial', 'B', 9);
        // $this->SetXY($rx, $y2);
        // $this->Cell(24, 6, utf8Decode('Factura N°:'), 0, 0, 'L');
        // $this->SetFont('Arial', '', 9);
        // $this->Cell($rw - 24, 6, $facturaNro, 'B', 0, 'L');

        // Mini "código de barras" decorativo + marca, esquina inferior derecha
        $barX = $rightX + $rightW - 26;
        $barY = $bodyY + $bodyH - 16;
        $this->SetFillColor(0, 0, 0);
        for ($i = 0; $i < 22; $i++) {
            $w = ($i % 3 === 0) ? 1.1 : 0.5;
            $this->Rect($barX + ($i * 1.1), $barY, $w, 10, 'F');
        }
        $this->SetFont('Arial', 'B', 7);
        $this->SetTextColor($verde[0], $verde[1], $verde[2]);
        $this->SetXY($barX, $barY + 11);
        $this->Cell(26, 3, 'SOCINBOL', 0, 0, 'C');

        // Salida del PDF
        $this->Output('I', 'vale_combustible.pdf');
    }


    public function generarSolicitudMantenimiento($solicitud)
    {
        // ── Datos de ejemplo (reemplazar por parámetro dinámico) ──────────
        $empresaLocal   = 'PLUS METALS LTDA.';
        $nroSolicitud   = $solicitud?->nro_solicitud;
        $fechaSolicitud = $solicitud?->fecha_solicitud;
        $solicitante    = $solicitud->persona?->nombre_completo;
        $maquinaria     = "{$solicitud->vehiculo?->codigo} {$solicitud->vehiculo?->marca}";
        $modelo         = $solicitud->vehiculo?->anio;
        $tipoMant       = $solicitud->tipo_mantenimiento === 'PREVENTIVO' ? 'A' : 'B'; // 'A' = PREVENTIVO, 'B' = CORRECTIVO
        $descripcion    = $solicitud->descripcion_problema;
        $observaciones  = '';

        $trabajos = [
            ['fecha' => '',          'horometro' => '',         'repuesto' => '',                     'codigo' => '',        'cantidad' => ''],
            ['fecha' => '',           'horometro' => '',        'repuesto' => '',                     'codigo' => '',        'cantidad' => ''],
            ['fecha' => '',           'horometro' => '',         'repuesto' => '',                    'codigo' => '',        'cantidad' => ''],
            ['fecha' => '',           'horometro' => '',         'repuesto' => '',                    'codigo' => '',        'cantidad' => ''],
            ['fecha' => '',           'horometro' => '',         'repuesto' => '',                    'codigo' => '',        'cantidad' => ''],
            ['fecha' => '',           'horometro' => '',         'repuesto' => '',                    'codigo' => '',        'cantidad' => ''],
            ['fecha' => '',           'horometro' => '',         'repuesto' => '',                    'codigo' => '',        'cantidad' => ''],
            ['fecha' => '',           'horometro' => '',         'repuesto' => '',                    'codigo' => '',        'cantidad' => ''],
        ];

        // ── Colores (consistentes con generarVale) ────────────────────────
        $azul   = [39, 42, 84];
        $rojo   = [190, 30, 30];
        $negro  = [30, 30, 30];
        $gris   = [90, 90, 90];
        $blanco = [255, 255, 255];

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(false);

        $sx = 8;      // origen X
        $sy = 8;      // origen Y
        $uw = 199.9;  // ancho útil (215.9 - 16), tamaño Carta/Letter
        $sBottom = 271.4; // límite inferior útil (279.4 - 8), tamaño Carta/Letter

        // ── Borde exterior ────────────────────────────────────────────────
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.5);
        $this->Rect($sx, $sy, $uw, $sBottom - $sy);

        // ══════════════════════════════════════════════════════════════════
        // SECCIÓN 1 – ENCABEZADO  (y=8, h=16)
        // ══════════════════════════════════════════════════════════════════
        $h1    = 16;
        $logoW = 45;
        $nroW  = 44;
        $titW  = $uw - $logoW - $nroW; // ~110.9

        $this->SetLineWidth(0.3);

        // Celda logo
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $sy, $logoW, $h1);
        $this->Image(public_path('images/logo/logo-plus-metals-azul.png'), $sx + 4, $sy + 1.5, 40);

        // Celda título
        $this->Rect($sx + $logoW, $sy, $titW, $h1);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + $logoW, $sy);
        $this->Cell($titW, $h1, utf8Decode('SOLICITUD DE MANTENIMIENTO EQUIPO'), 0, 0, 'C');

        // Celda número
        $this->Rect($sx + $logoW + $titW, $sy, $nroW, $h1);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($rojo[0], $rojo[1], $rojo[2]);
        $this->SetXY($sx + $logoW + $titW, $sy);
        $this->Cell($nroW, $h1, utf8Decode('N° ' . $nroSolicitud), 0, 0, 'C');

        // ══════════════════════════════════════════════════════════════════
        // SECCIÓN 2 – DATOS + TIPO MANTENIMIENTO  (y=24, h=44)
        // ══════════════════════════════════════════════════════════════════
        $s2Y   = $sy + $h1; // 24
        $s2H   = 44;
        $leftW = 130;
        $rigW  = $uw - $leftW; // ~69.9

        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $s2Y, $leftW, $s2H);
        $this->Rect($sx + $leftW, $s2Y, $rigW, $s2H);

        // -- Campos lado izquierdo --
        $labelW = 54;
        $rowH   = 10;
        $campos = [
            ['FECHA DE SOLICITUD :',      $fechaSolicitud],
            ['NOMBRE DEL SOLICITANTE :',  $solicitante],
            ['MAQUINARIA Y/O EQUIPO :',   $maquinaria],
            ['MODELO :',                  $modelo],
        ];

        $this->SetLineWidth(0.2);
        $yF = $s2Y + 2;
        foreach ($campos as [$etiq, $val]) {
            $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
            $this->Line($sx + 2, $yF + $rowH - 1, $sx + $leftW - 2, $yF + $rowH - 1);

            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($sx + 2, $yF);
            $this->Cell($labelW, $rowH, utf8Decode($etiq), 0, 0, 'L');

            $this->SetFont('Arial', '', 8);
            $this->Cell($leftW - $labelW - 4, $rowH, utf8Decode($val), 0, 0, 'L');
            $yF += $rowH;
        }

        // -- Tipo de mantenimiento (lado derecho) --
        $rx = $sx + $leftW;

        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($rx, $s2Y, $rigW, 10, 'FD');
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
        $this->SetXY($rx, $s2Y);
        $this->Cell($rigW, 10, utf8Decode('TIPO DE MANTENIMIENTO'), 0, 0, 'C');

        $opciones = [['A = PREVENTIVO', 'A'], ['B = CORRECTIVO', 'B']];
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.4);
        $yOp = $s2Y + 13;
        foreach ($opciones as [$texto, $tipo]) {
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($rx + 5, $yOp);
            $this->Cell($rigW - 20, 8, utf8Decode($texto), 0, 0, 'L');

            $cirX = $rx + $rigW - 9;
            $cirY = $yOp + 4;
            $this->drawCircle($cirX, $cirY, 4);
            if ($tipoMant === $tipo) {
                $this->SetFillColor($azul[0], $azul[1], $azul[2]);
                $this->drawCircle($cirX, $cirY, 2.5, 'F');
            }
            $yOp += 15;
        }

        // ══════════════════════════════════════════════════════════════════
        // SECCIÓN 3 – DESCRIPCIÓN DE LA FALLA  (y=68, h=36)
        // ══════════════════════════════════════════════════════════════════
        $s3Y = $s2Y + $s2H; // 68
        $s3H = 36;

        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $s3Y, $uw, 8, 'FD');
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
        $this->SetXY($sx, $s3Y);
        $this->Cell($uw, 8, utf8Decode('DESCRIPCION DE LA FALLA DEL EQUIPO'), 0, 0, 'C');

        $this->SetLineWidth(0.3);
        $this->Rect($sx, $s3Y + 8, $uw, $s3H - 8);
        if ($descripcion) {
            $this->SetFont('Arial', '', 8.5);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($sx + 2, $s3Y + 10);
            $this->MultiCell($uw - 4, 5, utf8Decode($descripcion), 0, 'L');
        }

        // ══════════════════════════════════════════════════════════════════
        // SECCIÓN 4 – TRABAJOS REALIZADOS  (y=104)
        // ══════════════════════════════════════════════════════════════════
        $s4Y     = $s3Y + $s3H; // 104
        $tblRowH = 8;

        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $s4Y, $uw, 8, 'FD');
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
        $this->SetXY($sx, $s4Y);
        $this->Cell($uw, 8, utf8Decode('TRABAJOS REALIZADOS'), 0, 0, 'C');

        // Cabecera de columnas — anchos suman $uw (199.9, tamaño Carta/Letter)
        $cols = [
            ['label' => 'FECHA',                     'w' => 24.7, 'align' => 'C'],
            ['label' => 'HOROMETRO',                 'w' => 25.8, 'align' => 'C'],
            ['label' => 'REPUESTO UTILIZADO',        'w' => 58.7, 'align' => 'C'],
            ['label' => 'CODIGO O NRO. DE REPUESTO', 'w' => 62.9, 'align' => 'C'],
            ['label' => 'CANTIDAD',                  'w' => 27.8, 'align' => 'C'],
        ];

        $colHdrY = $s4Y + 8;
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.25);
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        $cx = $sx;
        foreach ($cols as $col) {
            $this->Rect($cx, $colHdrY, $col['w'], $tblRowH);
            $this->SetXY($cx, $colHdrY);
            $this->Cell($col['w'], $tblRowH, utf8Decode($col['label']), 0, 0, 'C');
            $cx += $col['w'];
        }

        // Filas de datos
        $this->SetFont('Arial', '', 7.5);
        $dataY = $colHdrY + $tblRowH;

        foreach ($trabajos as $t) {
            $vals = [$t['fecha'], $t['horometro'], $t['repuesto'], $t['codigo'], $t['cantidad']];
            $cx   = $sx;
            foreach ($cols as $idx => $col) {
                $this->Rect($cx, $dataY, $col['w'], $tblRowH);
                $this->SetXY($cx + 1, $dataY);
                $align = ($idx === 2 || $idx === 3) ? 'L' : 'C';
                $this->Cell($col['w'] - 2, $tblRowH, utf8Decode($vals[$idx]), 0, 0, $align);
                $cx += $col['w'];
            }
            $dataY += $tblRowH;
        }

        // ══════════════════════════════════════════════════════════════════
        // SECCIÓN 5 – OBSERVACIONES  (dinámico, después de tabla)
        // ══════════════════════════════════════════════════════════════════
        $s5Y = $dataY;
        $s5H = 30; // altura reducida para que todo entre en tamaño Carta/Letter

        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $s5Y, $uw, 8, 'FD');
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
        $this->SetXY($sx, $s5Y);
        $this->Cell($uw, 8, utf8Decode('OBSERVACIONES'), 0, 0, 'C');

        $this->SetLineWidth(0.3);
        $this->Rect($sx, $s5Y + 8, $uw, $s5H - 8);
        if ($observaciones) {
            $this->SetFont('Arial', '', 8.5);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($sx + 2, $s5Y + 10);
            $this->MultiCell($uw - 4, 5, utf8Decode($observaciones), 0, 'L');
        }

        // ══════════════════════════════════════════════════════════════════
        // SECCIÓN 6 – FIRMAS  (ocupa el resto hasta $sBottom, altura reducida)
        // ══════════════════════════════════════════════════════════════════
        $s6Y  = $s5Y + $s5H;
        $s6H  = $sBottom - $s6Y;
        $cw3  = $uw / 3; // ~66.6 mm por columna

        $firmas = ['SOLICITANTE:', 'EJECUTOR DE TRABAJO:', 'Vo. Bo.'];

        for ($i = 0; $i < 3; $i++) {
            $fx = $sx + ($i * $cw3);

            $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
            $this->SetLineWidth(0.3);
            $this->Rect($fx, $s6Y, $cw3, $s6H);

            // Etiqueta superior
            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($fx + 2, $s6Y + 3);
            $this->Cell($cw3 - 4, 6, utf8Decode($firmas[$i]), 0, 0, 'L');

            // Línea de firma
            $this->SetLineWidth(0.2);
            $this->Line($fx + 3, $s6Y + $s6H - 19, $fx + $cw3 - 3, $s6Y + $s6H - 19);

            if($i === 0) {
                //imprimir sobre la línea de firma el nombre del solicitante
                $this->SetFont('Arial', 'B', 9);
                $this->SetXY($fx + 2, $s6Y + $s6H - 25);
                $this->Cell($cw3 - 4, 5, utf8Decode($solicitante), 0, 0, 'C');
            }

            // NOMBRE COMPLETO
            $this->SetFont('Arial', 'B', 7);
            $this->SetXY($fx, $s6Y + $s6H - 17);
            $this->Cell($cw3, 5, 'NOMBRE COMPLETO', 0, 0, 'C');

            // FECHA
            $this->SetFont('Arial', '', 7);
            $this->SetXY($fx + 2, $s6Y + $s6H - 10);
            $this->Cell($cw3 - 4, 5, utf8Decode('FECHA ......../......../........'), 0, 0, 'C');
        }


        $this->Output('I', 'solicitud_mantenimiento.pdf');
    }

    public function generarReporteCargasCombustible($fechaInicio, $fechaFin, $idVehiculo = null)
    {
        // Importar modelo
        $cargasCombustibleQuery = \App\Models\CargaCombustible::whereBetween('fecha_carga', [$fechaInicio, $fechaFin])
            ->with(['vehiculo', 'tipoCombustible']);

        if ($idVehiculo) {
            $cargasCombustibleQuery->where('id_vehiculo', $idVehiculo);
        }

        $cargas = $cargasCombustibleQuery->orderBy('fecha_carga')->get();

        // Agrupar por vehículo
        $vehiculosAgrupados = $cargas->groupBy('id_vehiculo')->map(function ($grupo) {
            $primerCarga = $grupo->first();
            return [
                'vehiculo' => $primerCarga->vehiculo,
                'cargas' => $grupo->toArray(),
                'total_litros' => $grupo->sum('litros'),
                'total_costo' => $grupo->sum(function ($c) {
                    return $c->precio * $c->litros;
                }),
            ];
        })->values();

        // Calcular totales generales
        $totalLitros = $cargas->sum('litros');
        $totalCosto = $cargas->sum(function ($c) {
            return $c->precio * $c->litros;
        });

        // Colores
        $azul = [39, 42, 84];
        $verde = [24, 125, 170];
        $rojo = [190, 30, 30];
        $negro = [30, 30, 30];
        $gris = [90, 90, 90];
        $blanco = [255, 255, 255];

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(true, 15);

        $sx = 8;
        $sy = 8;
        $uw = 199.9;

        // ════════════════════════════════════════════════════════════════
        // ENCABEZADO
        // ════════════════════════════════════════════════════════════════
        $this->SetLineWidth(0.5);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $sy, $uw, 16);

        // Logo
        $this->Image(public_path('images/logo/logo-plus-metals-azul.png'), $sx + 4, $sy + 1.5, 35);

        // Título
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy);
        $this->Cell($uw - 40, 8, utf8Decode('REPORTE DE CARGAS DE COMBUSTIBLE'), 0, 2, 'L');

        // Rango de fechas
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx + 40, $sy + 8);
        $this->Cell($uw - 40, 8, utf8Decode("Del " . date('d/m/Y', strtotime($fechaInicio)) . " al " . date('d/m/Y', strtotime($fechaFin))), 0, 2, 'L');

        $currentY = $sy + 16 + 5;

        // ════════════════════════════════════════════════════════════════
        // TARJETAS DE RESUMEN
        // ════════════════════════════════════════════════════════════════
        $cardW = 49;
        $cardH = 14;
        $cards = [
            ['TOTAL LITROS', round($totalLitros, 2) . ' L', 'Azul'],
            ['TOTAL COSTO', 'Bs. ' . number_format($totalCosto, 2, ',', '.'), 'Verde'],
            ['COSTO/LITRO', 'Bs. ' . number_format($totalLitros > 0 ? $totalCosto / $totalLitros : 0, 2, ',', '.'), 'Rojo'],
            ['CANTIDAD CARGAS', count($cargas), 'Gris'],
        ];

        $cardX = $sx;
        foreach ($cards as $card) {
            $color = match ($card[2]) {
                'Azul' => [59, 89, 152],
                'Verde' => [34, 177, 76],
                'Rojo' => [192, 0, 0],
                'Gris' => [155, 155, 155],
            };

            $this->SetLineWidth(0.3);
            $this->SetDrawColor($color[0], $color[1], $color[2]);
            $this->SetFillColor($color[0], $color[1], $color[2]);
            $this->Rect($cardX, $currentY, $cardW, $cardH, 'FD');

            $this->SetFont('Arial', 'B', 7);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($cardX, $currentY);
            $this->Cell($cardW, 5, utf8Decode($card[0]), 0, 1, 'C');

            $this->SetFont('Arial', 'B', 10);
            $this->SetXY($cardX, $currentY + 5);
            $this->Cell($cardW, 9, utf8Decode($card[1]), 0, 1, 'C');

            $cardX += $cardW + 2;
        }

        $currentY += $cardH + 8;

        // ════════════════════════════════════════════════════════════════
        // TABLA DE VEHÍCULOS
        // ════════════════════════════════════════════════════════════════
        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $currentY, $uw, 8, 'FD');

        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY($sx, $currentY);
        $this->Cell($uw, 8, utf8Decode('DETALLE POR VEHÍCULO'), 0, 1, 'C');

        $currentY += 8;

        // Encabezados de columnas
        $cols = [
            ['label' => 'PLACA', 'w' => 25, 'align' => 'C'],
            ['label' => 'MARCA', 'w' => 35, 'align' => 'L'],
            ['label' => 'TOTAL LITROS', 'w' => 28, 'align' => 'R'],
            ['label' => 'TOTAL COSTO (Bs.)', 'w' => 35, 'align' => 'R'],
            ['label' => 'NRO. CARGAS', 'w' => 20, 'align' => 'C'],
            ['label' => 'PRECIO PROM.', 'w' => 27.9, 'align' => 'R'],
        ];

        $this->SetFillColor(240, 240, 240);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.2);
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        $colX = $sx;
        foreach ($cols as $col) {
            $this->Rect($colX, $currentY, $col['w'], 7, 'FD');
            $this->SetXY($colX, $currentY);
            $this->Cell($col['w'], 7, utf8Decode($col['label']), 0, 0, $col['align']);
            $colX += $col['w'];
        }

        $currentY += 7;

        // Filas de datos
        $this->SetFont('Arial', '', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        foreach ($vehiculosAgrupados as $vehData) {
            $precioPromedio = count($vehData['cargas']) > 0
                ? array_sum(array_map(fn($c) => $c['precio'], $vehData['cargas'])) / count($vehData['cargas'])
                : 0;

            $valores = [
                $vehData['vehiculo']->nro_placa,
                $vehData['vehiculo']->marca,
                number_format($vehData['total_litros'], 2, ',', '.'),
                number_format($vehData['total_costo'], 2, ',', '.'),
                count($vehData['cargas']),
                number_format($precioPromedio, 2, ',', '.'),
            ];

            $colX = $sx;
            foreach ($cols as $idx => $col) {
                $this->SetDrawColor($gris[0], $gris[1], $gris[2]);
                $this->SetLineWidth(0.1);
                $this->Rect($colX, $currentY, $col['w'], 6);
                $this->SetXY($colX + 1, $currentY + 0.5);
                $this->Cell($col['w'] - 2, 6, utf8Decode($valores[$idx]), 0, 0, $col['align']);
                $colX += $col['w'];
            }

            $currentY += 6;
        }

        // Fila de TOTALES
        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(255, 255, 255);

        $totales = [
            'TOTALES',
            '',
            number_format($totalLitros, 2, ',', '.'),
            number_format($totalCosto, 2, ',', '.'),
            count($cargas),
            number_format($totalLitros > 0 ? $totalCosto / $totalLitros : 0, 2, ',', '.'),
        ];

        $colX = $sx;
        foreach ($cols as $idx => $col) {
            $this->Rect($colX, $currentY, $col['w'], 7, 'FD');
            $this->SetXY($colX + 1, $currentY);
            $this->Cell($col['w'] - 2, 7, utf8Decode($totales[$idx]), 0, 0, $col['align']);
            $colX += $col['w'];
        }

        // ════════════════════════════════════════════════════════════════
        // PIE DE PÁGINA
        // ════════════════════════════════════════════════════════════════
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->Cell($uw, 4, utf8Decode('Reporte generado el: ' . date('d/m/Y H:i')), 0, 0, 'L');
        $this->Cell($uw, 4, utf8Decode('Página: ') . $this->PageNo() . '/{nb}', 0, 0, 'R');

        $this->AliasNbPages();
        $this->Output('I', 'reporte_cargas_combustible.pdf');
    }

    protected function drawCircle(float $cx, float $cy, float $r, string $style = 'D'): void
    {
        if ($style === 'F') {
            $op = 'f';
        } elseif ($style === 'FD' || $style === 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        $k  = $this->k;
        $h  = $this->h;
        $lx = 4 / 3 * (sqrt(2) - 1) * $r;
        $this->_out(sprintf(
            '%.2F %.2F m '
            . '%.2F %.2F %.2F %.2F %.2F %.2F c '
            . '%.2F %.2F %.2F %.2F %.2F %.2F c '
            . '%.2F %.2F %.2F %.2F %.2F %.2F c '
            . '%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($cx + $r) * $k, ($h - $cy) * $k,
            ($cx + $r) * $k, ($h - $cy + $lx) * $k,  ($cx + $lx) * $k, ($h - $cy + $r) * $k,  $cx * $k,          ($h - $cy + $r) * $k,
            ($cx - $lx) * $k, ($h - $cy + $r) * $k,  ($cx - $r) * $k,  ($h - $cy + $lx) * $k, ($cx - $r) * $k,   ($h - $cy) * $k,
            ($cx - $r) * $k,  ($h - $cy - $lx) * $k, ($cx - $lx) * $k, ($h - $cy - $r) * $k,  $cx * $k,          ($h - $cy - $r) * $k,
            ($cx + $lx) * $k, ($h - $cy - $r) * $k,  ($cx + $r) * $k,  ($h - $cy - $lx) * $k, ($cx + $r) * $k,   ($h - $cy) * $k,
            $op
        ));
    }
}

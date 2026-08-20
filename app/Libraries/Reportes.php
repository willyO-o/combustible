<?php

namespace App\Libraries;

use App\Models\CargaCombustible;
use App\Models\ParametrosEmpresa;
use FPDF;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Reportes extends FPDF
{
    /**
     * @param  string  $modo  Destino de salida de FPDF: 'I' (mostrar inline en el navegador,
     *                        usado por la vista web), 'D' (forzar descarga en el navegador) o
     *                        'S' (devolver el PDF como string, usado por la API para que el
     *                        controlador arme la respuesta HTTP con sus propios encabezados).
     */
    public function generarVale($vale, string $modo = 'I', ?string $nombreArchivo = null)
    {
        // logo======
        // DATOS DE EJEMPLO (reemplazar por variables dinámicas luego)
        // ==========================================================

        $parametrosEmpresa = $this->parametrosEmpresa();

        $empresaLocal = $parametrosEmpresa->nombre_empresa;
        $numeroVale = $vale->nro; // Número de vale (formateado con ceros a la izquierda)
        $empresa = $vale->grifo->razon_social;
        $direccion = $vale->grifo->direccion;
        $telefono = 'Tel. '.$vale->grifo->telefono;
        $ciudad = $vale->grifo->ciudad;

        $cliente = $vale->conductor->persona->nombre_completo;
        $licencia = $vale->conductor->licenciaConducir()?->numero_documento ?? 'N/A';
        $vehiculo = $vale->vehiculo->codigo;
        $marca = $vale->vehiculo->marca;
        $placa = $vale->vehiculo->nro_placa;

        $litros = $vale->litros;
        $bolivianos = $vale->precio;
        // $tipoGasolina   = true;   // marca el checkbox "Gasolina"
        $tipoDiesel = false;  // marca el checkbox "Diesel"
        $autorizadoPor = $vale->user?->name;
        // $facturaNro     = '000452';
        $tipoCombustible = $vale->tipoCombustible->tipo_combustible;

        // Colores base (verde institucional / rojo para el correlativo)
        // $verde  = [20, 110, 60];
        $verde = [1, 82, 145];
        $rojo = [203, 39, 45];
        $negro = [30, 30, 30];
        $gris = [90, 90, 90];

        $this->AddPage('L', [219, 140]);
        $this->SetMargins(5, 5, 5);
        $this->SetAutoPageBreak(false);

        $pageW = 219;

        // ----------------------------------------------------------
        // ENCABEZADO
        // ----------------------------------------------------------
        // Título "VALE"
        $this->Image(public_path('images/reportes/vale-fondo.png'), 0, 0, 219, 140);
        $this->Image(public_path('images/logo/logo-min.png'), 6, 5.5, 25, 15);

        $this->SetXY(35, 3);
        $this->SetFont('Arial', 'BI', 40);
        $this->SetTextColor($verde[0], $verde[1], $verde[2]);
        $this->Cell(40, 16, utf8Decode('VALE'), 0, 0, 'L');

        $this->Image(public_path('images/logo/gas.jpg'), 198.5, 2.5, 15);

        // Recuadro rojo con el número de vale (arriba a la derecha)
        // $this->SetDrawColor($rojo[0], $rojo[1], $rojo[2]);
        // $this->SetLineWidth(0.4);
        // $this->Rect(40, 5, 16, 17);
        $this->SetXY(35, 17);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($rojo[0], $rojo[1], $rojo[2]);
        $this->Cell(40, 4, utf8Decode('N°'.$numeroVale), 0, 2, 'C');
        // $this->SetFont('Arial', 'B', 12);
        // $this->Cell(27, 6, $numeroVale, 0, 0, 'C');
        // Datos de la empresa (alineados a la derecha, arriba)

        $this->SetXY(123, 3);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->Cell(74, 4, utf8Decode($empresa), 0, 2, 'R');

        $this->SetXY(123, 3);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->Cell(74, 4, utf8Decode($empresa), 0, 2, 'R');
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->Cell(74, 3.5, utf8Decode($direccion), 0, 2, 'R');
        $this->Cell(74, 3.5, utf8Decode($telefono), 0, 2, 'R');
        $this->Cell(74, 3.5, utf8Decode($ciudad), 0, 2, 'R');

        $this->SetXY(5, 20);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($pageW - 12, 5, utf8Decode(mb_strtoupper($empresaLocal)), 0, 0, 'R');

        // ----------------------------------------------------------
        // CUERPO: 2 cuadros (Cliente / Combustible)
        // ----------------------------------------------------------
        $bodyY = 34;
        $bodyH = 100;
        $leftX = 5;
        $leftW = 108;
        $rightX = 116;
        $rightW = 98;

        // $this->SetDrawColor($verde[0], $verde[1], $verde[2]);
        // $this->SetLineWidth(0.3);
        // $this->Rect($leftX, $bodyY, $leftW, $bodyH);   // cuadro izquierdo
        // $this->Rect($rightX, $bodyY, $rightW, $bodyH); // cuadro derecho

        // --- Cuadro izquierdo: datos del cliente ---

        $filas = [
            ['Nombre:', $cliente],
            ['Licencia:', $licencia],
            ['Vehículo:', $vehiculo],
            ['Marca:', $marca],
            ['Placa:', $placa],
        ];

        $y = $bodyY + 12;
        foreach ($filas as $fila) {
            [$etiqueta, $valor] = $fila;

            $this->SetXY($leftX + 30, $y);
            $this->SetFont('Arial', 'B', 9);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            // $this->Cell(24, 6, utf8Decode($etiqueta), 0, 0, 'L');

            $this->SetFont('Arial', '', 9);
            $this->Cell($leftW - 4 - 24 - 4, 6, utf8Decode($valor), 0, 0, 'L');

            $y += 14.5;
        }

        // --- Cuadro derecho: litros / importe ---
        $rx = $rightX;
        $rw = $rightW - 8;

        // Tabla Lt. / Bs.
        $tblY = $bodyY + 3;
        $tblH = 22;
        $colW = $rw / 2;

        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($rx, $tblY);
        $this->Cell($colW, 16, $litros, 0, 0, 'C');
        $this->SetXY($rx + $colW, $tblY);
        $this->Cell($colW, 16, $bolivianos, 0, 0, 'C');

        $y2 = $tblY + $tblH;
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($rx, $y2);
        // $this->Cell(30, 6, utf8Decode('Tipo Combustible: '), 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell($rw, 6, utf8Decode(numeroLiteral($litros, 'LITROS')), 0, 0, 'C');

        $y2 += 7;
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($rx, $y2);
        // $this->Cell(30, 6, utf8Decode('Tipo Combustible: '), 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell($rw, 6, utf8Decode(monedaLiteral($bolivianos)), 0, 0, 'C');

        $y2 += 10;
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($rx + 33, $y2);
        $this->SetFont('Arial', '', 9);
        $this->Cell($rw, 6, utf8Decode($tipoCombustible), 0, 0, 'L');

        $y2 += 9;
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY($rx + 30, $y2);
        $this->SetFont('Arial', '', 9);
        $this->Cell($rw - 28, 6, utf8Decode($autorizadoPor), 0, 0, 'L');

        $y2 += 14;

        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(17, 127);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($rw - 28, 6, utf8Decode('Este vale es de uso unico'), 0, 0, 'L');

        $this->SetXY(89, 128);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($rw - 28, 5, utf8Decode('Emitido el: '.$vale->fecha_emision?->format('d/m/Y H:i')), 0, 0, 'L');

        $this->SetXY(151, 128);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($rw - 28, 5, utf8Decode('Expira el: '.$vale->fecha_vencimiento?->format('d/m/Y H:i')), 0, 0, 'L');

        $urlQr = route('vales.publico', ['hash' => md5($vale->id)]);
        $base64Qr = 'data:image/png;base64,'.$this->getBase64Qr($urlQr);

        $this->Image($base64Qr, 177.5, 95, 23, 23, 'PNG');

        // $this->SetFont('Arial', 'B', 9);
        // $this->SetXY($rx, $y2);
        // $this->Cell(24, 6, utf8Decode('Factura N°:'), 0, 0, 'L');
        // $this->SetFont('Arial', '', 9);
        // $this->Cell($rw - 24, 6, $facturaNro, 'B', 0, 'L');

        // Mini "código de barras" decorativo + marca, esquina inferior derecha

        // Salida del PDF
        // El '/' de $numeroVale ("NNNNNN/GESTION") no es válido dentro de un nombre
        // de archivo, así que se reemplaza por '-' sólo para el nombre sugerido.
        return $this->Output($modo, $nombreArchivo ?? 'vale_'.str_replace('/', '-', (string) $numeroVale).'.pdf');
    }

    public function getBase64Qr($text, $size = 400, $format = 'png', $qualy = 'Q', $logoPath = '', $margin = 2)
    {

        if (empty($text)) {
            throw new \Exception('No se Especificó el Texto para generar el Qr', 1);
        }

        if (empty($logoPath)) {
            return base64_encode(QrCode::encoding('UTF-8')->format($format)->size($size)->errorCorrection($qualy)->margin($margin)->generate($text));
        }

        return base64_encode(QrCode::encoding('UTF-8')->format($format)->merge($logoPath, 0.3)->size($size)->errorCorrection($qualy)->margin($margin)->generate($text));

        // >eyeColor(0, 180, 255, 255, 0, 0, 0)
        //  return base64_encode(QrCode::encoding('UTF-8')->format('png')->merge('/public/assets/logo/logo-circular.png', 0.3)->size(500)->errorCorrection('H')->generate($qrText));

    }

    /**
     * @param  string  $modo  Ver docblock de generarVale().
     */
    public function generarSolicitudMantenimiento($solicitud, string $modo = 'I', ?string $nombreArchivo = null)
    {
        // ── Datos de ejemplo (reemplazar por parámetro dinámico) ──────────
        $empresaLocal = 'PLUS METALS LTDA.';
        $nroSolicitud = $solicitud?->nro;
        $fechaSolicitud = $solicitud?->fecha_solicitud;
        $solicitante = $solicitud->persona?->nombre_completo;
        $maquinaria = "{$solicitud->vehiculo?->codigo} {$solicitud->vehiculo?->marca}";
        $modelo = $solicitud->vehiculo?->anio;
        $tipoMant = $solicitud->tipo_mantenimiento === 'PREVENTIVO' ? 'A' : 'B'; // 'A' = PREVENTIVO, 'B' = CORRECTIVO
        $descripcion = $solicitud->descripcion_problema;
        $observaciones = '';

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
        $azul = [39, 42, 84];
        $rojo = [190, 30, 30];
        $negro = [30, 30, 30];
        $gris = [90, 90, 90];
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
        $h1 = 16;
        $logoW = 45;
        $nroW = 44;
        $titW = $uw - $logoW - $nroW; // ~110.9

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
        $this->Cell($nroW, $h1, utf8Decode('N° '.$nroSolicitud), 0, 0, 'C');

        // ══════════════════════════════════════════════════════════════════
        // SECCIÓN 2 – DATOS + TIPO MANTENIMIENTO  (y=24, h=44)
        // ══════════════════════════════════════════════════════════════════
        $s2Y = $sy + $h1; // 24
        $s2H = 44;
        $leftW = 130;
        $rigW = $uw - $leftW; // ~69.9

        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $s2Y, $leftW, $s2H);
        $this->Rect($sx + $leftW, $s2Y, $rigW, $s2H);

        // -- Campos lado izquierdo --
        $labelW = 54;
        $rowH = 10;
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
        $s4Y = $s3Y + $s3H; // 104
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
            $cx = $sx;
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
        $s6Y = $s5Y + $s5H;
        $s6H = $sBottom - $s6Y;
        $cw3 = $uw / 3; // ~66.6 mm por columna

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

            if ($i === 0) {
                // imprimir sobre la línea de firma el nombre del solicitante
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

        // El '/' de $nroSolicitud ("NNNNNN/GESTION") no es válido dentro de un nombre
        // de archivo, así que se reemplaza por '-' sólo para el nombre sugerido.
        return $this->Output($modo, $nombreArchivo ?? 'solicitud_mantenimiento_'.str_replace('/', '-', (string) $nroSolicitud).'.pdf');
    }

    public function generarReporteCargasCombustible($fechaInicio, $fechaFin, $idVehiculo = null)
    {
        // Importar modelo
        $cargasCombustibleQuery = CargaCombustible::whereBetween('fecha_carga', [$fechaInicio, $fechaFin])
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
        $filaAlterna = [244, 246, 250];

        $parametrosEmpresa = $this->parametrosEmpresa();

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

        // Logo (el configurado en Parámetros de la Empresa, o el de respaldo)
        $this->Image($this->logoEmpresa($parametrosEmpresa), $sx + 4, $sy + 1.5, 35);

        // Título
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy);
        $this->Cell($uw - 40, 8, utf8Decode('REPORTE DE CARGAS DE COMBUSTIBLE'), 0, 2, 'L');

        // Rango de fechas
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx + 40, $sy + 8);
        $this->Cell($uw - 40, 8, utf8Decode('Del '.date('d/m/Y', strtotime($fechaInicio)).' al '.date('d/m/Y', strtotime($fechaFin))), 0, 2, 'L');

        $currentY = $sy + 16 + 2;
        $currentY += $this->pintarInfoEmpresa($parametrosEmpresa, $sx, $currentY, $uw, $gris);
        $currentY += 3;

        // ════════════════════════════════════════════════════════════════
        // TARJETAS DE RESUMEN
        // ════════════════════════════════════════════════════════════════
        $cardW = 49;
        $cardH = 14;
        $cards = [
            ['TOTAL LITROS', round($totalLitros, 2).' L', 'Azul'],
            ['TOTAL COSTO', 'Bs. '.number_format($totalCosto, 2, ',', '.'), 'Verde'],
            ['COSTO/LITRO', 'Bs. '.number_format($totalLitros > 0 ? $totalCosto / $totalLitros : 0, 2, ',', '.'), 'Rojo'],
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
            ['label' => 'CÓD. CONTABLE', 'w' => 26, 'align' => 'L'],
            ['label' => 'PLACA', 'w' => 25.4, 'align' => 'C'],
            ['label' => 'MARCA', 'w' => 35.6, 'align' => 'L'],
            ['label' => 'TOTAL LITROS', 'w' => 28.5, 'align' => 'R'],
            ['label' => 'TOTAL COSTO (Bs.)', 'w' => 35.6, 'align' => 'R'],
            ['label' => 'NRO. CARGAS', 'w' => 20.3, 'align' => 'C'],
            ['label' => 'PRECIO PROM.', 'w' => 28.5, 'align' => 'R'],
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

        foreach ($vehiculosAgrupados as $fila => $vehData) {
            $precioPromedio = count($vehData['cargas']) > 0
                ? array_sum(array_map(fn ($c) => $c['precio'], $vehData['cargas'])) / count($vehData['cargas'])
                : 0;

            $valores = [
                $vehData['vehiculo']->codigo,
                $vehData['vehiculo']->nro_placa,
                $vehData['vehiculo']->marca,
                number_format($vehData['total_litros'], 2, ',', '.'),
                number_format($vehData['total_costo'], 2, ',', '.'),
                count($vehData['cargas']),
                number_format($precioPromedio, 2, ',', '.'),
            ];

            // Franjas alternadas por fila: mejora la lectura en tablas largas.
            $this->SetFillColor($filaAlterna[0], $filaAlterna[1], $filaAlterna[2]);
            $conFondo = $fila % 2 === 1;

            $colX = $sx;
            foreach ($cols as $idx => $col) {
                $this->SetDrawColor($gris[0], $gris[1], $gris[2]);
                $this->SetLineWidth(0.1);
                $this->Rect($colX, $currentY, $col['w'], 6, $conFondo ? 'FD' : 'D');
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
        $this->pintarPieDePagina($uw, $gris);
        $this->Output('I', 'reporte_cargas_combustible.pdf');
    }

    /**
     * Reporte general de rendimiento: uno o varios vehículos comparados
     * (resumen agrupado, sin gráfico). $resultado es la colección que
     * devuelve CargasCombustibleReportController::obtenerResumenRendimiento()
     * con $soloResumen = true (una fila por vehículo).
     *
     * @param  array{tipo_combustible?: ?string, area?: ?string}  $filtrosAplicados  Etiquetas
     *                                                                               ya resueltas a texto (no ids) de los filtros de tipo de combustible/área
     *                                                                               aplicados en la vista, para dejar constancia de ellos en el PDF.
     */
    public function generarReporteRendimiento($resultado, $fechaInicio, $fechaFin, string $modo = 'I', ?string $nombreArchivo = null, array $filtrosAplicados = [])
    {
        $resultado = collect($resultado);

        // Colores (consistentes con generarReporteCargasCombustible)
        $azul = [39, 42, 84];
        $verde = [24, 125, 170];
        $rojo = [190, 30, 30];
        $negro = [30, 30, 30];
        $gris = [90, 90, 90];
        $blanco = [255, 255, 255];
        $filaAlterna = [244, 246, 250];

        $parametrosEmpresa = $this->parametrosEmpresa();

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(true, 15);

        $sx = 8;
        $sy = 8;
        $uw = 199.9;

        // ════════════════════════════════════════════════════════════════
        // ENCABEZADO
        // ════════════════════════════════════════════════════════════════
        $etiquetasFiltro = array_filter([
            $filtrosAplicados['tipo_combustible'] ?? null ? 'Combustible: '.$filtrosAplicados['tipo_combustible'] : null,
            $filtrosAplicados['area'] ?? null ? 'Área: '.$filtrosAplicados['area'] : null,
        ]);
        $h1 = $etiquetasFiltro ? 22 : 16;

        // Barra de acento superior, a modo de detalle visual del encabezado.
        $this->SetFillColor($verde[0], $verde[1], $verde[2]);
        $this->Rect($sx, $sy, $uw, 1.2, 'F');

        $this->SetLineWidth(0.5);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $sy + 1.2, $uw, $h1 - 1.2);

        $this->Image($this->logoEmpresa($parametrosEmpresa), $sx + 4, $sy + 3, 35);

        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy + 1.5);
        $this->Cell($uw - 40, 8, utf8Decode('REPORTE DE RENDIMIENTO DE COMBUSTIBLE'), 0, 2, 'L');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx + 40, $sy + 9.5);
        $this->Cell($uw - 40, 6, utf8Decode('Del '.date('d/m/Y', strtotime($fechaInicio)).' al '.date('d/m/Y', strtotime($fechaFin))), 0, 2, 'L');

        if ($etiquetasFiltro) {
            $this->SetFont('Arial', 'BI', 8);
            $this->SetTextColor($verde[0], $verde[1], $verde[2]);
            $this->SetXY($sx + 40, $sy + 16);
            $this->Cell($uw - 40, 5, utf8Decode('Filtros aplicados: '.implode('   |   ', $etiquetasFiltro)), 0, 2, 'L');
        }

        $currentY = $sy + $h1 + 2;
        $currentY += $this->pintarInfoEmpresa($parametrosEmpresa, $sx, $currentY, $uw, $gris);
        $currentY += 3;

        // ════════════════════════════════════════════════════════════════
        // TARJETAS DE RESUMEN
        // ════════════════════════════════════════════════════════════════
        $totalLitros = $resultado->sum(fn ($r) => (float) $r->total_litros);
        $totalRecorrido = $resultado->sum(fn ($r) => (float) $r->total_recorrido);
        $totalCargas = $resultado->sum(fn ($r) => (int) $r->total_cargas);

        $cardW = 49;
        $cardH = 14;
        $cards = [
            ['VEHÍCULOS COMPARADOS', (string) $resultado->count(), 'Azul'],
            ['TOTAL LITROS', number_format($totalLitros, 2, ',', '.').' L', 'Verde'],
            ['TOTAL RECORRIDO/HORAS', number_format($totalRecorrido, 2, ',', '.'), 'Rojo'],
            ['TOTAL CARGAS', (string) $totalCargas, 'Gris'],
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

        $cols = [
            ['label' => 'CÓDIGO', 'w' => 24, 'align' => 'L'],
            ['label' => 'PLACA', 'w' => 22, 'align' => 'C'],
            ['label' => 'COMBUSTIBLE', 'w' => 26, 'align' => 'C'],
            ['label' => 'TIPO MEDICIÓN', 'w' => 26, 'align' => 'C'],
            ['label' => 'CARGAS', 'w' => 16, 'align' => 'C'],
            ['label' => 'LITROS', 'w' => 26, 'align' => 'R'],
            ['label' => 'RECORRIDO/HORAS', 'w' => 30.9, 'align' => 'R'],
            ['label' => 'RENDIMIENTO', 'w' => 29, 'align' => 'R'],
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

        $this->SetFont('Arial', '', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        if ($resultado->isEmpty()) {
            $this->SetDrawColor($gris[0], $gris[1], $gris[2]);
            $this->SetLineWidth(0.1);
            $this->Rect($sx, $currentY, $uw, 7);
            $this->SetXY($sx, $currentY);
            $this->Cell($uw, 7, utf8Decode('No hay datos de rendimiento para el rango seleccionado.'), 0, 0, 'C');
            $currentY += 7;
        }

        foreach ($resultado as $fila => $r) {
            $sinDatos = (float) $r->total_recorrido === 0.0;
            $tipoLabel = $r->tipo_medicion === 'horometro' ? 'Horómetro' : 'Kilometraje';

            $valores = [
                $r->codigo,
                $r->nro_placa,
                $r->tipo_combustible,
                $tipoLabel,
                (string) $r->total_cargas,
                number_format((float) $r->total_litros, 2, ',', '.').' L',
                number_format((float) $r->total_recorrido, 2, ',', '.'),
                $sinDatos ? 'Sin datos suficientes' : number_format((float) $r->rendimiento_promedio, 2, ',', '.').' '.$r->unidad_medida,
            ];

            // Franjas alternadas por fila: mejora la lectura en tablas largas.
            $this->SetFillColor($filaAlterna[0], $filaAlterna[1], $filaAlterna[2]);
            $conFondo = $fila % 2 === 1;

            $colX = $sx;
            foreach ($cols as $idx => $col) {
                $this->SetDrawColor($gris[0], $gris[1], $gris[2]);
                $this->SetLineWidth(0.1);
                $this->Rect($colX, $currentY, $col['w'], 6, $conFondo ? 'FD' : 'D');
                $this->SetXY($colX + 1, $currentY + 0.5);
                $this->SetFont('Arial', $idx === 7 && $sinDatos ? 'I' : '', 7.5);
                $this->SetTextColor($idx === 7 && $sinDatos ? $gris[0] : $negro[0], $idx === 7 && $sinDatos ? $gris[1] : $negro[1], $idx === 7 && $sinDatos ? $gris[2] : $negro[2]);
                $this->Cell($col['w'] - 2, 6, utf8Decode($valores[$idx]), 0, 0, $col['align']);
                $colX += $col['w'];
            }

            $currentY += 6;
        }

        // Fila de TOTALES (sólo los campos que sí son sumables entre vehículos:
        // el rendimiento no se totaliza porque km/L y L/h no son comparables)
        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(255, 255, 255);

        $totales = ['TOTALES', '', '', '', (string) $totalCargas, number_format($totalLitros, 2, ',', '.').' L', number_format($totalRecorrido, 2, ',', '.'), '—'];

        $colX = $sx;
        foreach ($cols as $idx => $col) {
            $this->Rect($colX, $currentY, $col['w'], 7, 'FD');
            $this->SetXY($colX + 1, $currentY);
            $this->Cell($col['w'] - 2, 7, utf8Decode($totales[$idx]), 0, 0, $col['align']);
            $colX += $col['w'];
        }

        $this->pintarPieDePagina($uw, $gris);

        return $this->Output($modo, $nombreArchivo ?? 'reporte_rendimiento_combustible.pdf');
    }

    /**
     * Detalle carga por carga del rendimiento de UN solo vehículo (drill-down
     * del reporte general). $detalle es la colección que devuelve
     * obtenerResumenRendimiento() con $soloResumen = false, ya filtrada a un
     * único id_vehiculo.
     */
    public function generarReporteDetalleRendimiento($vehiculo, $detalle, $fechaInicio, $fechaFin, string $modo = 'I', ?string $nombreArchivo = null)
    {
        $detalle = collect($detalle);

        $azul = [39, 42, 84];
        $verde = [24, 125, 170];
        $rojo = [190, 30, 30];
        $negro = [30, 30, 30];
        $gris = [90, 90, 90];
        $blanco = [255, 255, 255];
        $filaAlterna = [244, 246, 250];

        $esHorometro = $vehiculo->tipo_medicion === 'horometro';
        $unidad = $esHorometro ? 'L/h' : 'km/L';
        $etiquetaRecorrido = $esHorometro ? 'HORAS' : 'RECORRIDO';
        $tipoCombustibleLabel = $vehiculo->tipoCombustible?->tipo_combustible;
        $parametrosEmpresa = $this->parametrosEmpresa();

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(true, 15);

        $sx = 8;
        $sy = 8;
        $uw = 199.9;

        // ════════════════════════════════════════════════════════════════
        // ENCABEZADO
        // ════════════════════════════════════════════════════════════════
        $h1 = $tipoCombustibleLabel ? 22 : 16;

        // Barra de acento superior, a modo de detalle visual del encabezado.
        $this->SetFillColor($verde[0], $verde[1], $verde[2]);
        $this->Rect($sx, $sy, $uw, 1.2, 'F');

        $this->SetLineWidth(0.5);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $sy + 1.2, $uw, $h1 - 1.2);

        $this->Image($this->logoEmpresa($parametrosEmpresa), $sx + 4, $sy + 3, 35);

        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy + 1.5);
        $this->Cell($uw - 40, 8, utf8Decode('DETALLE DE RENDIMIENTO — '.$vehiculo->codigo.' ('.$vehiculo->nro_placa.')'), 0, 2, 'L');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx + 40, $sy + 9.5);
        $this->Cell($uw - 40, 6, utf8Decode('Del '.date('d/m/Y', strtotime($fechaInicio)).' al '.date('d/m/Y', strtotime($fechaFin))), 0, 2, 'L');

        if ($tipoCombustibleLabel) {
            $this->SetFont('Arial', 'BI', 8);
            $this->SetTextColor($verde[0], $verde[1], $verde[2]);
            $this->SetXY($sx + 40, $sy + 16);
            $this->Cell($uw - 40, 5, utf8Decode('Combustible: '.$tipoCombustibleLabel), 0, 2, 'L');
        }

        $currentY = $sy + $h1 + 2;
        $currentY += $this->pintarInfoEmpresa($parametrosEmpresa, $sx, $currentY, $uw, $gris);
        $currentY += 3;

        // ════════════════════════════════════════════════════════════════
        // TARJETAS DE RESUMEN
        // ════════════════════════════════════════════════════════════════
        $totalLitros = $detalle->sum(fn ($d) => (float) $d->litros);
        $totalRecorrido = $detalle->sum(fn ($d) => (float) $d->recorrido);
        $rendimientoPromedio = $esHorometro
            ? ($totalRecorrido > 0 ? $totalLitros / $totalRecorrido : 0)
            : ($totalLitros > 0 ? $totalRecorrido / $totalLitros : 0);

        $cardW = 49;
        $cardH = 14;
        $cards = [
            ['CARGAS EN EL RANGO', (string) $detalle->count(), 'Azul'],
            ['TOTAL LITROS', number_format($totalLitros, 2, ',', '.').' L', 'Verde'],
            ['TOTAL '.$etiquetaRecorrido, number_format($totalRecorrido, 2, ',', '.'), 'Rojo'],
            ['RENDIMIENTO PROMEDIO', number_format($rendimientoPromedio, 2, ',', '.').' '.$unidad, 'Gris'],
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
        // TABLA DE CARGAS
        // ════════════════════════════════════════════════════════════════
        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $currentY, $uw, 8, 'FD');

        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY($sx, $currentY);
        $this->Cell($uw, 8, utf8Decode('CARGAS DEL VEHÍCULO'), 0, 1, 'C');

        $currentY += 8;

        $cols = [
            ['label' => 'FECHA DE CARGA', 'w' => 34, 'align' => 'C'],
            ['label' => 'LITROS', 'w' => 26, 'align' => 'R'],
            ['label' => 'MED. ANTERIOR', 'w' => 32, 'align' => 'R'],
            ['label' => 'MED. ACTUAL', 'w' => 32, 'align' => 'R'],
            ['label' => strtoupper($etiquetaRecorrido), 'w' => 32.9, 'align' => 'R'],
            ['label' => 'RENDIMIENTO', 'w' => 43, 'align' => 'R'],
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

        $this->SetFont('Arial', '', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        if ($detalle->isEmpty()) {
            $this->SetDrawColor($gris[0], $gris[1], $gris[2]);
            $this->SetLineWidth(0.1);
            $this->Rect($sx, $currentY, $uw, 7);
            $this->SetXY($sx, $currentY);
            $this->Cell($uw, 7, utf8Decode('No hay cargas con medición anterior disponible en este rango.'), 0, 0, 'C');
            $currentY += 7;
        }

        foreach ($detalle as $fila => $d) {
            $valores = [
                date('d/m/Y H:i', strtotime($d->fecha_carga)),
                number_format((float) $d->litros, 2, ',', '.').' L',
                number_format((float) $d->medicion_anterior, 2, ',', '.'),
                number_format((float) $d->medicion_actual, 2, ',', '.'),
                number_format((float) $d->recorrido, 2, ',', '.'),
                number_format((float) $d->rendimiento, 2, ',', '.').' '.$unidad,
            ];

            // Franjas alternadas por fila: mejora la lectura en tablas largas.
            $this->SetFillColor($filaAlterna[0], $filaAlterna[1], $filaAlterna[2]);
            $conFondo = $fila % 2 === 1;

            $colX = $sx;
            foreach ($cols as $idx => $col) {
                $this->SetDrawColor($gris[0], $gris[1], $gris[2]);
                $this->SetLineWidth(0.1);
                $this->Rect($colX, $currentY, $col['w'], 6, $conFondo ? 'FD' : 'D');
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

        $totales = ['TOTALES', number_format($totalLitros, 2, ',', '.').' L', '', '', number_format($totalRecorrido, 2, ',', '.'), number_format($rendimientoPromedio, 2, ',', '.').' '.$unidad];

        $colX = $sx;
        foreach ($cols as $idx => $col) {
            $this->Rect($colX, $currentY, $col['w'], 7, 'FD');
            $this->SetXY($colX + 1, $currentY);
            $this->Cell($col['w'] - 2, 7, utf8Decode($totales[$idx]), 0, 0, $col['align']);
            $colX += $col['w'];
        }

        $this->pintarPieDePagina($uw, $gris);

        return $this->Output($modo, $nombreArchivo ?? 'detalle_rendimiento_'.$vehiculo->codigo.'.pdf');
    }

    /**
     * Pie de página estándar (fecha de generación + numeración), reutilizado
     * por los reportes de rendimiento. AliasNbPages()+SetY(-12) sólo puede
     * pintarse una vez que ya se conoce la altura final del contenido.
     */
    private function pintarPieDePagina(float $uw, array $gris): void
    {
        // El auto-salto de página (activado para que la tabla pagine si hay
        // muchas filas) dispara una página nueva en cuanto un Cell() cae
        // dentro de los últimos 15mm; el pie va a -12mm del borde inferior,
        // así que hay que apagarlo aquí o el pie termina solo en una página
        // extra en blanco.
        $this->SetAutoPageBreak(false);
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->Cell($uw, 4, utf8Decode('Reporte generado el: '.date('d/m/Y H:i')), 0, 0, 'L');
        $this->Cell($uw, 4, utf8Decode('Página: ').$this->PageNo().'/{nb}', 0, 0, 'R');
        $this->AliasNbPages();
    }

    /**
     * Registro único de configuración de la empresa (nombre, dirección,
     * teléfono, NIT, logo), usado para no dejar estos datos hardcodeados en
     * los reportes. Consulta directa a la base de datos (sin caché: el driver
     * configurado no deserializaba bien el modelo).
     */
    private function parametrosEmpresa(): ?ParametrosEmpresa
    {
        return ParametrosEmpresa::first();
    }

    /**
     * Ruta absoluta al logo a usar en el encabezado: el que esté configurado
     * en Parámetros de la Empresa si existe el archivo, o el logo estático
     * como respaldo (para no romper el reporte si aún no se configuró uno).
     */
    private function logoEmpresa(?ParametrosEmpresa $parametrosEmpresa): string
    {
        $logoRespaldo = public_path('images/logo/logo-plus-metals-azul.png');

        if (! $parametrosEmpresa?->logo_empresa) {
            return $logoRespaldo;
        }

        $logoConfigurado = storage_path('app/public/'.$parametrosEmpresa->logo_empresa);

        return file_exists($logoConfigurado) ? $logoConfigurado : $logoRespaldo;
    }

    /**
     * Franja informativa con los datos de la empresa (nombre, dirección,
     * teléfono, NIT), pintada como una línea centrada justo debajo del
     * encabezado del reporte. Devuelve el alto ocupado (0 si no hay datos).
     */
    private function pintarInfoEmpresa(?ParametrosEmpresa $parametrosEmpresa, float $sx, float $y, float $uw, array $gris): float
    {
        if (! $parametrosEmpresa) {
            return 0;
        }

        $partes = array_filter([
            $parametrosEmpresa->nombre_empresa,
            $parametrosEmpresa->direccion_empresa,
            $parametrosEmpresa->telefono_empresa ? 'Tel. '.$parametrosEmpresa->telefono_empresa : null,
            $parametrosEmpresa->nit_empresa ? 'NIT: '.$parametrosEmpresa->nit_empresa : null,
        ]);

        if (! $partes) {
            return 0;
        }

        $this->SetFont('Arial', '', 7.5);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx, $y);
        $this->Cell($uw, 4, utf8Decode(implode('   |   ', $partes)), 0, 0, 'C');

        return 6;
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
        $k = $this->k;
        $h = $this->h;
        $lx = 4 / 3 * (sqrt(2) - 1) * $r;
        $this->_out(sprintf(
            '%.2F %.2F m '
                .'%.2F %.2F %.2F %.2F %.2F %.2F c '
                .'%.2F %.2F %.2F %.2F %.2F %.2F c '
                .'%.2F %.2F %.2F %.2F %.2F %.2F c '
                .'%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($cx + $r) * $k,
            ($h - $cy) * $k,
            ($cx + $r) * $k,
            ($h - $cy + $lx) * $k,
            ($cx + $lx) * $k,
            ($h - $cy + $r) * $k,
            $cx * $k,
            ($h - $cy + $r) * $k,
            ($cx - $lx) * $k,
            ($h - $cy + $r) * $k,
            ($cx - $r) * $k,
            ($h - $cy + $lx) * $k,
            ($cx - $r) * $k,
            ($h - $cy) * $k,
            ($cx - $r) * $k,
            ($h - $cy - $lx) * $k,
            ($cx - $lx) * $k,
            ($h - $cy - $r) * $k,
            $cx * $k,
            ($h - $cy - $r) * $k,
            ($cx + $lx) * $k,
            ($h - $cy - $r) * $k,
            ($cx + $r) * $k,
            ($h - $cy - $lx) * $k,
            ($cx + $r) * $k,
            ($h - $cy) * $k,
            $op
        ));
    }

    public function generarReporteOperacionDiaria($datos = null)
    {
        // ── Datos estáticos de ejemplo ──────────────────────────────────────
        $nroReporte = '01576';
        $operador = 'JOSÉ GARCÍA MORALES';
        $horometroInicial = '45000';
        $horometroFinal = '45150';
        $descripcionEquipo = 'EXCAVADORA CAT 320 D';
        $totalHorasTrabajo = '8.5 HRS';
        $dia = '15';
        $mes = '08';
        $anio = '2026';
        $tipoJornada = 'DÍA'; // 'DÍA' o 'NOCHE'

        // Datos de mantenimiento realizado
        $diesel = 'HO 0KM';
        $aceitesMotor = 'HO 0KM';
        $aceiteTransm = 'HO 0KM';
        $aceiteHidraul = 'HO 0KM';
        $grasa = 'HO 0KM';
        $sopleteFiltro = 'NO'; // 'SI' o 'NO'
        $observaciones = 'Operación normal. Equipo en excelente estado operativo.';

        // Datos de actividades (tabla de ejemplo)
        $actividades = [
            ['DE' => '06:00', 'A' => '07:30', 'ACTIVIDAD' => 'Inspección pre-operacional'],
            ['DE' => '07:30', 'A' => '11:00', 'ACTIVIDAD' => 'Excavación zona norte'],
            ['DE' => '11:00', 'A' => '12:00', 'ACTIVIDAD' => 'Descanso'],
            ['DE' => '12:00', 'A' => '15:30', 'ACTIVIDAD' => 'Excavación zona sur'],
            ['DE' => '15:30', 'A' => '16:30', 'ACTIVIDAD' => 'Mantenimiento preventivo'],
            ['DE' => '16:30', 'A' => '17:00', 'ACTIVIDAD' => 'Inspección post-operacional'],
        ];

        // ── Colores consistentes ────────────────────────────────────────────
        $azul = [39, 42, 84];
        $rojo = [190, 30, 30];
        $negro = [30, 30, 30];
        $gris = [90, 90, 90];
        $blanco = [255, 255, 255];

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(false);

        $sx = 8;      // origen X
        $sy = 8;      // origen Y
        $uw = 199.9;  // ancho útil (215.9 - 16)
        $sBottom = 271.4; // límite inferior útil

        // ══════════════════════════════════════════════════════════════════
        // ENCABEZADO: 3 secciones [LOGO | TÍTULO + CHECKBOXES | N° + FECHA]
        // ══════════════════════════════════════════════════════════════════
        $h1 = 24;    // altura del bloque encabezado
        $logoW = 42;    // ancho sección logo
        $nroW = 50;    // ancho sección derecha
        $midW = $uw - $logoW - $nroW; // ancho sección central

        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.4);

        // Borde exterior del encabezado
        $this->Rect($sx, $sy, $uw, $h1);

        // Divisores verticales internos
        $this->SetLineWidth(0.3);
        $this->Line($sx + $logoW, $sy, $sx + $logoW, $sy + $h1);
        $this->Line($sx + $logoW + $midW, $sy, $sx + $logoW + $midW, $sy + $h1);

        // — Sección izquierda: logo + nombre empresa —
        $this->Image(public_path('images/logo/logo-plus-metals-azul.png'), $sx + 1, $sy + 1, 26);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx, $sy + 14);
        $this->Cell($logoW, 4, utf8Decode('PLUS METALS LTDA.'), 0, 0, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetXY($sx, $sy + 18);
        $this->Cell($logoW, 4, utf8Decode('ORURO - BOLIVIA'), 0, 0, 'C');

        // — Sección central: título + checkboxes DÍA / NOCHE —
        $midX = $sx + $logoW;
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($midX, $sy + 2);
        $this->Cell($midW, 8, utf8Decode('REPORTE DE OPERACION DIARIA'), 0, 0, 'C');

        // Checkboxes centrados horizontalmente
        $chkY = $sy + 13;
        $chkSz = 4;
        $chkMid = $midX + $midW / 2;

        // Checkbox DIA (izquierda del centro)
        $chkDiaX = $chkMid - 22;
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($chkDiaX, $chkY, $chkSz, $chkSz);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($chkDiaX + $chkSz + 1, $chkY);
        $this->Cell(10, $chkSz, utf8Decode('DIA'), 0, 0, 'L');

        // Checkbox NOCHE (derecha del centro)
        $chkNocheX = $chkMid + 2;
        $this->Rect($chkNocheX, $chkY, $chkSz, $chkSz);
        $this->SetXY($chkNocheX + $chkSz + 1, $chkY);
        $this->Cell(14, $chkSz, utf8Decode('NOCHE'), 0, 0, 'L');

        // Marcar el checkbox según $tipoJornada
        if ($tipoJornada === 'DÍA') {
            $this->SetFont('Arial', 'B', 8);
            $this->SetXY($chkDiaX, $chkY - 0.5);
            $this->Cell($chkSz, $chkSz + 1, 'X', 0, 0, 'C');
        } else {
            $this->SetFont('Arial', 'B', 8);
            $this->SetXY($chkNocheX, $chkY - 0.5);
            $this->Cell($chkSz, $chkSz + 1, 'X', 0, 0, 'C');
        }

        // — Sección derecha: N° reporte + tabla DÍA/MES/AÑO —
        $rx = $sx + $logoW + $midW;
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor($rojo[0], $rojo[1], $rojo[2]);
        $this->SetXY($rx, $sy + 1);
        $this->Cell($nroW, 7, utf8Decode('N° '.$nroReporte), 0, 0, 'C');

        // Tabla de fecha (3 columnas iguales)
        $dtColW = $nroW / 3;
        $dtY = $sy + 10;

        $dtCols = ['DIA', 'MES', utf8Decode('AÑO')];
        $dtVals = [$dia, $mes, $anio];

        foreach ($dtCols as $i => $label) {
            $cx = $rx + $i * $dtColW;
            $this->SetFillColor($azul[0], $azul[1], $azul[2]);
            $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
            $this->SetLineWidth(0.25);
            $this->Rect($cx, $dtY, $dtColW, 5, 'FD');
            $this->SetFont('Arial', 'B', 7);
            $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
            $this->SetXY($cx, $dtY);
            $this->Cell($dtColW, 5, $label, 0, 0, 'C');
            // Valor
            $this->Rect($cx, $dtY + 5, $dtColW, 5);
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($cx, $dtY + 5);
            $this->Cell($dtColW, 5, $dtVals[$i], 0, 0, 'C');
        }

        // ══════════════════════════════════════════════════════════════════
        // CAMPOS DE DATOS PRINCIPALES
        // ══════════════════════════════════════════════════════════════════
        $fieldY = $sy + $h1 + 4;
        $fieldH = 4;
        $labelW = 45;
        $this->SetLineWidth(0.2);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);

        // OPERADOR
        $this->SetXY($sx, $fieldY);
        $this->Cell($labelW, $fieldH, utf8Decode('OPERADOR:'), 0, 0, 'L');
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Line($sx + $labelW, $fieldY + $fieldH - 0.5, $sx + $uw / 2, $fieldY + $fieldH - 0.5);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($sx + $labelW + 2, $fieldY);
        $this->Cell($uw / 2 - $labelW - 2, $fieldH, utf8Decode($operador), 0, 0, 'L');

        // DESCRIPCIÓN EQUIPO (lado derecho)
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + $uw / 2, $fieldY);
        $this->Cell(35, $fieldH, utf8Decode('DESCRIPCION EQUIPO:'), 0, 0, 'L');
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Line($sx + $uw / 2 + 35, $fieldY + $fieldH - 0.5, $sx + $uw, $fieldY + $fieldH - 0.5);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($sx + $uw / 2 + 35 + 2, $fieldY);
        $this->Cell($uw / 2 - 35 - 2, $fieldH, utf8Decode($descripcionEquipo), 0, 0, 'L');

        // HORÓMETRO 0 KM INICIAL
        $fieldY += 6;
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx, $fieldY);
        $this->Cell($labelW, $fieldH, utf8Decode('HOROMETRO 0 KM INICIAL:'), 0, 0, 'L');
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Line($sx + $labelW, $fieldY + $fieldH - 0.5, $sx + $uw / 2, $fieldY + $fieldH - 0.5);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($sx + $labelW + 2, $fieldY);
        $this->Cell($uw / 2 - $labelW - 2, $fieldH, utf8Decode($horometroInicial), 0, 0, 'L');

        // TOTAL HORAS TRABAJO (lado derecho)
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + $uw / 2, $fieldY);
        $this->Cell(30, $fieldH, utf8Decode('TOTAL HORAS TRABAJO:'), 0, 0, 'L');
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Line($sx + $uw / 2 + 30, $fieldY + $fieldH - 0.5, $sx + $uw, $fieldY + $fieldH - 0.5);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($sx + $uw / 2 + 30 + 2, $fieldY);
        $this->Cell($uw / 2 - 30 - 2, $fieldH, utf8Decode($totalHorasTrabajo), 0, 0, 'L');

        // HORÓMETRO 0 KM FINAL
        $fieldY += 6;
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx, $fieldY);
        $this->Cell($labelW, $fieldH, utf8Decode('HOROMETRO 0 KM FINAL:'), 0, 0, 'L');
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Line($sx + $labelW, $fieldY + $fieldH - 0.5, $sx + $uw / 2, $fieldY + $fieldH - 0.5);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($sx + $labelW + 2, $fieldY);
        $this->Cell($uw / 2 - $labelW - 2, $fieldH, utf8Decode($horometroFinal), 0, 0, 'L');

        // ══════════════════════════════════════════════════════════════════
        // TABLA - DETALLE JORNADA DIARIA DE TRABAJO
        // ══════════════════════════════════════════════════════════════════
        $tblY = $fieldY + 8;
        $tblH = 60;

        // Encabezado de tabla
        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $tblY, $uw, 6, 'FD');

        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);

        $this->SetXY($sx, $tblY);
        $this->Cell(15, 6, utf8Decode('H O R A'), 0, 0, 'C');
        $this->SetXY($sx + 15, $tblY);
        $this->Cell($uw - 15, 6, utf8Decode('DETALLE JORNADA DIARIA DE TRABAJO'), 0, 0, 'C');

        // Subtítulos
        $this->SetXY($sx, $tblY + 6);
        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $tblY + 6, $uw, 4, 'FD');
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);

        $this->Cell(7.5, 4, utf8Decode('DE'), 0, 0, 'C');
        $this->SetXY($sx + 7.5, $tblY + 6);
        $this->Cell(7.5, 4, utf8Decode('A'), 0, 0, 'C');
        $this->SetXY($sx + 15, $tblY + 6);
        $this->Cell($uw - 15, 4, utf8Decode('A C T I V I D A D'), 0, 0, 'C');

        // Filas de datos
        $this->SetLineWidth(0.2);
        $rowH = 8;
        $rowsCount = 8;
        $dataRowY = $tblY + 10;

        for ($i = 0; $i < $rowsCount; $i++) {
            $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
            $this->Rect($sx, $dataRowY, 7.5, $rowH);
            $this->Rect($sx + 7.5, $dataRowY, 7.5, $rowH);
            $this->Rect($sx + 15, $dataRowY, $uw - 15, $rowH);

            if (isset($actividades[$i])) {
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor($negro[0], $negro[1], $negro[2]);

                // Hora DE
                $this->SetXY($sx, $dataRowY + 1);
                $this->Cell(7.5, $rowH - 2, $actividades[$i]['DE'], 0, 0, 'C');

                // Hora A
                $this->SetXY($sx + 7.5, $dataRowY + 1);
                $this->Cell(7.5, $rowH - 2, $actividades[$i]['A'], 0, 0, 'C');

                // Actividad
                $this->SetXY($sx + 15 + 2, $dataRowY + 1);
                $this->Cell($uw - 15 - 4, $rowH - 2, utf8Decode($actividades[$i]['ACTIVIDAD']), 0, 0, 'L');
            }

            $dataRowY += $rowH;
        }

        // ══════════════════════════════════════════════════════════════════
        // MANTENIMIENTO REALIZADO
        // ══════════════════════════════════════════════════════════════════
        $mantY = $dataRowY + 4;

        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx + 20, $mantY, $uw - 40, 8, 'FD');

        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
        $this->SetXY($sx + 20, $mantY);
        $this->Cell($uw - 40, 8, utf8Decode('M A N T E N I M I E N T O  R E A L I Z A D O'), 0, 0, 'C');

        // Ítems de mantenimiento
        $mantItems = [
            ['DIESEL:', $diesel],
            ['ACEITES MOTOR:', $aceitesMotor],
            ['ACEITE TRANSM.:', $aceiteTransm],
            ['ACEITE HIDRAULICO', $aceiteHidraul],
            ['GRASA:', $grasa],
        ];

        $itemY = $mantY + 8;
        $itemH = 6;
        $this->SetLineWidth(0.25);

        foreach ($mantItems as $item) {
            $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
            $this->Rect($sx + 20, $itemY, $uw - 40, $itemH);

            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor($azul[0], $azul[1], $azul[2]);
            $this->SetXY($sx + 20 + 2, $itemY + 1);
            $this->Cell(45, $itemH - 2, utf8Decode($item[0]), 0, 0, 'L');

            $colW = ($uw - 40 - 47) / 2;

            $this->SetFont('Arial', '', 8);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($sx + 20 + 47 + 10, $itemY + 1);
            $this->Cell($colW, $itemH - 2, utf8Decode($item[1]), 0, 0, 'L');

            $itemY += $itemH;
        }

        // ══════════════════════════════════════════════════════════════════
        // SOPLETE DE FILTROS
        // ══════════════════════════════════════════════════════════════════
        $sopY = $itemY + 2;

        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx, $sopY);
        $this->Cell(35, 4, utf8Decode('SOPLETE DE FILTROS:'), 0, 0, 'L');

        // Checkbox SI
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx + 36, $sopY, 3.5, 3.5);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($sx + 40, $sopY);
        $this->Cell(10, 4, utf8Decode('SI'), 0, 0, 'L');

        // Checkbox NO
        $this->Rect($sx + 52, $sopY, 3.5, 3.5);
        $this->SetXY($sx + 56, $sopY);
        $this->Cell(10, 4, utf8Decode('NO'), 0, 0, 'L');

        // ══════════════════════════════════════════════════════════════════
        // OBSERVACIONES
        // ══════════════════════════════════════════════════════════════════
        $obsY = $sopY + 6;

        $this->SetFillColor($azul[0], $azul[1], $azul[2]);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $obsY, $uw, 6, 'FD');

        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
        $this->SetXY($sx, $obsY);
        $this->Cell($uw, 6, utf8Decode('OBSERVACIONES:'), 0, 0, 'L');

        // Área de observaciones con líneas punteadas
        $obsHeight = 28;
        $this->SetLineWidth(0.2);
        $this->Rect($sx, $obsY + 6, $uw, $obsHeight);

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($sx + 2, $obsY + 7);

        // Dividir observaciones en líneas
        $maxWidth = $uw - 4;
        $lineHeight = 4;
        $obsLines = explode("\n", wordwrap($observaciones, 100, "\n"));

        foreach ($obsLines as $line) {
            $this->SetXY($sx + 2, $this->GetY());
            $this->Cell($maxWidth, $lineHeight, utf8Decode($line), 0, 1, 'L');
        }

        // ══════════════════════════════════════════════════════════════════
        // FIRMAS
        // ══════════════════════════════════════════════════════════════════
        $firmaY = $obsY + $obsHeight + 10;
        $firmaW = ($uw / 2) - 5;

        // Línea punteada OPERADOR
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 2, $firmaY);

        // Línea punteada
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.2);
        $this->Line($sx + 2, $firmaY, $sx + 2 + $firmaW - 2, $firmaY);

        $this->SetXY($sx + 2, $firmaY + 2);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($firmaW, 3, utf8Decode('OPERADOR'), 0, 0, 'C');

        // Línea punteada SUPERVISOR
        $this->SetXY($sx + $uw / 2 + 3, $firmaY);
        $this->SetLineWidth(0.2);
        $this->Line($sx + $uw / 2 + 3, $firmaY, $sx + $uw - 2, $firmaY);

        $this->SetXY($sx + $uw / 2 + 3, $firmaY + 2);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($firmaW - 3, 3, utf8Decode('SUPERVISOR'), 0, 0, 'C');

        // Salida del PDF
        $this->Output('I', 'reporte_operacion_diaria.pdf');
    }
}

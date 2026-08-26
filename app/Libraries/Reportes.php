<?php

namespace App\Libraries;

use App\Models\CargaCombustible;
use App\Models\ParametrosEmpresa;
use easyTable;
use exFPDF;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class Reportes extends exFPDF
{
    /**
     * @param  string  $modo  Destino de salida de FPDF: 'I' (mostrar inline en el navegador,
     *                        usado por la vista web), 'D' (forzar descarga en el navegador) o
     *                        'S' (devolver el PDF como string, usado por la API para que el
     *                        controlador arme la respuesta HTTP con sus propios encabezados).
     */
    public function generarVale($vale, string $modo = 'I', ?string $nombreArchivo = null)
    {
        // ==========================================================
        // DATOS
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
        $autorizadoPor = $vale->user?->name;
        $tipoCombustible = $vale->tipoCombustible->tipo_combustible;

        // Colores (el azul coincide con el del fondo, aprox. rgb(0,85,155))
        $azul = [1, 82, 145];
        $rojo = [203, 39, 45];
        $negro = [30, 30, 30];
        $gris = [90, 90, 90];
        $blanco = [255, 255, 255];

        $pageW = 215.9;
        $pageH = 279.4;

        $this->AddPage('P', 'Letter');
        $this->SetMargins(5, 5, 5);
        $this->SetAutoPageBreak(false);

        // ----------------------------------------------------------
        // FONDO (diseño completo tamaño carta: cuadros, líneas e íconos
        // ya vienen impresos en la imagen; aquí sólo se ubica el texto)
        // ----------------------------------------------------------
        $this->Image(public_path('images/reportes/vale-fondo-carta.png'), 0, 0, $pageW, $pageH);

        // ----------------------------------------------------------
        // ENCABEZADO: logo, título "VALE" + correlativo, datos del grifo
        // ----------------------------------------------------------
        $this->Image(public_path('images/logo/logo-min.png'), 8, 10, 40, 20);

        $this->SetXY(52, 10);
        $this->SetFont('Arial', 'BI', 40);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->Cell(45, 14, utf8Decode('VALE'), 0, 0, 'L');

        $this->SetXY(53, 24);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor($rojo[0], $rojo[1], $rojo[2]);
        $this->Cell(45, 6, utf8Decode('N°'.$numeroVale), 0, 0, 'L');

        // Datos del grifo (alineados a la derecha, arriba). El ancho se
        // detiene antes del ícono del surtidor impreso en el fondo (~170mm).
        $this->SetXY(112, 10);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->Cell(80, 5, utf8Decode($empresa), 0, 2, 'R');

        $this->SetX(112);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->Cell(80, 4.5, utf8Decode($direccion), 0, 2, 'R');
        $this->SetX(112);
        $this->Cell(80, 4.5, utf8Decode($telefono), 0, 2, 'R');
        $this->SetX(112);
        $this->Cell(80, 4.5, utf8Decode($ciudad), 0, 2, 'R');

        // Nombre de la empresa local, sobre la franja azul del encabezado
        // (mismo límite de ancho que los datos del grifo, antes del ícono)
        $this->SetXY(130, 33);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
        $this->Cell(80, 6, utf8Decode(mb_strtoupper($empresaLocal)), 0, 0, 'R');

        // ----------------------------------------------------------
        // CUADRO IZQUIERDO: datos del cliente (etiquetas ya impresas en
        // el fondo; cada valor se ubica sobre su línea correspondiente)
        // ----------------------------------------------------------
        $valX = 40;
        $valW = 100 - $valX;

        $filas = [
            [73, $cliente],
            [93, $licencia],
            [115, $vehiculo],
            [138, $marca],
            [160, $placa],
        ];

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        foreach ($filas as [$y, $valor]) {
            $this->SetXY($valX, $y);
            $this->Cell($valW, 5, utf8Decode($valor), 0, 0, 'L');
        }

        // ----------------------------------------------------------
        // CUADRO DERECHO: litros / importe / combustible / autorizado
        // ----------------------------------------------------------
        // Lt. / Bs. (números grandes, centrados en cada mitad del cuadro)
        $this->SetFont('Arial', 'B', 22);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY(109.6, 60);
        $this->Cell(49.9, 14, $litros, 0, 0, 'C');
        $this->SetXY(159.5, 60);
        $this->Cell(47.8, 14, $bolivianos, 0, 0, 'C');

        // Montos en letras
        $this->SetFont('Arial', '', 9);
        $this->SetXY(109.6, 92.5);
        $this->Cell(97.7, 4.5, utf8Decode(numeroLiteral($litros, 'LITROS')), 0, 0, 'C');

        $this->SetXY(109.6, 104);
        $this->Cell(97.7, 4.5, utf8Decode(monedaLiteral($bolivianos)), 0, 0, 'C');

        // Tipo de combustible / Autorizado por (entre la etiqueta y su línea)
        $this->SetXY(150, 118);
        $this->Cell(93, 4, utf8Decode($tipoCombustible), 0, 0, 'L');

        $this->SetXY(148, 132);
        $this->Cell(93, 4, utf8Decode($autorizadoPor), 0, 0, 'L');

        // QR de verificación
        $urlQr = route('vales.publico', ['hash' => md5($vale->id)]);
        $base64Qr = 'data:image/png;base64,'.$this->getBase64Qr($urlQr);
        $this->Image($base64Qr, 175, 145, 26, 29, 'PNG');

        // ----------------------------------------------------------
        // PIE: franja de "uso único" con fechas de emisión y vencimiento
        // ----------------------------------------------------------
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
        $this->SetXY(106, 188);
        $this->Cell(28, 6, utf8Decode($vale->fecha_emision?->format('d/m/Y H:i')), 0, 0, 'L');

        $this->SetXY(170, 188);
        $this->Cell(29, 6, utf8Decode($vale->fecha_vencimiento?->format('d/m/Y H:i')), 0, 0, 'L');

        // agregar la fecha de impresión del PDF en el pie de página
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY(5, 268);
        $this->Cell(203, 4, utf8Decode('Fecha de impresión: '.now()->format('d/m/Y H:i')), 0, 0, 'R');

        // Salida del PDF
        // El '/' de $numeroVale ("NNNNNN/GESTION") no es válido dentro de un nombre
        // de archivo, así que se reemplaza por '-' sólo para el nombre sugerido.
        return $this->Output($modo, $nombreArchivo ?? 'vale_'.str_replace('/', '-', (string) $numeroVale).'.pdf');
    }

    /**
     * Comprobante de egreso de combustible: se emite después de registrar una
     * carga de combustible (módulo Cargas de Combustible). Usa el mismo
     * esquema que generarVale() — el fondo trae impresos los cuadros,
     * etiquetas, líneas e íconos; aquí sólo se ubica el texto dinámico.
     *
     * @param  string  $modo  Ver docblock de generarVale().
     */
    public function generarComprobanteEgreso($carga, string $modo = 'I', ?string $nombreArchivo = null)
    {
        // ==========================================================
        // DATOS
        // ==========================================================
        $numeroComprobante = $carga->nro; // Correlativo formateado (nro_carga/gestion)
        $fecha = $carga->fecha_carga?->format('d/m/Y');

        $vehiculo = $carga->vehiculo;
        // Área asignada al vehículo (si tiene) y su encargado activo, para
        // dejar constancia de a qué área/jefe corresponde el gasto.
        $area = $vehiculo?->areasAsignadas()->first();
        $encargadoArea = $area?->encargadosActivos()->first();

        $areaNombre = $area?->nombre_area ?? 'N/A';
        $encargadoNombre = $encargadoArea?->nombre_completo ?? 'N/A';
        $automovil = trim(($vehiculo?->tipoVehiculo?->tipo_vehiculo ?? '').' '.($vehiculo?->nro_placa ?? ''));
        $conductorNombre = $carga->conductor?->persona?->nombre_completo ?? 'N/A';
        $concepto = $carga->concepto ?: 'N/A';

        $tipoCombustible = $carga->tipoCombustible->tipo_combustible;
        $litros = $carga->litros;
        $precioUnitario = $carga->precio;
        $total = round($litros * $precioUnitario, 2);

        // Colores (consistentes con generarVale)
        $azul = [1, 82, 145];
        $negro = [30, 30, 30];
        $blanco = [255, 255, 255];

        $pageW = 215.9;
        $pageH = 279.4;

        $this->AddPage('P', 'Letter');
        $this->SetMargins(5, 5, 5);
        $this->SetAutoPageBreak(false);

        // ----------------------------------------------------------
        // FONDO (diseño completo tamaño carta: cuadros, tabla, líneas e
        // íconos ya vienen impresos en la imagen; aquí sólo se ubica el
        // texto).
        // ----------------------------------------------------------
        $this->Image(public_path('images/reportes/fondo-comprobante-egreso.png'), 0, 0, $pageW, $pageH);

        // ----------------------------------------------------------
        // ENCABEZADO: logo, título, correlativo y fecha
        // ----------------------------------------------------------
        $this->Image(public_path('images/logo/logo-min.png'), 8, 8, 40);

        $this->SetXY(50, 8);
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->Cell(105, 9, utf8Decode('COMPROBANTE DE EGRESO'), 0, 2, 'C');
        $this->SetX(50);
        $this->Cell(105, 9, utf8Decode('DE COMBUSTIBLE'), 0, 2, 'C');

        // Nro. de comprobante, sobre el recuadro azul relleno del encabezado
        $this->SetXY(158, 13.2);
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
        $this->Cell(29, 6, utf8Decode($numeroComprobante), 0, 0, 'C');

        // Fecha, debajo de la etiqueta "FECHA:" impresa en el fondo (a la
        // derecha, sobre el ícono del surtidor, no hay espacio suficiente)
        $this->SetXY(165, 26);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->Cell(34, 5, utf8Decode($fecha), 0, 0, 'L');

        // ----------------------------------------------------------
        // CUADRO SUPERIOR: área, encargado, automóvil, conductor y
        // concepto (etiquetas ya impresas en el fondo; cada valor va
        // sobre su línea correspondiente).
        // ----------------------------------------------------------
        $valX = 55;
        $valW = 208.73 - 4 - $valX;

        $filas = [
            [50, 4, $areaNombre],
            [64, 3.5, $encargadoNombre],
            [77, 4, $automovil],
            [88, 4, $conductorNombre],
            [100, 4.5, $concepto],
        ];

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        foreach ($filas as [$y, $h, $valor]) {
            $this->SetXY($valX, $y);
            $this->Cell($valW, $h, utf8Decode(mb_strtoupper($valor)), 0, 0, 'L');
        }

        // ----------------------------------------------------------
        // TABLA: única línea de concepto (la carga de combustible
        // registrada). CargaCombustible sólo admite un tipo de
        // combustible por registro, así que siempre es una sola fila.
        // ----------------------------------------------------------
        $this->SetFont('Arial', 'B', 10);
        $this->SetXY(10.5, 130);
        $this->Cell(100, 6, utf8Decode(mb_strtoupper($tipoCombustible)), 0, 0, 'L');

        $this->SetFont('Arial', '', 9);
        $this->SetXY(117.48, 130);
        $this->Cell(30.1, 6, number_format($litros, 2, ',', '.').' LT', 0, 0, 'C');

        $this->SetXY(147.58, 130);
        $this->Cell(29.98, 6, number_format($precioUnitario, 2, ',', '.'), 0, 0, 'C');

        $this->SetXY(177.56, 130);
        $this->Cell(31.14, 6, 'Bs '.number_format($total, 2, ',', '.'), 0, 0, 'C');

        // Total estimado: la franja "TOTAL ESTIMADO" sólo trae relleno azul
        // del lado de la etiqueta; el lado del valor queda en blanco, así
        // que el monto va en color oscuro, no blanco.
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY(80, 205);
        $this->Cell(125, 7, 'Bs '.number_format($total, 2, ',', '.'), 0, 0, 'R');

        // Salida del PDF
        // El '/' de $numeroComprobante ("NNNNNN/GESTION") no es válido dentro
        // de un nombre de archivo, así que se reemplaza por '-' sólo para el
        // nombre sugerido.
        return $this->Output($modo, $nombreArchivo ?? 'comprobante_egreso_'.str_replace('/', '-', (string) $numeroComprobante).'.pdf');
    }

    public function getBase64Qr($text, $size = 400, $format = 'png', $qualy = 'Q', $logoPath = '', $margin = 0)
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
     * Formulario "SOLICITUD DE MANTENIMIENTO EQUIPO": se dibuja sobre el fondo
     * completo tamaño carta public/images/reportes/fondo-solicitud-mantenimiento.png
     * (mismo enfoque que generarComprobanteEgreso()/generarVale() — el fondo trae
     * impresas todas las cajas, etiquetas y líneas; aquí sólo se ubica el texto
     * dinámico). La única sección que el fondo NO trae impresa es "TRABAJOS
     * REALIZADOS": ese bloque (título + tabla) se dibuja íntegramente aquí con
     * fpdf-easytable, usando el hueco en blanco que deja el fondo entre la caja de
     * "DESCRIPCIÓN DE LA FALLA DEL EQUIPO" y la de "OBSERVACIONES".
     *
     * Las coordenadas de cada elemento se midieron sobre un render real del fondo
     * a 215.9x279.4mm (no a simple vista sobre el PNG de origen, que tiene un
     * aspect-ratio ligeramente distinto) — ver la nota en
     * .ai/rules/libraries-http-controllers.md sobre este mismo enfoque para
     * fondo-comprobante-egreso.png.
     *
     * @param  string  $modo  Ver docblock de generarVale().
     */
    public function generarSolicitudMantenimiento($solicitud, string $modo = 'I', ?string $nombreArchivo = null)
    {
        // ══════════════════════════════════════════════════════════════════
        // DATOS
        // ══════════════════════════════════════════════════════════════════
        $nroSolicitud = $solicitud?->nro;
        $fechaSolicitud = $solicitud->fecha_solicitud?->format('d/m/Y H:i');
        $solicitante = $solicitud->persona?->nombre_completo;

        $vehiculo = $solicitud->vehiculo;
        $maquinaria = trim(($vehiculo?->codigo ?? '').' '.($vehiculo?->marca ?? ''));
        $modelo = $vehiculo?->anio;
        $placa = $vehiculo?->nro_placa;
        $codigoVehiculo = $vehiculo?->codigo;
        // El horómetro/kilometraje por ítem del detalle a mostrar en la tabla
        // depende del tipo de medición del vehículo (mismo criterio que
        // OrdenTrabajoController/DetalleMantenimientoRequest).
        $tipoMedicion = $vehiculo?->tipo_medicion;

        $tipoMant = $solicitud->tipo_mantenimiento === 'PREVENTIVO' ? 'A' : 'B'; // 'A' = PREVENTIVO, 'B' = CORRECTIVO
        $descripcion = $solicitud->descripcion_problema;
        $observaciones = $solicitud->observacion;

        // Los trabajos realizados sólo existen si la solicitud ya derivó en una
        // orden de trabajo (Paso 2/3 del flujo) con su detalle de repuestos/
        // insumos cargado. Si no hay orden_trabajo relacionada, o aún no tiene
        // detalle, la tabla se dibuja vacía (sólo encabezado + filas en blanco).
        $ordenTrabajo = $solicitud->ordenTrabajo;
        $ejecutor = $ordenTrabajo?->usuarioEjecuta?->name;
        $vistoBueno = $ordenTrabajo?->usuarioEmite?->name;

        // La sección tiene alto fijo (formulario de una sola página): con las
        // columnas/alto de fila usados más abajo entran 10 filas de datos bajo
        // el encabezado.
        $maxFilas = 10;

        $trabajos = $ordenTrabajo
            ? $ordenTrabajo->detalles->take($maxFilas)->map(fn ($detalle) => [
                'fecha' => $detalle->fecha?->format('d/m/Y') ?? '',
                'lectura' => $this->formatearLectura($tipoMedicion === 'kilometraje' ? $detalle->kilometraje : $detalle->horometro),
                'repuesto' => $detalle->repuesto?->nombre_repuesto ?? 'Mano de obra',
                'codigo' => $detalle->repuesto?->codigo_repuesto ?? '',
                'cantidad' => (string) $detalle->cantidad,
            ])->all()
            : [];

        // ══════════════════════════════════════════════════════════════════
        // COLORES
        // ══════════════════════════════════════════════════════════════════
        $azul = [0, 75, 145]; // mismo azul pedido para la tabla de "TRABAJOS REALIZADOS"
        $negro = [30, 30, 30];

        $pageW = 215.9;
        $pageH = 279.4;

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(false);

        // ----------------------------------------------------------
        // FONDO (formulario completo tamaño carta: cajas, etiquetas y líneas
        // ya vienen impresas en la imagen; aquí sólo se ubica el texto).
        // ----------------------------------------------------------
        $this->Image(public_path('images/reportes/fondo-solicitud-mantenimiento.png'), 0, 0, $pageW, $pageH);

        // ══════════════════════════════════════════════════════════════════
        // ENCABEZADO: logo (zona x 2.29-48.98) y N° de solicitud (zona x 170.98-212.85)
        // ══════════════════════════════════════════════════════════════════
        $this->Image(public_path('images/logo/logo-plus-metals-azul.png'), 6, 6, 40);

        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY(172, 9);
        $this->Cell(39, 9, utf8Decode('N° '.$nroSolicitud), 0, 0, 'R');

        // ══════════════════════════════════════════════════════════════════
        // DATOS DEL SOLICITANTE/EQUIPO (caja izquierda, x 4.53-113.79, filas de
        // ~9.1mm entre y=29.04 e y=74.51) + FECHA DE SOLICITUD (caja derecha
        // superior, x 117.43-210.10, y 29.04-40.00)
        // ══════════════════════════════════════════════════════════════════
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        $filasIzq = [
            [29.9, $solicitante],
            [39.0, $maquinaria],
            [48.1, $modelo],
            [57.2, $placa],
            [66.3, $codigoVehiculo],
        ];
        foreach ($filasIzq as [$y, $valor]) {
            $this->SetXY(55, $y);
            $this->Cell(56, 6, utf8Decode((string) ($valor ?? '')), 0, 0, 'L');
        }

        $this->SetXY(168, 30.9);
        $this->Cell(40, 6, utf8Decode((string) $fechaSolicitud), 0, 0, 'L');

        // -- Tipo de mantenimiento: casilla marcada con una "X" (caja derecha
        // inferior, checkboxes ya impresos en el fondo en (149.78-155.96,
        // 60.41-66.08) para "A" y (195.41-201.34, 60.41-66.08) para "B") --
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $cx = $tipoMant === 'A' ? 149.78 : 195.41;
        $this->SetXY($cx, 60.9);
        $this->Cell(6.2, 5.2, 'X', 0, 0, 'C');

        // ══════════════════════════════════════════════════════════════════
        // DESCRIPCIÓN DE LA FALLA DEL EQUIPO (caja punteada x 6.5-209.4, y 85.30-123.32)
        // ══════════════════════════════════════════════════════════════════
        if ($descripcion) {
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY(8, 87.5);
            $this->MultiCell(200, 5, utf8Decode($descripcion), 0, 'L');
        }

        // ══════════════════════════════════════════════════════════════════
        // TRABAJOS REALIZADOS: única sección que el fondo NO trae impresa —
        // ocupa el hueco en blanco entre la caja de DESCRIPCIÓN (termina en
        // y=125.48) y la de OBSERVACIONES (empieza en y=211.29), con un margen
        // de ~3mm arriba y abajo para no tocar ninguna de las dos.
        // ══════════════════════════════════════════════════════════════════
        $s4X = 2.29;
        $s4W = 210.56; // 212.85 - 2.29, mismo ancho que las demás cajas del fondo
        $s4Y = 128.5;
        // Bottom disponible: 208.3 (211.29 de OBSERVACIONES - 3mm de margen). Con
        // el título (8mm) + $maxFilas=10 filas de 6.5mm de alto (65mm) la tabla
        // termina en 128.5 + 8 + 65 = 201.5, dentro de ese límite.

        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($s4X, $s4Y, $s4W, 8);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($s4X, $s4Y);
        $this->Cell($s4W, 8, utf8Decode('TRABAJOS REALIZADOS'), 0, 0, 'C');

        $etiquetaLectura = $tipoMedicion === 'kilometraje' ? 'KILOMETRAJE' : 'HOROMETRO';
        $anchos = [26, 27.2, 61.8, 66.3, 29.26]; // suma = $s4W (210.56)
        $encabezados = ['FECHA', $etiquetaLectura, 'REPUESTO UTILIZADO', 'CODIGO O NRO. DE REPUESTO', 'CANTIDAD'];

        $this->SetXY($s4X, $s4Y + 8);
        $tabla = new easyTable($this, '{'.implode(',', $anchos).'}', "width:{$s4W}; border:1; border-color:{$azul[0]},{$azul[1]},{$azul[2]}; border-width:0.25; font-family:Arial; valign:M; paddingX:1.5; min-height:6.5;");

        $tabla->rowStyle("bgcolor:{$azul[0]},{$azul[1]},{$azul[2]}; font-color:255,255,255; font-style:B; font-size:7.5; align:C;");
        foreach ($encabezados as $encabezado) {
            $tabla->easyCell(utf8Decode($encabezado));
        }
        $tabla->printRow(true);

        // Filas de datos reales, seguidas de filas en blanco hasta completar
        // $maxFilas: la tabla siempre ocupa todo el espacio disponible, tenga
        // o no tenga (todavía) el detalle de trabajo cargado.
        $filasVacias = max(0, $maxFilas - count($trabajos));
        $filas = array_merge($trabajos, array_fill(0, $filasVacias, ['fecha' => '', 'lectura' => '', 'repuesto' => '', 'codigo' => '', 'cantidad' => '']));

        $this->SetFont('Arial', '', 7.5);
        foreach ($filas as $fila) {
            $tabla->rowStyle('font-color:30,30,30; font-style:; align:C;');
            $tabla->easyCell(utf8Decode($fila['fecha']));
            $tabla->easyCell(utf8Decode($fila['lectura']));
            $tabla->easyCell(utf8Decode($fila['repuesto']), 'align:L;');
            $tabla->easyCell(utf8Decode($fila['codigo']), 'align:L;');
            $tabla->easyCell(utf8Decode($fila['cantidad']));
            $tabla->printRow();
        }

        $tabla->endTable();

        // ══════════════════════════════════════════════════════════════════
        // OBSERVACIONES (caja punteada x 6.5-209.4, y 219.16-233.26)
        // ══════════════════════════════════════════════════════════════════
        if ($observaciones) {
            $this->SetFont('Arial', '', 8.5);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY(8, 221);
            $this->MultiCell(200, 4.5, utf8Decode($observaciones), 0, 'L');
        }

        // ══════════════════════════════════════════════════════════════════
        // FIRMAS (SOLICITANTE / EJECUTOR DE TRABAJO / Vo. Bo. — columnas ya
        // impresas en el fondo en x 4.53-71.50 / 71.50-141.18 / 141.18-210.10;
        // cada nombre se imprime centrado justo encima de su línea de firma,
        // en y=256.37). El ejecutor y el visto bueno sólo existen si la
        // solicitud ya derivó en una orden de trabajo emitida (Paso 2).
        // ══════════════════════════════════════════════════════════════════
        $columnasFirma = [
            [4.53, 71.50 - 4.53, $solicitante],
            [71.50, 141.18 - 71.50, $ejecutor],
            [141.18, 210.10 - 141.18, $vistoBueno],
        ];

        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        foreach ($columnasFirma as [$x, $w, $nombre]) {
            if (! $nombre) {
                continue;
            }
            $this->SetXY($x, 250);
            $this->Cell($w, 5, utf8Decode($nombre), 0, 0, 'C');
        }

        // El '/' de $nroSolicitud ("NNNNNN/GESTION") no es válido dentro de un nombre
        // de archivo, así que se reemplaza por '-' sólo para el nombre sugerido.
        return $this->Output($modo, $nombreArchivo ?? 'solicitud_mantenimiento_'.str_replace('/', '-', (string) $nroSolicitud).'.pdf');
    }

    /**
     * Formatea una lectura de horómetro/kilometraje (decimal:2, llega como
     * string o null) para la tabla de "TRABAJOS REALIZADOS". Devuelve '' si
     * el ítem del detalle no registró esa lectura.
     */
    private function formatearLectura(?string $valor): string
    {
        return $valor !== null ? number_format((float) $valor, 2, ',', '.') : '';
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

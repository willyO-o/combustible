<?php

namespace App\Libraries;

use App\Models\CargaCombustible;
use App\Models\OperacionDiaria;
use App\Models\ParametrosEmpresa;
use App\Models\TipoMantenimiento;
use App\Models\VehiculoExterno;
use easyTable;
use exFPDF;
use Illuminate\Support\Collection;
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
        $blanco = [30, 30, 30];

        $pageW = 215.9;
        $pageH = 279.4;

        $gris = [90, 90, 90];

        $this->AddPage('P', 'Letter');
        $this->SetMargins(5, 5, 5);
        $this->SetAutoPageBreak(false);
        $this->registrarFuenteCalibri();

        // ----------------------------------------------------------
        // FONDO (diseño completo tamaño carta: cuadros, líneas e íconos
        // ya vienen impresos en la imagen; aquí sólo se ubica el texto)
        // ----------------------------------------------------------
        $this->Image(public_path('images/reportes/vale-fondo-carta.jpg'), 0, 0, $pageW, $pageH);

        // ----------------------------------------------------------
        // ENCABEZADO: logo, título "VALE" + correlativo, datos del grifo
        // ----------------------------------------------------------
        $this->Image(public_path('images/logo/logo-plus-metals.png'), 8, 12, 42);

        $this->SetXY(52, 10);
        $this->SetFont('Calibri', 'BI', 40);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->Cell(45, 14, $this->textoCalibri('VALE'), 0, 0, 'L');

        $this->SetXY(53, 24);
        $this->SetFont('Calibri', 'B', 15);
        $this->SetTextColor($rojo[0], $rojo[1], $rojo[2]);
        $this->Cell(45, 6, $this->textoCalibri('N°'.$numeroVale), 0, 0, 'L');

        // Datos del grifo (alineados a la derecha, arriba). El ancho se
        // detiene antes del ícono del surtidor impreso en el fondo (~170mm).
        $this->SetXY(112, 10);
        $this->SetFont('Calibri', 'B', 10);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->Cell(80, 5, $this->textoCalibri($empresa), 0, 2, 'R');

        $this->SetX(112);
        $this->SetFont('Calibri', '', 8);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->Cell(80, 4.5, $this->textoCalibri($direccion), 0, 2, 'R');
        $this->SetX(112);
        $this->Cell(80, 4.5, $this->textoCalibri($telefono), 0, 2, 'R');
        $this->SetX(112);
        $this->Cell(80, 4.5, $this->textoCalibri($ciudad), 0, 2, 'R');

        // Nombre de la empresa local, sobre la franja azul del encabezado
        // (mismo límite de ancho que los datos del grifo, antes del ícono)
        $this->SetXY(130, 33);
        $this->SetFont('Calibri', 'B', 12);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
        $this->Cell(80, 6, $this->textoCalibri(mb_strtoupper($empresaLocal)), 0, 0, 'R');

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

        $this->SetFont('Calibri', '', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        foreach ($filas as [$y, $valor]) {
            $this->SetXY($valX, $y);
            $this->Cell($valW, 5, $this->textoCalibri($valor), 0, 0, 'L');
        }

        // ----------------------------------------------------------
        // CUADRO DERECHO: litros / importe / combustible / autorizado
        // ----------------------------------------------------------
        // Lt. / Bs. (números grandes, centrados en cada mitad del cuadro)
        $this->SetFont('Calibri', 'B', 22);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY(109.6, 60);
        $this->Cell(49.9, 14, $litros, 0, 0, 'C');
        $this->SetXY(159.5, 60);
        $this->Cell(47.8, 14, $bolivianos, 0, 0, 'C');

        // Montos en letras
        $this->SetFont('Calibri', '', 9);
        $this->SetXY(109.6, 92.5);
        $this->Cell(97.7, 4.5, $this->textoCalibri(numeroLiteral($litros, 'LITROS')), 0, 0, 'C');

        $this->SetXY(109.6, 104);
        $this->Cell(97.7, 4.5, $this->textoCalibri(monedaLiteral($bolivianos)), 0, 0, 'C');

        // Tipo de combustible / Autorizado por (entre la etiqueta y su línea)
        $this->SetXY(150, 118);
        $this->Cell(93, 4, $this->textoCalibri($tipoCombustible), 0, 0, 'L');

        $this->SetXY(148, 132);
        $this->Cell(93, 4, $this->textoCalibri($autorizadoPor), 0, 0, 'L');

        // QR de verificación
        $urlQr = route('vales.publico', ['hash' => md5($vale->id)]);
        $base64Qr = 'data:image/png;base64,'.$this->getBase64Qr($urlQr);
        $this->Image($base64Qr, 175, 145, 26, 29, 'PNG');

        // ----------------------------------------------------------
        // PIE: franja de "uso único" con fechas de emisión y vencimiento
        // ----------------------------------------------------------
        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor($blanco[0], $blanco[1], $blanco[2]);
        $this->SetXY(108, 188);
        $this->Cell(28, 6, $this->textoCalibri($vale->fecha_emision?->format('d/m/Y H:i')), 0, 0, 'L');

        $this->SetXY(170, 188);
        $this->Cell(29, 6, $this->textoCalibri($vale->fecha_vencimiento?->format('d/m/Y H:i')), 0, 0, 'L');

        // agregar la fecha de impresión del PDF en el pie de página
        $this->SetFont('Calibri', 'I', 7);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY(5, 268);
        $this->Cell(203, 4, $this->textoCalibri('Fecha de impresión: '.now()->format('d/m/Y H:i')), 0, 0, 'R');

        // ----------------------------------------------------------
        // SELLO "ANULADO": se dibuja al final para quedar por encima de
        // todo lo demás. El PNG ya trae el sello rotado con fondo
        // transparente (canal alpha), así que sólo se centra en la
        // página; FPDF soporta el canal alpha de forma nativa (SMask).
        // ----------------------------------------------------------
        if ($vale->estado_vale === 'ANULADO') {
            $selloAncho = 130;
            [$selloAnchoPx, $selloAltoPx] = getimagesize(public_path('images/reportes/anulado.png'));
            $selloAlto = $selloAncho * ($selloAltoPx / $selloAnchoPx);

            $this->Image(
                public_path('images/reportes/anulado.png'),
                ($pageW - $selloAncho) / 2,
                ($pageH - $selloAlto) / 2,
                $selloAncho,
                $selloAlto
            );
        }

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
        $this->registrarFuenteCalibri();

        // ----------------------------------------------------------
        // FONDO (diseño completo tamaño carta: cuadros, tabla, líneas e
        // íconos ya vienen impresos en la imagen; aquí sólo se ubica el
        // texto).
        // ----------------------------------------------------------
        $this->Image(public_path('images/reportes/fondo-comprobante-egreso.jpg'), 0, 0, $pageW, $pageH);

        // ----------------------------------------------------------
        // ENCABEZADO: logo, título, correlativo y fecha
        // ----------------------------------------------------------
        $this->Image(public_path('images/logo/logo-plus-metals.png'), 8, 8, 40);

        $this->SetXY(50, 8);
        $this->SetFont('Calibri', 'B', 18);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->Cell(105, 9, $this->textoCalibri('COMPROBANTE DE EGRESO'), 0, 2, 'C');
        $this->SetX(50);
        $this->Cell(105, 9, $this->textoCalibri('DE COMBUSTIBLE'), 0, 2, 'C');

        // Nro. de comprobante, sobre el recuadro azul relleno del encabezado
        $this->SetXY(158, 13.2);
        $this->SetFont('Calibri', 'B', 11);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->Cell(29, 6, $this->textoCalibri($numeroComprobante), 0, 0, 'C');

        // Fecha, debajo de la etiqueta "FECHA:" impresa en el fondo (a la
        // derecha, sobre el ícono del surtidor, no hay espacio suficiente)
        $this->SetXY(165, 26);
        $this->SetFont('Calibri', '', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->Cell(34, 5, $this->textoCalibri($fecha), 0, 0, 'L');

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

        $this->SetFont('Calibri', '', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        foreach ($filas as [$y, $h, $valor]) {
            $this->SetXY($valX, $y);
            $this->Cell($valW, $h, $this->textoCalibri(mb_strtoupper($valor)), 0, 0, 'L');
        }

        // ----------------------------------------------------------
        // TABLA: única línea de concepto (la carga de combustible
        // registrada). CargaCombustible sólo admite un tipo de
        // combustible por registro, así que siempre es una sola fila.
        // ----------------------------------------------------------
        $this->SetFont('Calibri', 'B', 10);
        $this->SetXY(10.5, 130);
        $this->Cell(100, 6, $this->textoCalibri(mb_strtoupper($tipoCombustible)), 0, 0, 'L');

        $this->SetFont('Calibri', '', 9);
        $this->SetXY(117.48, 130);
        $this->Cell(30.1, 6, number_format($litros, 2, ',', '.').' LT', 0, 0, 'C');

        $this->SetXY(147.58, 130);
        $this->Cell(29.98, 6, number_format($precioUnitario, 2, ',', '.'), 0, 0, 'C');

        $this->SetXY(177.56, 130);
        $this->Cell(31.14, 6, 'Bs '.number_format($total, 2, ',', '.'), 0, 0, 'C');

        // Total estimado: la franja "TOTAL ESTIMADO" sólo trae relleno azul
        // del lado de la etiqueta; el lado del valor queda en blanco, así
        // que el monto va en color oscuro, no blanco.
        $this->SetFont('Calibri', 'B', 13);
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
        $maxFilas = 11;

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
        $azul = [0, 75, 145];
        $negro = [30, 30, 30];
        // Tabla dinámica "TRABAJOS REALIZADOS" (única sección que el fondo no
        // trae impresa): líneas/bordes y todo su texto en negro, y el fondo
        // de la cabecera de la tabla en gris #c9c9c9 en vez de azul, a pedido
        // del usuario. El checkbox de tipo de mantenimiento (sobre el fondo
        // pre-impreso, fuera de esta tabla) sigue en $azul como antes.
        $grisEncabezado = [201, 201, 201];

        $pageW = 215.9;
        $pageH = 279.4;

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(false);
        $this->registrarFuenteCalibri();

        // ----------------------------------------------------------
        // FONDO (formulario completo tamaño carta: cajas, etiquetas y líneas
        // ya vienen impresas en la imagen; aquí sólo se ubica el texto).
        // ----------------------------------------------------------
        $this->Image(public_path('images/reportes/fondo-solicitud-mantenimiento.jpg'), 0, 0, $pageW, $pageH);

        // ══════════════════════════════════════════════════════════════════
        // ENCABEZADO: logo (zona x 2.29-48.98) y N° de solicitud (zona x 170.98-212.85)
        // ══════════════════════════════════════════════════════════════════
        $this->Image(public_path('images/logo/logo-plus-metals.png'), 6, 8, 42);

        $this->SetFont('Calibri', 'B', 14);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY(172, 9);
        $this->Cell(39, 9, $this->textoCalibri('N° '.$nroSolicitud), 0, 0, 'R');

        // ══════════════════════════════════════════════════════════════════
        // DATOS DEL SOLICITANTE/EQUIPO (caja izquierda, x 4.53-113.79, filas de
        // ~9.1mm entre y=29.04 e y=74.51) + FECHA DE SOLICITUD (caja derecha
        // superior, x 117.43-210.10, y 29.04-40.00)
        // ══════════════════════════════════════════════════════════════════
        $this->SetFont('Calibri', '', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        $filasIzq = [
            [31.5, $solicitante],
            [40.0, $maquinaria],
            [49.1, $modelo],
            [58.2, $placa],
            [67.3, $codigoVehiculo],
        ];
        foreach ($filasIzq as [$y, $valor]) {
            $this->SetXY(55, $y);
            $this->Cell(56, 6, $this->textoCalibri((string) ($valor ?? '')), 0, 0, 'L');
        }

        $this->SetXY(168, 31.5);
        $this->Cell(40, 6, $this->textoCalibri((string) $fechaSolicitud), 0, 0, 'L');

        // -- Tipo de mantenimiento: casilla marcada con una "X" (caja derecha
        // inferior, checkboxes ya impresos en el fondo en (149.78-155.96,
        // 60.41-66.08) para "A" y (195.41-201.34, 60.41-66.08) para "B") --
        $this->SetFont('Calibri', 'B', 10);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $cx = $tipoMant === 'A' ? 149.78 : 195.41;
        $this->SetXY($cx, 60.9);
        $this->Cell(6.2, 5.2, 'X', 0, 0, 'C');

        // ══════════════════════════════════════════════════════════════════
        // DESCRIPCIÓN DE LA FALLA DEL EQUIPO (caja punteada x 6.5-209.4, y 85.30-123.32)
        // ══════════════════════════════════════════════════════════════════
        if ($descripcion) {
            $this->SetFont('Calibri', '', 9);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY(8, 87.5);
            $this->MultiCell(200, 5, $this->textoCalibri($descripcion), 0, 'L');
        }

        // ══════════════════════════════════════════════════════════════════
        // TRABAJOS REALIZADOS: única sección que el fondo NO trae impresa —
        // ocupa el hueco en blanco entre la caja de DESCRIPCIÓN (termina en
        // y=125.48) y la de OBSERVACIONES (empieza en y=211.29), con un margen
        // de ~3mm arriba y abajo para no tocar ninguna de las dos.
        // ══════════════════════════════════════════════════════════════════
        $s4X = 2.29;
        $s4W = 210; // 212.85 - 2.29, mismo ancho que las demás cajas del fondo
        $s4Y = 128.5;
        // Bottom disponible: 208.3 (211.29 de OBSERVACIONES - 3mm de margen). Con
        // el título (8mm) + $maxFilas=10 filas de 6.5mm de alto (65mm) la tabla
        // termina en 128.5 + 8 + 65 = 201.5, dentro de ese límite.

        $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($s4X + 2, $s4Y, $s4W - 4, 8);
        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($s4X, $s4Y);
        $this->Cell($s4W, 8, $this->textoCalibri('TRABAJOS REALIZADOS'), 0, 0, 'C');

        $etiquetaLectura = $tipoMedicion === 'kilometraje' ? 'KILOMETRAJE' : 'HOROMETRO';
        $anchos = [23, 25, 110, 30, 18]; // suma = $s4W (210.56)
        $encabezados = ['FECHA', $etiquetaLectura, 'REPUESTO UTILIZADO', 'COD. REPUESTO', 'CANTIDAD'];

        $this->SetMargins(-1, 0, 0);
        $this->SetXY(0, $s4Y + 8);
        $anchoTabla = array_sum($anchos);
        $tabla = new easyTable($this, '{'.implode(',', $anchos).'}', "width:{$anchoTabla}; border:1; border-color:{$negro[0]},{$negro[1]},{$negro[2]}; border-width:0.25; font-family:Calibri; valign:M; paddingX:1.5; min-height:6.5;");

        // Fondo gris (antes azul) + texto negro (antes blanco, para contraste
        // sobre azul oscuro; con fondo gris claro el blanco quedaría ilegible).
        $tabla->rowStyle("bgcolor:{$grisEncabezado[0]},{$grisEncabezado[1]},{$grisEncabezado[2]}; font-color:{$negro[0]},{$negro[1]},{$negro[2]}; font-style:B; font-size:7.5; align:C;");
        foreach ($encabezados as $encabezado) {
            $tabla->easyCell($this->textoCalibri($encabezado), 'align:C;');
        }
        $tabla->printRow(true);

        // Filas de datos reales, seguidas de filas en blanco hasta completar
        // $maxFilas: la tabla siempre ocupa todo el espacio disponible, tenga
        // o no tenga (todavía) el detalle de trabajo cargado.
        $filasVacias = max(0, $maxFilas - count($trabajos));
        $filas = array_merge($trabajos, array_fill(0, $filasVacias, ['fecha' => '', 'lectura' => '', 'repuesto' => '', 'codigo' => '', 'cantidad' => '']));

        $this->SetFont('Calibri', '', 7.5);
        foreach ($filas as $fila) {
            $tabla->rowStyle('font-color:30,30,30; font-style:; align:C;min-height:5.5;');
            $tabla->easyCell($this->textoCalibri($fila['fecha']), 'align:C;');
            $tabla->easyCell($this->textoCalibri($fila['lectura']), 'align:R;');
            $tabla->easyCell($this->textoCalibri($fila['repuesto']), 'align:L;');
            $tabla->easyCell($this->textoCalibri($fila['codigo']), 'align:C;');
            $tabla->easyCell($this->textoCalibri($fila['cantidad']), 'align:C;');
            $tabla->printRow();
        }

        $tabla->endTable();
        $this->SetMargins(8, 8, 8);

        // ══════════════════════════════════════════════════════════════════
        // OBSERVACIONES (caja punteada x 6.5-209.4, y 219.16-233.26)
        // ══════════════════════════════════════════════════════════════════
        if ($observaciones) {
            $this->SetFont('Calibri', '', 8.5);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY(8, 221);
            $this->MultiCell(200, 4.5, $this->textoCalibri($observaciones), 0, 'L');
        }

        // ══════════════════════════════════════════════════════════════════
        // FIRMAS (SOLICITANTE / EJECUTOR DE TRABAJO / Vo. Bo. — columnas ya
        // impresas en el fondo en x 4.53-71.50 / 71.50-141.18 / 141.18-210.10).
        // La línea de firma del fondo va en y≈256.4; cada nombre se imprime
        // centrado DEBAJO de su línea (y=258.5, medido sobre un render real:
        // deja hueco en blanco arriba de la línea para la firma a mano y no
        // se pisa con "FECHA ___/___/___", que empieza en y≈265.4 — antes el
        // nombre se imprimía encima de la línea, a pedido del usuario ahora
        // va debajo). El ejecutor y el visto bueno sólo existen si la
        // solicitud ya derivó en una orden de trabajo emitida (Paso 2).
        // ══════════════════════════════════════════════════════════════════
        $columnasFirma = [
            [4.53, 71.50 - 4.53, $solicitante],
            [71.50, 141.18 - 71.50, $ejecutor],
            [141.18, 210.10 - 141.18, $vistoBueno],
        ];

        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        foreach ($columnasFirma as [$x, $w, $nombre]) {
            if (! $nombre) {
                continue;
            }
            $this->SetXY($x, 258.5);
            $this->Cell($w, 5, $this->textoCalibri($nombre), 0, 0, 'C');
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
        return $valor !== null ? number_format((float) $valor, 2, ',', ' ') : '';
    }

    /**
     * @param  string|null  $tipoVehiculoLabel  Etiqueta ya resuelta a texto (no el id) del
     *                                          tipo de vehículo filtrado, para dejar constancia
     *                                          de ese filtro en el PDF (mismo criterio que
     *                                          generarReporteRendimiento()).
     */
    public function generarReporteCargasCombustible($fechaInicio, $fechaFin, $idVehiculo = null, $idTipoVehiculo = null, ?string $tipoVehiculoLabel = null)
    {
        // Importar modelo
        $cargasCombustibleQuery = CargaCombustible::whereBetween('fecha_carga', [$fechaInicio, $fechaFin])
            ->with(['vehiculo', 'tipoCombustible']);

        if ($idVehiculo) {
            $cargasCombustibleQuery->where('id_vehiculo', $idVehiculo);
        }

        if ($idTipoVehiculo) {
            $cargasCombustibleQuery->whereHas('vehiculo', fn ($q) => $q->where('id_tipo_vehiculo', $idTipoVehiculo));
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
        $grisEncabezado = [201, 201, 201];
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
        $this->registrarFuenteCalibri();

        $sx = 8;
        $sy = 8;
        $uw = 199.9;

        // ════════════════════════════════════════════════════════════════
        // ENCABEZADO
        // ════════════════════════════════════════════════════════════════
        // Alto dinámico: si hay un filtro aplicado se agrega una línea extra
        // debajo del rango de fechas (mismo criterio que generarReporteRendimiento()).
        $h1 = $tipoVehiculoLabel ? 22 : 16;

        $this->SetLineWidth(0.5);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $sy, $uw, $h1);

        // Logo (el configurado en Parámetros de la Empresa, o el de respaldo)
        $this->Image($this->logoEmpresa($parametrosEmpresa), $sx + 4, $sy + 1.5, 35);

        // Título
        $this->SetFont('Calibri', 'B', 14);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy);
        $this->Cell($uw - 40, 8, $this->textoCalibri('REPORTE DE CARGAS DE COMBUSTIBLE'), 0, 2, 'L');

        // Rango de fechas
        $this->SetFont('Calibri', '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx + 40, $sy + 8);
        $this->Cell($uw - 40, 8, $this->textoCalibri('Del '.date('d/m/Y', strtotime($fechaInicio)).' al '.date('d/m/Y', strtotime($fechaFin))), 0, 2, 'L');

        if ($tipoVehiculoLabel) {
            $this->SetFont('Calibri', 'BI', 8);
            $this->SetTextColor($verde[0], $verde[1], $verde[2]);
            $this->SetXY($sx + 40, $sy + 16);
            $this->Cell($uw - 40, 5, $this->textoCalibri('Filtro aplicado: Tipo de vehículo: '.$tipoVehiculoLabel), 0, 2, 'L');
        }

        $currentY = $sy + $h1 + 2;
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

            $this->SetFont('Calibri', 'B', 7);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($cardX, $currentY);
            $this->Cell($cardW, 5, $this->textoCalibri($card[0]), 0, 1, 'C');

            $this->SetFont('Calibri', 'B', 10);
            $this->SetXY($cardX, $currentY + 5);
            $this->Cell($cardW, 9, $this->textoCalibri($card[1]), 0, 1, 'C');

            $cardX += $cardW + 2;
        }

        $currentY += $cardH + 8;

        // ════════════════════════════════════════════════════════════════
        // TABLA DE VEHÍCULOS
        // ════════════════════════════════════════════════════════════════
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $currentY, $uw, 8, 'FD');

        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor(30, 30, 30);
        $this->SetXY($sx, $currentY);
        $this->Cell($uw, 8, $this->textoCalibri('DETALLE POR VEHÍCULO'), 0, 1, 'C');

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

        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
        $this->SetLineWidth(0.2);
        $this->SetFont('Calibri', 'B', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        $colX = $sx;
        foreach ($cols as $col) {
            $this->Rect($colX, $currentY, $col['w'], 7, 'FD');
            $this->SetXY($colX, $currentY);
            $this->Cell($col['w'], 7, $this->textoCalibri($col['label']), 0, 0, $col['align']);
            $colX += $col['w'];
        }

        $currentY += 7;

        // Filas de datos
        $this->SetFont('Calibri', '', 7.5);
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
                $this->Cell($col['w'] - 2, 6, $this->textoCalibri($valores[$idx]), 0, 0, $col['align']);
                $colX += $col['w'];
            }

            $currentY += 6;
        }

        // Fila de TOTALES
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetFont('Calibri', 'B', 8);
        $this->SetTextColor(30, 30, 30);

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
            $this->Cell($col['w'] - 2, 7, $this->textoCalibri($totales[$idx]), 0, 0, $col['align']);
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
        $grisEncabezado = [201, 201, 201];
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
        $this->registrarFuenteCalibri();

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

        $this->SetFont('Calibri', 'B', 14);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy + 1.5);
        $this->Cell($uw - 40, 8, $this->textoCalibri('REPORTE DE RENDIMIENTO DE COMBUSTIBLE'), 0, 2, 'L');

        $this->SetFont('Calibri', '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx + 40, $sy + 9.5);
        $this->Cell($uw - 40, 6, $this->textoCalibri('Del '.date('d/m/Y', strtotime($fechaInicio)).' al '.date('d/m/Y', strtotime($fechaFin))), 0, 2, 'L');

        if ($etiquetasFiltro) {
            $this->SetFont('Calibri', 'BI', 8);
            $this->SetTextColor($verde[0], $verde[1], $verde[2]);
            $this->SetXY($sx + 40, $sy + 16);
            $this->Cell($uw - 40, 5, $this->textoCalibri('Filtros aplicados: '.implode('   |   ', $etiquetasFiltro)), 0, 2, 'L');
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

            $this->SetFont('Calibri', 'B', 7);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($cardX, $currentY);
            $this->Cell($cardW, 5, $this->textoCalibri($card[0]), 0, 1, 'C');

            $this->SetFont('Calibri', 'B', 10);
            $this->SetXY($cardX, $currentY + 5);
            $this->Cell($cardW, 9, $this->textoCalibri($card[1]), 0, 1, 'C');

            $cardX += $cardW + 2;
        }

        $currentY += $cardH + 8;

        // ════════════════════════════════════════════════════════════════
        // TABLA DE VEHÍCULOS
        // ════════════════════════════════════════════════════════════════
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $currentY, $uw, 8, 'FD');

        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor(30, 30, 30);
        $this->SetXY($sx, $currentY);
        $this->Cell($uw, 8, $this->textoCalibri('DETALLE POR VEHÍCULO'), 0, 1, 'C');

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

        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
        $this->SetLineWidth(0.2);
        $this->SetFont('Calibri', 'B', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        $colX = $sx;
        foreach ($cols as $col) {
            $this->Rect($colX, $currentY, $col['w'], 7, 'FD');
            $this->SetXY($colX, $currentY);
            $this->Cell($col['w'], 7, $this->textoCalibri($col['label']), 0, 0, $col['align']);
            $colX += $col['w'];
        }

        $currentY += 7;

        $this->SetFont('Calibri', '', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        if ($resultado->isEmpty()) {
            $this->SetDrawColor($gris[0], $gris[1], $gris[2]);
            $this->SetLineWidth(0.1);
            $this->Rect($sx, $currentY, $uw, 7);
            $this->SetXY($sx, $currentY);
            $this->Cell($uw, 7, $this->textoCalibri('No hay datos de rendimiento para el rango seleccionado.'), 0, 0, 'C');
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
                $this->SetFont('Calibri', $idx === 7 && $sinDatos ? 'I' : '', 7.5);
                $this->SetTextColor($idx === 7 && $sinDatos ? $gris[0] : $negro[0], $idx === 7 && $sinDatos ? $gris[1] : $negro[1], $idx === 7 && $sinDatos ? $gris[2] : $negro[2]);
                $this->Cell($col['w'] - 2, 6, $this->textoCalibri($valores[$idx]), 0, 0, $col['align']);
                $colX += $col['w'];
            }

            $currentY += 6;
        }

        // Fila de TOTALES (sólo los campos que sí son sumables entre vehículos:
        // el rendimiento no se totaliza porque km/L y L/h no son comparables)
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetFont('Calibri', 'B', 8);
        $this->SetTextColor(30, 30, 30);

        $totales = ['TOTALES', '', '', '', (string) $totalCargas, number_format($totalLitros, 2, ',', '.').' L', number_format($totalRecorrido, 2, ',', '.'), '—'];

        $colX = $sx;
        foreach ($cols as $idx => $col) {
            $this->Rect($colX, $currentY, $col['w'], 7, 'FD');
            $this->SetXY($colX + 1, $currentY);
            $this->Cell($col['w'] - 2, 7, $this->textoCalibri($totales[$idx]), 0, 0, $col['align']);
            $colX += $col['w'];
        }

        $this->pintarPieDePagina($uw, $gris);

        return $this->Output($modo, $nombreArchivo ?? 'reporte_rendimiento_combustible.pdf');
    }

    /**
     * Reporte de control de carga de material: cantidad de viajes realizados
     * por cada vehículo externo dentro del rango, con el reparto entre viajes
     * al exterior y nacionales. $resumen es lo que devuelve
     * ControlCargasReportController::obtenerResumen() (una fila por vehículo
     * externo + totales).
     *
     * @param  array{vehiculos: iterable<array<string, mixed>>, totales: array<string, int|float>}  $resumen
     * @param  string  $ambito  'todos' | 'exterior' | 'nacional' — sólo para dejar constancia del filtro en el PDF.
     */
    public function generarReporteControlCargas(array $resumen, $fechaDesde, $fechaHasta, string $ambito = 'todos', string $modo = 'I', ?string $nombreArchivo = null)
    {
        $vehiculos = collect($resumen['vehiculos'] ?? []);
        $totales = ($resumen['totales'] ?? []) + [
            'total_vehiculos' => 0,
            'total_fletes' => 0,
            'total_viajes' => 0,
            'viajes_exterior' => 0,
            'viajes_nacional' => 0,
            'monto_total' => 0,
        ];

        $bs = fn ($valor): string => 'Bs. '.number_format((float) $valor, 2, ',', '.');

        $azul = [39, 42, 84];
        $grisEncabezado = [201, 201, 201];
        $verde = [24, 125, 170];
        $gris = [90, 90, 90];

        $parametrosEmpresa = $this->parametrosEmpresa();

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(true, 15);
        $this->registrarFuenteCalibri();

        $sx = 8;
        $sy = 8;
        $uw = 199.9;

        // ════════════════════════════════════════════════════════════════
        // ENCABEZADO
        // ════════════════════════════════════════════════════════════════
        $ambitoLabel = match ($ambito) {
            'exterior' => 'Sólo viajes al exterior',
            'nacional' => 'Sólo viajes nacionales',
            default => 'Todos los viajes',
        };
        $h1 = 22;

        $this->SetFillColor($verde[0], $verde[1], $verde[2]);
        $this->Rect($sx, $sy, $uw, 1.2, 'F');

        $this->SetLineWidth(0.5);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $sy + 1.2, $uw, $h1 - 1.2);

        $this->Image($this->logoEmpresa($parametrosEmpresa), $sx + 4, $sy + 3, 35);

        $this->SetFont('Calibri', 'B', 14);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy + 1.5);
        $this->Cell($uw - 40, 8, $this->textoCalibri('REPORTE DE CONTROL DE CARGA DE MATERIAL'), 0, 2, 'L');

        $this->SetFont('Calibri', '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx + 40, $sy + 9.5);
        $this->Cell($uw - 40, 6, $this->textoCalibri('Fletes abiertos del '.date('d/m/Y', strtotime($fechaDesde)).' al '.date('d/m/Y', strtotime($fechaHasta))), 0, 2, 'L');

        $this->SetFont('Calibri', 'BI', 8);
        $this->SetTextColor($verde[0], $verde[1], $verde[2]);
        $this->SetXY($sx + 40, $sy + 16);
        $this->Cell($uw - 40, 5, $this->textoCalibri('Filtro aplicado: '.$ambitoLabel), 0, 2, 'L');

        $currentY = $sy + $h1 + 2;
        $currentY += $this->pintarInfoEmpresa($parametrosEmpresa, $sx, $currentY, $uw, $gris);
        $currentY += 3;

        // ════════════════════════════════════════════════════════════════
        // TARJETAS DE RESUMEN
        // ════════════════════════════════════════════════════════════════
        $cardW = 38;
        $cardH = 14;
        $cards = [
            ['VEHÍCULOS EXTERNOS', (string) $totales['total_vehiculos'], [59, 89, 152]],
            ['TOTAL FLETES', (string) $totales['total_fletes'], [34, 177, 76]],
            ['TOTAL VIAJES', (string) $totales['total_viajes'], [192, 0, 0]],
            ['AL EXT. / NAC.', $totales['viajes_exterior'].' / '.$totales['viajes_nacional'], [155, 155, 155]],
            ['MONTO PAGADO', $bs($totales['monto_total']), [24, 125, 170]],
        ];

        $cardX = $sx;
        foreach ($cards as $card) {
            [$label, $valor, $color] = $card;

            $this->SetLineWidth(0.3);
            $this->SetDrawColor($color[0], $color[1], $color[2]);
            $this->SetFillColor($color[0], $color[1], $color[2]);
            $this->Rect($cardX, $currentY, $cardW, $cardH, 'FD');

            $this->SetFont('Calibri', 'B', 6.5);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($cardX, $currentY);
            $this->Cell($cardW, 5, $this->textoCalibri($label), 0, 1, 'C');

            $this->SetFont('Calibri', 'B', 8.5);
            $this->SetXY($cardX, $currentY + 5);
            $this->Cell($cardW, 9, $this->textoCalibri($valor), 0, 1, 'C');

            $cardX += $cardW + 2;
        }

        $currentY += $cardH + 8;

        // ════════════════════════════════════════════════════════════════
        // TABLA POR VEHÍCULO EXTERNO
        // ════════════════════════════════════════════════════════════════
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $currentY, $uw, 8, 'FD');
        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor(30, 30, 30);
        $this->SetXY($sx, $currentY);
        $this->Cell($uw, 8, $this->textoCalibri('VIAJES POR VEHÍCULO EXTERNO'), 0, 1, 'C');

        $currentY += 8;

        $this->SetXY($sx, $currentY);
        $tabla = new easyTable($this, '{9, 27, 56, 17, 17, 18, 18, 37}', 'width:199; border:1; border-color:30,30,30; border-width:0.2; font-family:Calibri; valign:M; paddingX:1.5; min-height:6;');

        $tabla->rowStyle('bgcolor:201,201,201; font-style:B; font-size:7.5; font-color:30,30,30;');
        foreach (['#', 'PLACA', 'PROPIETARIO', 'FLETES', 'VIAJES', 'AL EXT.', 'NACION.', 'MONTO PAGADO'] as $i => $encabezado) {
            $tabla->easyCell($this->textoCalibri($encabezado), 'align:'.($i <= 2 ? 'L' : ($i === 7 ? 'R' : 'C')).';');
        }
        $tabla->printRow(true);

        if ($vehiculos->isEmpty()) {
            $tabla->rowStyle('font-size:8; font-color:90,90,90;');
            $tabla->easyCell($this->textoCalibri('No hay fletes en el rango y filtro seleccionados.'), 'align:C; colspan:8;');
            $tabla->printRow();
        }

        foreach ($vehiculos as $idx => $veh) {
            $tabla->rowStyle('font-style:; font-size:7.5; font-color:30,30,30;');
            $tabla->easyCell((string) ($idx + 1), 'align:C;');
            $tabla->easyCell($this->textoCalibri($veh['nro_placa'] ?? '—'), 'align:L;');
            $tabla->easyCell($this->textoCalibri($veh['propietario'] ?? '—'), 'align:L;');
            $tabla->easyCell((string) $veh['total_fletes'], 'align:C;');
            $tabla->easyCell((string) $veh['total_viajes'], 'align:C;');
            $tabla->easyCell((string) $veh['viajes_exterior'], 'align:C;');
            $tabla->easyCell((string) $veh['viajes_nacional'], 'align:C;');
            $tabla->easyCell($this->textoCalibri($bs($veh['monto_total'] ?? 0)), 'align:R;');
            $tabla->printRow();
        }

        if ($vehiculos->isNotEmpty()) {
            $tabla->rowStyle('bgcolor:201,201,201; font-style:B; font-size:8; font-color:30,30,30;');
            $tabla->easyCell($this->textoCalibri('TOTALES'), 'align:L; colspan:3;');
            $tabla->easyCell((string) $totales['total_fletes'], 'align:C;');
            $tabla->easyCell((string) $totales['total_viajes'], 'align:C;');
            $tabla->easyCell((string) $totales['viajes_exterior'], 'align:C;');
            $tabla->easyCell((string) $totales['viajes_nacional'], 'align:C;');
            $tabla->easyCell($this->textoCalibri($bs($totales['monto_total'])), 'align:R;');
            $tabla->printRow();
        }

        $tabla->endTable();

        $this->pintarPieDePagina($uw, $gris);

        return $this->Output($modo, $nombreArchivo ?? 'reporte_control_cargas_material.pdf');
    }

    /**
     * Detalle de un solo vehículo externo: sus fletes abiertos en el rango, el
     * desglose de viajes por material de cada uno y el resumen por material del
     * vehículo. $detalle es lo que devuelve
     * ControlCargasReportController::obtenerDetalleVehiculo().
     *
     * @param  VehiculoExterno  $vehiculo
     * @param  array{fletes: iterable<array<string, mixed>>, materiales: iterable<array<string, mixed>>, totales: array<string, int|float>}  $detalle
     */
    public function generarReporteControlCargasDetalle($vehiculo, array $detalle, $fechaDesde, $fechaHasta, string $modo = 'I', ?string $nombreArchivo = null)
    {
        $fletes = collect($detalle['fletes'] ?? []);
        $materiales = collect($detalle['materiales'] ?? []);
        $totales = ($detalle['totales'] ?? []) + [
            'total_fletes' => 0,
            'total_viajes' => 0,
            'total_materiales' => 0,
            'monto_total' => 0,
        ];

        $azul = [39, 42, 84];
        $grisEncabezado = [201, 201, 201];
        $verde = [24, 125, 170];
        $gris = [90, 90, 90];

        $bs = fn ($valor): string => 'Bs. '.number_format((float) $valor, 2, ',', '.');
        $fechaHora = fn ($valor): string => $valor ? date('d/m/Y H:i', strtotime((string) $valor)) : '—';
        $pct = fn (int $viajes): string => $totales['total_viajes'] > 0
            ? number_format($viajes / $totales['total_viajes'] * 100, 1, ',', '.').' %'
            : '0 %';

        $parametrosEmpresa = $this->parametrosEmpresa();

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(true, 15);
        $this->registrarFuenteCalibri();

        $sx = 8;
        $sy = 8;
        $uw = 199.9;

        // ── Encabezado ──────────────────────────────────────────────────
        $h1 = 22;
        $this->SetFillColor($verde[0], $verde[1], $verde[2]);
        $this->Rect($sx, $sy, $uw, 1.2, 'F');
        $this->SetLineWidth(0.5);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $sy + 1.2, $uw, $h1 - 1.2);
        $this->Image($this->logoEmpresa($parametrosEmpresa), $sx + 4, $sy + 3, 35);

        $this->SetFont('Calibri', 'B', 13);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy + 1.5);
        $this->Cell($uw - 40, 8, $this->textoCalibri('DETALLE DE CONTROL DE CARGA DE MATERIAL'), 0, 2, 'L');

        $this->SetFont('Calibri', '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx + 40, $sy + 9.5);
        $this->Cell($uw - 40, 6, $this->textoCalibri('Fletes abiertos del '.date('d/m/Y', strtotime($fechaDesde)).' al '.date('d/m/Y', strtotime($fechaHasta))), 0, 2, 'L');

        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy + 15.5);
        $placa = $vehiculo->nro_placa ?: 'Sin placa';
        $propietario = $vehiculo->propietario ? '   |   '.$vehiculo->propietario : '';
        $this->Cell($uw - 40, 5, $this->textoCalibri('Vehículo externo: '.$placa.$propietario), 0, 2, 'L');

        $currentY = $sy + $h1 + 2;
        $currentY += $this->pintarInfoEmpresa($parametrosEmpresa, $sx, $currentY, $uw, $gris);
        $currentY += 3;

        // ── Tarjetas de resumen ─────────────────────────────────────────
        $cardW = 49;
        $cardH = 14;
        $cards = [
            ['FLETES', (string) $totales['total_fletes'], [59, 89, 152]],
            ['TOTAL VIAJES', (string) $totales['total_viajes'], [34, 177, 76]],
            ['TIPOS DE MATERIAL', (string) $totales['total_materiales'], [192, 0, 0]],
            ['MONTO PAGADO', $bs($totales['monto_total']), [24, 125, 170]],
        ];

        $cardX = $sx;
        foreach ($cards as [$label, $valor, $color]) {
            $this->SetLineWidth(0.3);
            $this->SetDrawColor($color[0], $color[1], $color[2]);
            $this->SetFillColor($color[0], $color[1], $color[2]);
            $this->Rect($cardX, $currentY, $cardW, $cardH, 'FD');

            $this->SetFont('Calibri', 'B', 7);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($cardX, $currentY);
            $this->Cell($cardW, 5, $this->textoCalibri($label), 0, 1, 'C');

            $this->SetFont('Calibri', 'B', 9);
            $this->SetXY($cardX, $currentY + 5);
            $this->Cell($cardW, 9, $this->textoCalibri($valor), 0, 1, 'C');

            $cardX += $cardW + 2;
        }

        $currentY += $cardH + 8;

        // ── Resumen por material ────────────────────────────────────────
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $currentY, $uw, 8, 'FD');
        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor(30, 30, 30);
        $this->SetXY($sx, $currentY);
        $this->Cell($uw, 8, $this->textoCalibri('RESUMEN DE TIPOS DE CARGA (VIAJES POR MATERIAL)'), 0, 1, 'C');
        $currentY += 8;

        $this->SetXY($sx, $currentY);
        $tablaMat = new easyTable($this, '{119, 40, 40}', 'width:199; border:1; border-color:30,30,30; border-width:0.2; font-family:Calibri; valign:M; paddingX:1.5; min-height:6;');

        $tablaMat->rowStyle('bgcolor:201,201,201; font-style:B; font-size:7.5; font-color:30,30,30;');
        $tablaMat->easyCell($this->textoCalibri('MATERIAL'), 'align:L;');
        $tablaMat->easyCell($this->textoCalibri('VIAJES'), 'align:C;');
        $tablaMat->easyCell($this->textoCalibri('% DEL TOTAL'), 'align:R;');
        $tablaMat->printRow(true);

        if ($materiales->isEmpty()) {
            $tablaMat->rowStyle('font-size:8; font-color:90,90,90;');
            $tablaMat->easyCell($this->textoCalibri('El vehículo no registró viajes en el rango seleccionado.'), 'align:C; colspan:3;');
            $tablaMat->printRow();
        }

        foreach ($materiales as $mat) {
            $tablaMat->rowStyle('font-style:; font-size:7.5; font-color:30,30,30;');
            $tablaMat->easyCell($this->textoCalibri($mat['material'] ?? '—'), 'align:L;');
            $tablaMat->easyCell((string) ($mat['viajes'] ?? 0), 'align:C;');
            $tablaMat->easyCell($this->textoCalibri($pct((int) ($mat['viajes'] ?? 0))), 'align:R;');
            $tablaMat->printRow();
        }

        if ($materiales->isNotEmpty()) {
            $tablaMat->rowStyle('bgcolor:201,201,201; font-style:B; font-size:8; font-color:30,30,30;');
            $tablaMat->easyCell($this->textoCalibri('TOTAL'), 'align:L;');
            $tablaMat->easyCell((string) $totales['total_viajes'], 'align:C;');
            $tablaMat->easyCell($this->textoCalibri('100 %'), 'align:R;');
            $tablaMat->printRow();
        }

        $tablaMat->endTable();
        $currentY = $this->GetY() + 6;

        // ── Fletes del vehículo ─────────────────────────────────────────
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $currentY, $uw, 8, 'FD');
        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor(30, 30, 30);
        $this->SetXY($sx, $currentY);
        $this->Cell($uw, 8, $this->textoCalibri('FLETES DEL VEHÍCULO'), 0, 1, 'C');
        $currentY += 8;

        $this->SetXY($sx, $currentY);
        $tablaFle = new easyTable($this, '{21, 25, 25, 17, 23, 14, 45, 29}', 'width:199; border:1; border-color:30,30,30; border-width:0.2; font-family:Calibri; valign:M; paddingX:1.2; min-height:6;');

        $tablaFle->rowStyle('bgcolor:201,201,201; font-style:B; font-size:7; font-color:30,30,30;');
        foreach (['Nº FLETE', 'APERTURA', 'CIERRE', 'ESTADO', 'ÁMBITO', 'VIAJES', 'VIAJES POR MATERIAL', 'MONTO (Bs.)'] as $i => $encabezado) {
            $tablaFle->easyCell($this->textoCalibri($encabezado), 'align:'.($i === 5 ? 'C' : ($i === 7 ? 'R' : 'L')).';');
        }
        $tablaFle->printRow(true);

        if ($fletes->isEmpty()) {
            $tablaFle->rowStyle('font-size:8; font-color:90,90,90;');
            $tablaFle->easyCell($this->textoCalibri('Sin fletes abiertos en el rango seleccionado.'), 'align:C; colspan:8;');
            $tablaFle->printRow();
        }

        foreach ($fletes as $flete) {
            $ambito = ($flete['es_al_exterior'] ?? false)
                ? 'Exterior'.(! empty($flete['pais']) ? ' · '.$flete['pais'] : '')
                : 'Nacional';

            $desglose = collect($flete['materiales'] ?? [])
                ->map(fn ($m) => ($m['material'] ?? '—').': '.($m['viajes'] ?? 0))
                ->implode(', ') ?: '—';

            $tablaFle->rowStyle('font-style:; font-size:7; font-color:30,30,30;');
            $tablaFle->easyCell($this->textoCalibri((string) ($flete['nro'] ?? '—')), 'align:L;');
            $tablaFle->easyCell($this->textoCalibri($fechaHora($flete['fecha_apertura'] ?? null)), 'align:L;');
            $tablaFle->easyCell($this->textoCalibri($fechaHora($flete['fecha_cierre'] ?? null)), 'align:L;');
            $tablaFle->easyCell($this->textoCalibri((string) ($flete['estado_carga'] ?? '—')), 'align:L;');
            $tablaFle->easyCell($this->textoCalibri($ambito), 'align:L;');
            $tablaFle->easyCell((string) ($flete['viajes_count'] ?? 0), 'align:C;');
            $tablaFle->easyCell($this->textoCalibri($desglose), 'align:L;');
            $tablaFle->easyCell($this->textoCalibri(($flete['monto_pago'] ?? null) !== null ? $bs($flete['monto_pago']) : '—'), 'align:R;');
            $tablaFle->printRow();
        }

        if ($fletes->isNotEmpty()) {
            $tablaFle->rowStyle('bgcolor:201,201,201; font-style:B; font-size:7.5; font-color:30,30,30;');
            $tablaFle->easyCell($this->textoCalibri('TOTALES'), 'align:L; colspan:5;');
            $tablaFle->easyCell((string) $totales['total_viajes'], 'align:C;');
            $tablaFle->easyCell($this->textoCalibri(''), 'align:L;');
            $tablaFle->easyCell($this->textoCalibri($bs($totales['monto_total'])), 'align:R;');
            $tablaFle->printRow();
        }

        $tablaFle->endTable();

        $this->pintarPieDePagina($uw, $gris);

        return $this->Output($modo, $nombreArchivo ?? 'detalle_control_cargas_material.pdf');
    }

    /**
     * Informe individual de UN flete: datos generales (vehículo externo,
     * conductor, ámbito, fechas de apertura/cierre/pago) + el detalle
     * completo de sus viajes. $viajes es la colección de Viaje ya cargada
     * con material y usuarioRegistro (ver CargaMaterialController::generarPDF()).
     */
    public function generarReporteFlete($carga, $viajes, string $modo = 'I', ?string $nombreArchivo = null)
    {
        $viajes = collect($viajes);

        $gris = [90, 90, 90];
        // Ya no queda azul ni celeste en este reporte (a pedido del usuario,
        // sólo aplica a este método, no al resto de Reportes.php): los
        // fondos de relleno de encabezado (franja superior + barra "DETALLE
        // DE VIAJES" + fila de cabecera de la tabla) usan $grisEncabezado
        // #c9c9c9; TODO el texto (títulos, etiquetas, badge de N°) es
        // $negro; las líneas/bordes también son $negro (lo más delgadas
        // posible), salvo el borde de los encabezados con fondo gris, que
        // es blanco (ver más abajo).
        $grisEncabezado = [201, 201, 201];
        $negro = [0, 0, 0];

        $coloresEstado = [
            'ABIERTA' => [34, 177, 76],
            'CERRADA' => [90, 90, 90],
            'PAGADA' => [24, 125, 170],
        ];
        $colorEstado = $coloresEstado[$carga->estado_carga] ?? [90, 90, 90];

        $fechaHora = fn ($valor): string => $valor ? $valor->format('d/m/Y H:i') : '—';
        $bs = fn ($valor): string => $valor !== null ? 'Bs. '.number_format((float) $valor, 2, ',', '.') : '—';

        $parametrosEmpresa = $this->parametrosEmpresa();

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(true, 15);

        // Calibri en vez de Arial en todo este reporte (a pedido del
        // usuario) — ver registrarFuenteCalibri()/textoCalibri(). Calibri es
        // ahora la fuente de toda la clase (ver docblock de
        // registrarFuenteCalibri()); $fuente se mantiene como variable local
        // aquí sólo por el mismo estilo que ya tenía este método.
        $this->registrarFuenteCalibri();
        $fuente = 'Calibri';

        $sx = 8;
        $sy = 8;
        $uw = 199.9;

        // ── Encabezado ──────────────────────────────────────────────────
        // Franja de acento superior en gris (antes celeste/$verde) y borde
        // del recuadro lo más delgado posible, a pedido del usuario.
        $h1 = 22;
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->Rect($sx, $sy, $uw, 1.2, 'F');
        $this->SetLineWidth(0.1);
        $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
        $this->Rect($sx, $sy + 1.2, $uw, $h1 - 1.2);
        $this->Image($this->logoEmpresa($parametrosEmpresa), $sx + 4, $sy + 3, 35);

        $badgeW = 32;
        $tituloW = $uw - 40 - $badgeW - 3;

        $this->SetFont($fuente, 'B', 13);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($sx + 40, $sy + 1.5);
        $this->Cell($tituloW, 8, $this->textoCalibri('DETALLE DE FLETE'), 0, 2, 'L');

        $this->SetFont($fuente, '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetX($sx + 40);
        $this->Cell($tituloW, 6, $this->textoCalibri('Control de carga de material — vehículos externos'), 0, 2, 'L');

        // Placa + propietario, bajo el título (identifica el flete de un
        // vistazo, igual que en generarReporteControlCargasDetalle()).
        $placa = $carga->vehiculoExterno?->nro_placa ?: 'Sin placa';
        $propietario = $carga->vehiculoExterno?->propietario ? '   |   '.$carga->vehiculoExterno->propietario : '';
        $this->SetFont($fuente, 'B', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetX($sx + 40);
        $this->Cell($tituloW, 5, $this->textoCalibri('Vehículo externo: '.$placa.$propietario), 0, 2, 'L');

        // Badge de estado + N° de flete, arriba a la derecha.
        $badgeX = $sx + $uw - $badgeW - 3;
        $this->SetFillColor($colorEstado[0], $colorEstado[1], $colorEstado[2]);
        $this->Rect($badgeX, $sy + 3, $badgeW, 7, 'F');
        $this->SetFont($fuente, 'B', 8.5);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY($badgeX, $sy + 3);
        $this->Cell($badgeW, 7, $this->textoCalibri($carga->estado_carga), 0, 0, 'C');

        $this->SetFont($fuente, 'B', 11);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($badgeX, $sy + 12);
        $this->Cell($badgeW, 6, $this->textoCalibri('N° '.$carga->nro), 0, 0, 'C');

        $currentY = $sy + $h1 + 2;
        $currentY += $this->pintarInfoEmpresa($parametrosEmpresa, $sx, $currentY, $uw, $gris, $fuente);
        $currentY += 3;

        // ── Datos generales (grilla de 2 columnas) ───────────────────────
        $ambito = $carga->es_al_exterior
            ? 'Al exterior'.($carga->pais ? ' — '.$carga->pais : '')
            : 'Nacional';

        $filas = [
            ['Conductor', $carga->nombre_conductor ?: '—', 'Teléfono', $carga->telefono ?: '—'],
            ['Ámbito', $ambito, 'N° de viajes', (string) $viajes->count()],
            ['Fecha de apertura', $fechaHora($carga->fecha_apertura), 'Abierto por', $carga->usuarioApertura?->name ?? '—'],
            ['Fecha de cierre', $fechaHora($carga->fecha_cierre), 'Cerrado por', $carga->usuarioCierre?->name ?? '—'],
            ['Fecha de pago', $fechaHora($carga->fecha_pago), 'Monto pagado', $bs($carga->monto_pago)],
        ];

        $labelW = 32;
        $colW = $uw / 2;
        $rowH = 6.5;

        $this->SetLineWidth(0.1);
        $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
        foreach ($filas as [$label1, $valor1, $label2, $valor2]) {
            $this->SetFont($fuente, 'B', 8);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($sx, $currentY);
            $this->Cell($labelW, $rowH, $this->textoCalibri($label1.':'), 0, 0, 'L');

            $this->SetFont($fuente, '', 8.5);
            $this->SetTextColor(30, 30, 30);
            $this->SetXY($sx + $labelW, $currentY);
            $this->Cell($colW - $labelW - 2, $rowH, $this->textoCalibri(mb_strtoupper($valor1)), 0, 0, 'L');

            $this->SetFont($fuente, 'B', 8);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($sx + $colW, $currentY);
            $this->Cell($labelW, $rowH, $this->textoCalibri($label2.':'), 0, 0, 'L');

            $this->SetFont($fuente, '', 8.5);
            $this->SetTextColor(30, 30, 30);
            $this->SetXY($sx + $colW + $labelW, $currentY);
            $this->Cell($colW - $labelW - 2, $rowH, $this->textoCalibri(mb_strtoupper($valor2)), 0, 0, 'L');

            $this->Line($sx, $currentY + $rowH, $sx + $uw, $currentY + $rowH);
            $currentY += $rowH;
        }

        $currentY += 4;

        if ($carga->detalle) {
            $this->SetFont($fuente, 'B', 8);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($sx, $currentY);
            $this->Cell($uw, 5, $this->textoCalibri('DETALLE DEL FLETE:'), 0, 1, 'L');

            $this->SetFont($fuente, '', 8.5);
            $this->SetTextColor(30, 30, 30);
            $this->SetX($sx);
            $this->MultiCell($uw, 4.5, $this->textoCalibri($carga->detalle), 0, 'L');
            $currentY = $this->GetY() + 3;
        }

        if ($carga->observaciones) {
            $this->SetFont($fuente, 'B', 8);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($sx, $currentY);
            $this->Cell($uw, 5, $this->textoCalibri('OBSERVACIONES:'), 0, 1, 'L');

            $this->SetFont($fuente, '', 8.5);
            $this->SetTextColor(30, 30, 30);
            $this->SetX($sx);
            $this->MultiCell($uw, 4.5, $this->textoCalibri($carga->observaciones), 0, 'L');
            $currentY = $this->GetY() + 3;
        }

        // ── Detalle de viajes ─────────────────────────────────────────────
        // Encabezados (esta barra + la fila de cabecera de la tabla) en gris
        // #c9c9c9 en vez de azul, con texto negro para mantener el contraste
        // y borde BLANCO (sin línea negra visible sobre el fondo gris); el
        // resto de la tabla (filas de datos) sigue con líneas negras.
        $blanco = [255, 255, 255];
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($blanco[0], $blanco[1], $blanco[2]);
        $this->SetLineWidth(0.1);
        $this->Rect($sx, $currentY, $uw, 8, 'FD');
        $this->SetFont($fuente, 'B', 9);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($sx, $currentY);
        $this->Cell($uw, 8, $this->textoCalibri('DETALLE DE VIAJES'), 0, 1, 'C');
        $currentY += 8;

        $this->SetXY($sx, $currentY);
        $tablaViajes = new easyTable($this, '{10, 30, 32, 32, 38, 32, 25}', "width:199; border:1; border-color:{$negro[0]},{$negro[1]},{$negro[2]}; border-width:0.1; font-family:{$fuente}; valign:M; paddingX:1.2; min-height:6;");

        // border-color:255,255,255 sólo en esta fila (celda gana sobre tabla):
        // header con fondo gris y borde blanco, sin línea negra encima.
        $tablaViajes->rowStyle("bgcolor:{$grisEncabezado[0]},{$grisEncabezado[1]},{$grisEncabezado[2]}; border-color:{$blanco[0]},{$blanco[1]},{$blanco[2]}; font-style:B; font-size:7; font-color:0,0,0;");
        foreach (['N°', 'MATERIAL', 'ORIGEN', 'DESTINO', 'DETALLE', 'REGISTRADO POR', 'FECHA'] as $i => $encabezado) {
            $tablaViajes->easyCell($this->textoCalibri($encabezado), 'align:'.($i === 0 ? 'C' : 'L').';');
        }
        $tablaViajes->printRow(true);

        if ($viajes->isEmpty()) {
            $tablaViajes->rowStyle('font-size:8; font-color:90,90,90;');
            $tablaViajes->easyCell($this->textoCalibri('Este flete todavía no registra viajes.'), 'align:C; colspan:7;');
            $tablaViajes->printRow();
        }

        foreach ($viajes as $i => $viaje) {
            $tablaViajes->rowStyle('font-style:; font-size:7.5; font-color:30,30,30;');
            $tablaViajes->easyCell((string) ($i + 1), 'align:C;');
            $tablaViajes->easyCell($this->textoCalibri($viaje->material?->material ?? '—'), 'align:L;');
            $tablaViajes->easyCell($this->textoCalibri($viaje->origen ?: '—'), 'align:L;');
            $tablaViajes->easyCell($this->textoCalibri($viaje->destino ?: '—'), 'align:L;');
            $tablaViajes->easyCell($this->textoCalibri($viaje->detalle ?: '—'), 'align:L;');
            $tablaViajes->easyCell($this->textoCalibri($viaje->usuarioRegistro?->name ?? '—'), 'align:L;');
            $tablaViajes->easyCell($this->textoCalibri($fechaHora($viaje->fecha_hora_carga ?? $viaje->created_at)), 'align:L;');
            $tablaViajes->printRow();
        }

        $tablaViajes->endTable();

        $this->pintarPieDePagina($uw, $gris, $fuente);

        return $this->Output($modo, $nombreArchivo ?? 'flete_'.str_replace('/', '-', (string) $carga->nro).'.pdf');
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
        $grisEncabezado = [201, 201, 201];
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
        $this->registrarFuenteCalibri();

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

        $this->SetFont('Calibri', 'B', 13);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy + 1.5);
        $this->Cell($uw - 40, 8, $this->textoCalibri('DETALLE DE RENDIMIENTO — '.$vehiculo->codigo.' ('.$vehiculo->nro_placa.')'), 0, 2, 'L');

        $this->SetFont('Calibri', '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx + 40, $sy + 9.5);
        $this->Cell($uw - 40, 6, $this->textoCalibri('Del '.date('d/m/Y', strtotime($fechaInicio)).' al '.date('d/m/Y', strtotime($fechaFin))), 0, 2, 'L');

        if ($tipoCombustibleLabel) {
            $this->SetFont('Calibri', 'BI', 8);
            $this->SetTextColor($verde[0], $verde[1], $verde[2]);
            $this->SetXY($sx + 40, $sy + 16);
            $this->Cell($uw - 40, 5, $this->textoCalibri('Combustible: '.$tipoCombustibleLabel), 0, 2, 'L');
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

            $this->SetFont('Calibri', 'B', 7);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($cardX, $currentY);
            $this->Cell($cardW, 5, $this->textoCalibri($card[0]), 0, 1, 'C');

            $this->SetFont('Calibri', 'B', 10);
            $this->SetXY($cardX, $currentY + 5);
            $this->Cell($cardW, 9, $this->textoCalibri($card[1]), 0, 1, 'C');

            $cardX += $cardW + 2;
        }

        $currentY += $cardH + 8;

        // ════════════════════════════════════════════════════════════════
        // TABLA DE CARGAS
        // ════════════════════════════════════════════════════════════════
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $currentY, $uw, 8, 'FD');

        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor(30, 30, 30);
        $this->SetXY($sx, $currentY);
        $this->Cell($uw, 8, $this->textoCalibri('CARGAS DEL VEHÍCULO'), 0, 1, 'C');

        $currentY += 8;

        $cols = [
            ['label' => 'FECHA DE CARGA', 'w' => 34, 'align' => 'C'],
            ['label' => 'LITROS', 'w' => 26, 'align' => 'R'],
            ['label' => 'MED. ANTERIOR', 'w' => 32, 'align' => 'R'],
            ['label' => 'MED. ACTUAL', 'w' => 32, 'align' => 'R'],
            ['label' => strtoupper($etiquetaRecorrido), 'w' => 32.9, 'align' => 'R'],
            ['label' => 'RENDIMIENTO', 'w' => 43, 'align' => 'R'],
        ];

        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
        $this->SetLineWidth(0.2);
        $this->SetFont('Calibri', 'B', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        $colX = $sx;
        foreach ($cols as $col) {
            $this->Rect($colX, $currentY, $col['w'], 7, 'FD');
            $this->SetXY($colX, $currentY);
            $this->Cell($col['w'], 7, $this->textoCalibri($col['label']), 0, 0, $col['align']);
            $colX += $col['w'];
        }

        $currentY += 7;

        $this->SetFont('Calibri', '', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        if ($detalle->isEmpty()) {
            $this->SetDrawColor($gris[0], $gris[1], $gris[2]);
            $this->SetLineWidth(0.1);
            $this->Rect($sx, $currentY, $uw, 7);
            $this->SetXY($sx, $currentY);
            $this->Cell($uw, 7, $this->textoCalibri('No hay cargas con medición anterior disponible en este rango.'), 0, 0, 'C');
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
                $this->Cell($col['w'] - 2, 6, $this->textoCalibri($valores[$idx]), 0, 0, $col['align']);
                $colX += $col['w'];
            }

            $currentY += 6;
        }

        // Fila de TOTALES
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetFont('Calibri', 'B', 8);
        $this->SetTextColor(30, 30, 30);

        $totales = ['TOTALES', number_format($totalLitros, 2, ',', '.').' L', '', '', number_format($totalRecorrido, 2, ',', '.'), number_format($rendimientoPromedio, 2, ',', '.').' '.$unidad];

        $colX = $sx;
        foreach ($cols as $idx => $col) {
            $this->Rect($colX, $currentY, $col['w'], 7, 'FD');
            $this->SetXY($colX + 1, $currentY);
            $this->Cell($col['w'] - 2, 7, $this->textoCalibri($totales[$idx]), 0, 0, $col['align']);
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
    private function pintarPieDePagina(float $uw, array $gris, string $fontFamily = 'Calibri'): void
    {
        // El auto-salto de página (activado para que la tabla pagine si hay
        // muchas filas) dispara una página nueva en cuanto un Cell() cae
        // dentro de los últimos 15mm; el pie va a -12mm del borde inferior,
        // así que hay que apagarlo aquí o el pie termina solo en una página
        // extra en blanco.
        $this->SetAutoPageBreak(false);
        $this->SetY(-12);
        $this->SetFont($fontFamily, 'I', 8);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->Cell($uw, 4, $this->textoCalibri('Reporte generado el: '.date('d/m/Y H:i')), 0, 0, 'L');
        $this->Cell($uw, 4, $this->textoCalibri('Página: ').$this->PageNo().'/{nb}', 0, 0, 'R');
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
     * en Parámetros de la Empresa si el archivo existe en el servidor Y es
     * una imagen válida/legible, o el logo estático (logo-plus-metals.png)
     * como respaldo en cualquier otro caso.
     *
     * La validación con getimagesize() (no sólo file_exists()) es a propósito:
     * FPDF::Image() lanza una Exception (Error() en fpdf.php) si el archivo
     * no es una imagen que pueda parsear — un logo_empresa apuntando a un
     * archivo borrado, corrupto o de 0 bytes tumbaría CUALQUIER reporte con
     * un 500 en vez de imprimir con el logo por defecto. @ silencia el
     * warning nativo de getimagesize() ante un archivo inválido; el valor de
     * retorno (false) es lo que ya distingue "válido" de "no válido" acá.
     */
    private function logoEmpresa(?ParametrosEmpresa $parametrosEmpresa): string
    {
        $logoRespaldo = public_path('images/logo/logo-plus-metals.png');

        if (! $parametrosEmpresa?->logo_empresa) {
            return $logoRespaldo;
        }

        $logoConfigurado = storage_path('app/public/'.$parametrosEmpresa->logo_empresa);

        if (is_file($logoConfigurado) && @getimagesize($logoConfigurado) !== false) {
            return $logoConfigurado;
        }

        return $logoRespaldo;
    }

    /**
     * Franja informativa con los datos de la empresa (nombre, dirección,
     * teléfono, NIT), pintada como una línea centrada justo debajo del
     * encabezado del reporte. Devuelve el alto ocupado (0 si no hay datos).
     */
    private function pintarInfoEmpresa(?ParametrosEmpresa $parametrosEmpresa, float $sx, float $y, float $uw, array $gris, string $fontFamily = 'Calibri'): float
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

        $this->SetFont($fontFamily, '', 7.5);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx, $y);
        $this->Cell($uw, 4, $this->textoCalibri(implode('   |   ', $partes)), 0, 0, 'C');

        return 6;
    }

    /**
     * Registra la familia "Calibri" (regular/negrita/cursiva/negrita-cursiva)
     * para poder usarla con SetFont('Calibri', ...) igual que un core font de
     * FPDF (Arial, Times, etc.). Los 4 archivos de definición (.php + .z,
     * generados con la utilidad oficial MakeFont a partir de las Microsoft
     * Fluent Fonts) viven en public/fonts/fpdf-calibri/font/ — se cargan
     * desde ahí directamente (sin copiarlos a vendor/) para que sobrevivan a
     * un `composer update`. AddFont() ya es idempotente (no vuelve a
     * registrar un fontkey ya cargado), así que es seguro llamar este método
     * al inicio de cualquier reporte que quiera usar Calibri.
     *
     * A pedido explícito del usuario (2026-09), Calibri es ahora la ÚNICA
     * fuente de toda la clase — ya no queda ningún SetFont('Arial', ...) en
     * Reportes.php, ni siquiera en generarVale()/generarComprobanteEgreso()/
     * generarSolicitudMantenimiento()/generarReporteOperacionDiaria(), cuyas
     * coordenadas de texto se habían medido a mano sobre el ancho de
     * carácter de Arial para encajar con las cajas/líneas ya impresas en sus
     * imágenes de fondo. Calibri es en general más angosta que Arial al
     * mismo tamaño, así que el riesgo típico es texto con más aire (no
     * desbordado), pero cualquier cambio en esas 4 páginas debe verificarse
     * visualmente (renderizar a PNG, ver .ai/rules/libraries-http-controllers.md)
     * antes de tocar tamaños/posiciones, porque el ancho de carácter ya NO
     * coincide con el que se usó para medir esas coordenadas originalmente.
     */
    private function registrarFuenteCalibri(): void
    {
        $dir = public_path('fonts/fpdf-calibri/font/');

        $this->AddFont('Calibri', '', 'calibri.php', $dir);
        $this->AddFont('Calibri', 'B', 'calibrib.php', $dir);
        $this->AddFont('Calibri', 'I', 'calibrii.php', $dir);
        $this->AddFont('Calibri', 'BI', 'calibribi.php', $dir);
    }

    /**
     * Convierte UTF-8 a cp1252 (Windows-1252), la codificación con la que se
     * generaron los archivos de fuente de registrarFuenteCalibri(). A
     * diferencia del viejo helper utf8Decode() de app/Helpers/helpers.php
     * (UTF-8 -> ISO-8859-1, pensado para los core fonts de FPDF como Arial —
     * ya no se usa en esta clase), cp1252 sí conserva el guión largo (—),
     * las comillas tipográficas y el símbolo €, que Calibri incluye pero
     * ISO-8859-1 no representa. //TRANSLIT evita que un caracter fuera de
     * cp1252 rompa el reporte (se aproxima en vez de fallar), igual que hace
     * utf8Decode().
     */
    private function textoCalibri(?string $texto): string
    {
        if (empty($texto)) {
            return '';
        }

        return iconv('UTF-8', 'CP1252//TRANSLIT', $texto);
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

    /**
     * "REPORTE DE OPERACIÓN DIARIA": se dibuja sobre el fondo completo tamaño
     * carta public/images/reportes/fondo-reporte-operacion.png (mismo enfoque
     * que generarSolicitudMantenimiento() — el fondo trae impresos el
     * encabezado, la caja de OBSERVACIONES y las líneas de firma; aquí se ubica
     * el texto dinámico y se dibujan las dos tablas — "DETALLE JORNADA DIARIA DE
     * TRABAJO" y "MANTENIMIENTO REALIZADO" — que el fondo NO trae).
     *
     * Las etiquetas y columnas dependen del tipo_medicion del vehículo:
     *  - horometro: lecturas "HORÓMETRO INICIAL/FINAL"; la jornada lista LUGAR.
     *  - kilometraje: lecturas "KILOMETRAJE INICIAL/FINAL"; la jornada lista
     *    ORIGEN, DESTINO y MATERIAL trasladado.
     *
     * Coordenadas medidas sobre un render real del fondo a 215.9x279.4mm (ver
     * la nota en .ai/rules/libraries-http-controllers.md).
     *
     * @param  OperacionDiaria  $operacion  cargado con conductor.persona,
     *                                      vehiculo, area, verificador, actividadesRealizadas (+ pivot->material)
     *                                      y mantenimientosOperacion.
     * @param  Collection|array|null  $tiposMantenimiento  catálogo
     *                                                     de tipo_mantenimiento de ámbito operacion_diaria activos
     *                                                     ({id, tipo_mantenimiento, tipo_valor, unidad_medida}); la tabla de
     *                                                     mantenimiento lista todos, con su valor registrado o "—". Si no se
     *                                                     pasa, se consultan directamente de la base de datos.
     * @param  string  $modo  Ver docblock de generarVale().
     */
    public function generarReporteOperacionDiaria($operacion, $tiposMantenimiento = null, string $modo = 'I', ?string $nombreArchivo = null)
    {
        // La tabla de mantenimiento es estática: lista SIEMPRE todos los tipos
        // de mantenimiento de ámbito operacion_diaria activos registrados en la
        // base de datos, aunque esta operación no tenga ningún valor cargado.
        $tiposMantenimiento = collect($tiposMantenimiento ?? []);
        if ($tiposMantenimiento->isEmpty()) {
            $tiposMantenimiento = TipoMantenimiento::query()
                ->where('ambito', 'operacion_diaria')
                ->where('estado_tipo_mantenimiento', 'ACTIVO')
                ->orderBy('tipo_mantenimiento')
                ->get(['id', 'tipo_mantenimiento', 'tipo_valor', 'unidad_medida']);
        }

        // ══════════════════════════════════════════════════════════════════
        // DATOS
        // ══════════════════════════════════════════════════════════════════
        $vehiculo = $operacion->vehiculo;
        $esKm = $vehiculo?->tipo_medicion === 'kilometraje';

        $nro = $operacion->nro;
        $fecha = $operacion->fecha_inicio;
        $dia = $fecha?->format('d') ?? '';
        $mes = $fecha?->format('m') ?? '';
        $anio = $fecha?->format('Y') ?? '';

        $operador = $operacion->conductor?->persona?->nombre_completo ?? 'N/A';
        $descripcionEquipo = trim(
            ($vehiculo?->codigo ?? '').'   '.($vehiculo?->marca ?? '').' '.($vehiculo?->modelo ?? '')
        ) ?: 'N/A';

        $lecturaInicial = $esKm ? $operacion->kilometraje_inicio : $operacion->horometro_inicio;
        $lecturaFinal = $esKm ? $operacion->kilometraje_fin : $operacion->horometro_fin;
        $unidadLectura = $esKm ? 'km' : 'h';
        $etiquetaLectura = $esKm ? 'KILOMETRAJE' : 'HORÓMETRO';

        $observaciones = (string) ($operacion->observaciones ?? '');
        $supervisor = $operacion->verificador?->nombre_completo ?? '';

        // Actividades realizadas de la jornada.
        $actividades = $operacion->actividadesRealizadas->map(fn ($a) => [
            'de' => $a->pivot->hora_inicio?->format('H:i') ?? '',
            'a' => $a->pivot->hora_fin?->format('H:i') ?? '',
            'actividad' => (string) $a->nombre_actividad,
            'origen' => (string) ($a->pivot->origen ?? ''),
            'destino' => (string) ($a->pivot->destino ?? ''),
            'lugar' => (string) ($a->pivot->lugar ?? ''),
            'material' => (string) ($a->pivot->material?->material ?? ''),
            'cantidad' => trim($this->formatearCantidadReporte($a->pivot->cantidad).' '.($a->pivot->unidad_medida ?? '')),
        ])->values()->all();

        // Mantenimiento: SIEMPRE una fila por cada tipo activo de ámbito
        // operacion_diaria. Sin registro relacionado la fila queda vacía; los
        // 'cantidad' muestran el número + unidad, los 'booleano' una casilla
        // SÍ/NO marcada según lo registrado.
        $registrados = $operacion->mantenimientosOperacion->keyBy('id');
        $mantenimiento = $tiposMantenimiento->map(function ($tipo) use ($registrados) {
            $reg = $registrados->get($tipo->id);

            return [
                'control' => (string) $tipo->tipo_mantenimiento,
                'tipo_valor' => $tipo->tipo_valor,
                'cantidad' => ($tipo->tipo_valor === 'cantidad' && $reg && $reg->pivot->valor !== null)
                    ? trim($this->formatearCantidadReporte($reg->pivot->valor).' '.($tipo->unidad_medida ?? ''))
                    : '',
                'realizado' => ($tipo->tipo_valor === 'booleano' && $reg) ? $reg->pivot->realizado : null,
            ];
        })->all();

        // ══════════════════════════════════════════════════════════════════
        // COLORES / PÁGINA
        // ══════════════════════════════════════════════════════════════════
        // Ya no queda azul en todo lo que dibuja este método sobre el fondo
        // pre-impreso ni en las 2 tablas dinámicas (a pedido del usuario):
        // los encabezados de tabla (antes celeste) van en $grisEncabezado
        // #c9c9c9; todo el texto (N°, marca DÍA/NOCHE, etiquetas y valores de
        // los campos superiores, títulos y filas de tabla) en $negro; los
        // bordes de las 2 tablas también en $negro; y las líneas de los
        // campos OPERADOR/EQUIPO/lecturas/SUPERVISOR (pintarCampoReporte) en
        // $gris, más clara que el negro de las tablas.
        $grisEncabezado = [201, 201, 201];
        $negro = [30, 30, 30];
        $gris = [110, 110, 110];

        $pageW = 215.9;
        $pageH = 279.4;

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(false);
        $this->registrarFuenteCalibri();
        $this->Image(public_path('images/reportes/fondo-reporte-operacion.jpg'), 0, 0, $pageW, $pageH);

        $sx = 4.5;
        $uw = 207.0;

        // ══════════════════════════════════════════════════════════════════
        // ENCABEZADO (logo + N° + DÍA/MES/AÑO + casilla DÍA/NOCHE)
        // ══════════════════════════════════════════════════════════════════
        $this->Image(public_path('images/logo/logo-plus-metals.png'), 9, 7, 34);

        $this->SetFont('Calibri', 'B', 13);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY(163.8, 6);
        $this->Cell(49.1, 6, $this->textoCalibri('N° '.$nro), 0, 0, 'C');

        $this->SetFont('Calibri', '', 10);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY(163.8, 23.6);
        $this->Cell(16.7, 5, $dia, 0, 0, 'C');
        $this->SetXY(180.5, 23.6);
        $this->Cell(16.5, 5, $mes, 0, 0, 'C');
        $this->SetXY(197.0, 23.6);
        $this->Cell(15.9, 5, $anio, 0, 0, 'C');

        $this->SetFont('Calibri', 'B', 11);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        if ($operacion->turno === 'DIA') {
            $this->SetXY(83.8, 19.9);
            $this->Cell(4.6, 5, 'X', 0, 0, 'C');
        } else {
            $this->SetXY(113.0, 19.9);
            $this->Cell(4.7, 5, 'X', 0, 0, 'C');
        }

        // ══════════════════════════════════════════════════════════════════
        // CAMPOS: OPERADOR / EQUIPO / LECTURAS / HORAS
        // ══════════════════════════════════════════════════════════════════
        $medio = $sx + $uw / 2;
        $this->pintarCampoReporte($sx, 35, 30, 'OPERADOR:', mb_strtoupper($operador), $medio - 4, $negro, $gris, $negro);
        $this->pintarCampoReporte($medio, 35, 40, 'DESCRIPCIÓN EQUIPO:', mb_strtoupper($descripcionEquipo), $sx + $uw, $negro, $gris, $negro);

        $this->pintarCampoReporte($sx, 43, 44, $etiquetaLectura.' INICIAL:', $this->formatearLecturaReporte($lecturaInicial, $unidadLectura), $medio - 4, $negro, $gris, $negro);
        $this->pintarCampoReporte($medio, 43, 50, 'TOTAL HORAS TRABAJADAS:', $this->formatearLecturaReporte($operacion->horas_trabajadas, 'h'), $sx + $uw, $negro, $gris, $negro);

        $this->pintarCampoReporte($sx, 51, 44, $etiquetaLectura.' FINAL:', $this->formatearLecturaReporte($lecturaFinal, $unidadLectura), $medio - 4, $negro, $gris, $negro);
        $this->pintarCampoReporte($medio, 51, 26, 'SUPERVISOR:', mb_strtoupper($supervisor), $sx + $uw, $negro, $gris, $negro);

        // ══════════════════════════════════════════════════════════════════
        // TABLA: DETALLE JORNADA DIARIA DE TRABAJO
        // ══════════════════════════════════════════════════════════════════
        $jornadaTitleY = 58.0;

        // Filas visibles fijas: 10 (se amplía sólo si la jornada trae más
        // actividades, para no perder datos).
        $filaH = 5.6;
        $maxFilas = max(10, count($actividades));
        $filasVacias = max(0, $maxFilas - count($actividades));

        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $jornadaTitleY, $uw, 7, 'FD');
        $this->SetFont('Calibri', 'B', 9.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($sx, $jornadaTitleY);
        $this->Cell($uw, 7, $this->textoCalibri('DETALLE JORNADA DIARIA DE TRABAJO'), 0, 0, 'C');

        if ($esKm) {
            $anchos = [12, 12, 47, 37, 37, 34, 28];
            $columnas = [['de', 'C'], ['a', 'C'], ['actividad', 'L'], ['origen', 'L'], ['destino', 'L'], ['material', 'L'], ['cantidad', 'C']];
            $encabezados = ['DE', 'A', 'ACTIVIDAD', 'ORIGEN', 'DESTINO', 'MATERIAL', 'CANTIDAD'];
        } else {
            $anchos = [14, 14, 105, 46, 28];
            $columnas = [['de', 'C'], ['a', 'C'], ['actividad', 'L'], ['lugar', 'L'], ['cantidad', 'C']];
            $encabezados = ['DE', 'A', 'ACTIVIDAD', 'LUGAR', 'CANTIDAD'];
        }

        $filas = array_merge(
            $actividades,
            array_fill(0, $filasVacias, array_fill_keys(array_column($columnas, 0), ''))
        );

        $this->SetMargins(0, 0, 0);
        $this->SetXY($sx, $jornadaTitleY + 7);
        $tabla = new easyTable($this, '{'.implode(',', $anchos).'}', "width:{$uw}; border:1; border-color:{$negro[0]},{$negro[1]},{$negro[2]}; border-width:0.25; font-family:Calibri; valign:M; paddingX:1.5; paddingY:0.3; min-height:{$filaH};");
        $tabla->rowStyle("bgcolor:{$grisEncabezado[0]},{$grisEncabezado[1]},{$grisEncabezado[2]}; font-color:{$negro[0]},{$negro[1]},{$negro[2]}; font-style:B; font-size:7.5; align:C; min-height:4.6;");
        foreach ($encabezados as $encabezado) {
            $tabla->easyCell($this->textoCalibri($encabezado), 'align:C;');
        }
        $tabla->printRow(true);

        $this->SetFont('Calibri', '', 7.5);
        foreach ($filas as $fila) {
            $tabla->rowStyle("font-color:30,30,30; font-size:7.5; min-height:{$filaH};");
            foreach ($columnas as [$clave, $align]) {
                $tabla->easyCell($this->textoCalibri((string) ($fila[$clave] ?? '')), 'align:'.$align.';');
            }
            $tabla->printRow();
        }
        $tabla->endTable(2);
        $this->SetMargins(8, 8, 8);

        // ══════════════════════════════════════════════════════════════════
        // TABLA: MANTENIMIENTO REALIZADO (angosta y centrada, como el modelo)
        // ══════════════════════════════════════════════════════════════════
        $mantTitleY = $this->GetY() + 3;
        $mantX = $sx + 22;
        $mantW = $uw - 44;

        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($mantX, $mantTitleY, $mantW, 7, 'FD');
        $this->SetFont('Calibri', 'B', 9.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetXY($mantX, $mantTitleY);
        $this->Cell($mantW, 7, $this->textoCalibri('MANTENIMIENTO REALIZADO'), 0, 0, 'C');

        $mantBodyY = $mantTitleY + 7;

        if (count($mantenimiento) === 0) {
            $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
            $this->SetLineWidth(0.25);
            $this->Rect($mantX, $mantBodyY, $mantW, 7);
            $this->SetFont('Calibri', 'I', 8);
            $this->SetTextColor($gris[0], $gris[1], $gris[2]);
            $this->SetXY($mantX, $mantBodyY);
            $this->Cell($mantW, 7, $this->textoCalibri('Sin controles de mantenimiento configurados.'), 0, 0, 'C');
            $this->SetY($mantBodyY + 7);
        } else {
            // Alto de fila adaptado para no invadir la caja de OBSERVACIONES.
            $rowH = max(4.0, min(5.4, (221.0 - $mantBodyY) / count($mantenimiento)));
            $colValW = 54;
            $colNameW = $mantW - $colValW;
            $valX = $mantX + $colNameW;
            $y = $mantBodyY;

            foreach ($mantenimiento as $item) {
                $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
                $this->SetLineWidth(0.25);
                $this->Rect($mantX, $y, $colNameW, $rowH);
                $this->Rect($valX, $y, $colValW, $rowH);

                $this->SetFont('Calibri', 'B', 8);
                $this->SetTextColor($negro[0], $negro[1], $negro[2]);
                $this->SetXY($mantX + 2.5, $y);
                $this->Cell($colNameW - 5, $rowH, $this->textoCalibri($item['control']), 0, 0, 'L');

                if ($item['tipo_valor'] === 'booleano') {
                    $this->dibujarCasillaSiNo($valX, $y, $colValW, $rowH, $item['realizado'], $negro, $negro);
                } else {
                    $this->SetFont('Calibri', '', 8);
                    $this->SetTextColor($negro[0], $negro[1], $negro[2]);
                    $this->SetXY($valX, $y);
                    $this->Cell($colValW, $rowH, $this->textoCalibri($item['cantidad']), 0, 0, 'C');
                }

                $y += $rowH;
            }
            $this->SetY($y);
        }

        // ══════════════════════════════════════════════════════════════════
        // OBSERVACIONES (caja del fondo; texto dentro de y≈230-249)
        // ══════════════════════════════════════════════════════════════════
        if ($observaciones !== '') {
            $this->SetFont('Calibri', '', 8.5);
            $this->SetTextColor($negro[0], $negro[1], $negro[2]);
            $this->SetXY($sx + 2, 231.5);
            $this->MultiCell($uw - 4, 4.5, $this->textoCalibri($observaciones), 0, 'L');
        }

        // Las líneas de firma vienen impresas en el fondo (OPERADOR y
        // SUPERVISOR); los nombres NO se imprimen encima (se firman a mano).

        return $this->Output($modo, $nombreArchivo ?? 'reporte_operacion_'.$nro.'.pdf');
    }

    /**
     * Formatea una lectura/cantidad (decimal:2 → string|float|null) para el
     * reporte de operación diaria. Devuelve '—' cuando no hay valor.
     */
    private function formatearLecturaReporte($valor, string $unidad = ''): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return number_format((float) $valor, 2, ',', '.').($unidad !== '' ? ' '.$unidad : '');
    }

    /**
     * Cantidad sin decimales redundantes (146, 6,50) para las columnas de la
     * tabla de jornada / mantenimiento.
     */
    private function formatearCantidadReporte($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        $n = (float) $valor;

        return $n == (int) $n ? (string) (int) $n : number_format($n, 2, ',', '.');
    }

    /**
     * Etiqueta en negrita + línea de campo + valor a la derecha (patrón de
     * los campos superiores del reporte de operación diaria). La etiqueta y
     * el valor van en $colorTexto (negro, a pedido del usuario — antes la
     * etiqueta iba en azul); la línea del campo va en $colorLinea (gris, a
     * pedido del usuario — antes también azul), separado del texto porque
     * ambos podían ser colores distintos.
     */
    private function pintarCampoReporte(float $x, float $y, float $labelW, string $label, string $valor, float $xFin, array $colorTexto, array $colorLinea, array $colorValor): void
    {
        $h = 5;
        $this->SetFont('Calibri', 'B', 8.5);
        $this->SetTextColor($colorTexto[0], $colorTexto[1], $colorTexto[2]);
        $this->SetXY($x, $y);
        $this->Cell($labelW, $h, $this->textoCalibri($label), 0, 0, 'L');

        $this->SetDrawColor($colorLinea[0], $colorLinea[1], $colorLinea[2]);
        $this->SetLineWidth(0.2);
        $this->Line($x + $labelW, $y + $h - 0.5, $xFin, $y + $h - 0.5);

        $this->SetFont('Calibri', '', 8.5);
        $this->SetTextColor($colorValor[0], $colorValor[1], $colorValor[2]);
        $this->SetXY($x + $labelW + 1.5, $y);
        $this->Cell($xFin - $x - $labelW - 2, $h, $this->textoCalibri($valor), 0, 0, 'L');
    }

    /**
     * Casilla "SÍ [ ]  NO [ ]" centrada en la celda de valor de un control de
     * mantenimiento de tipo booleano. Marca con "X" la opción registrada
     * ('SI' / 'NO'); si no hay registro deja ambas casillas vacías.
     */
    private function dibujarCasillaSiNo(float $x, float $y, float $w, float $h, ?string $realizado, array $azul, array $negro): void
    {
        $box = 3.0;
        $siBoxX = 5.5;
        $noBoxX = 22.0;
        $grupoW = $noBoxX + $box;
        $gx = $x + ($w - $grupoW) / 2;
        $cy = $y + ($h - $box) / 2;

        $this->SetFont('Calibri', 'B', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);
        $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
        $this->SetLineWidth(0.25);

        $this->SetXY($gx, $y);
        $this->Cell($siBoxX - 0.5, $h, $this->textoCalibri('SÍ'), 0, 0, 'L');
        $this->Rect($gx + $siBoxX, $cy, $box, $box);

        $this->SetXY($gx + $siBoxX + $box + 3, $y);
        $this->Cell($noBoxX - ($siBoxX + $box + 3) - 0.5, $h, 'NO', 0, 0, 'L');
        $this->Rect($gx + $noBoxX, $cy, $box, $box);

        if ($realizado === 'SI' || $realizado === 'NO') {
            $marcaX = $realizado === 'SI' ? $gx + $siBoxX : $gx + $noBoxX;
            $this->SetFont('Calibri', 'B', 8);
            $this->SetTextColor($azul[0], $azul[1], $azul[2]);
            $this->SetXY($marcaX - 0.4, $y);
            $this->Cell($box + 0.8, $h, 'X', 0, 0, 'C');
        }

        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
    }

    /**
     * Reporte de uso de vehículos en operación diaria: una fila por vehículo con
     * las horas trabajadas y el recorrido (kilometraje u horómetro según su
     * tipo_medicion) del rango, más una fila de totales. NO desglosa actividades.
     *
     * $resumen es lo que devuelve OperacionDiariaReportController::obtenerResumen()
     * (['vehiculos' => Collection, 'totales' => array]); todo ya viene agregado
     * de la base de datos, aquí sólo se dibuja.
     *
     * @param  array{vehiculos: iterable<array<string, mixed>>, totales: array<string, float|int>}  $resumen
     * @param  array{tipo_combustible?: string|null, area?: string|null, vehiculo?: string|null}  $filtrosAplicados
     */
    public function generarReporteOperacionDiariaUso(array $resumen, $fechaInicio, $fechaFin, string $modo = 'I', ?string $nombreArchivo = null, array $filtrosAplicados = [])
    {
        $vehiculos = collect($resumen['vehiculos'] ?? []);
        $totales = $resumen['totales'] ?? [];

        $azul = [39, 42, 84];
        $grisEncabezado = [201, 201, 201];
        $verde = [24, 125, 170];
        $negro = [30, 30, 30];
        $gris = [90, 90, 90];
        $filaAlterna = [244, 246, 250];

        $parametrosEmpresa = $this->parametrosEmpresa();

        $this->AddPage('P', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(true, 15);
        $this->registrarFuenteCalibri();

        $sx = 8;
        $sy = 8;
        $uw = 199.9;

        // ── ENCABEZADO ──────────────────────────────────────────────────
        $etiquetasFiltro = array_filter([
            ! empty($filtrosAplicados['tipo_combustible']) ? 'Combustible: '.$filtrosAplicados['tipo_combustible'] : null,
            ! empty($filtrosAplicados['area']) ? 'Área: '.$filtrosAplicados['area'] : null,
            ! empty($filtrosAplicados['vehiculo']) ? 'Vehículos: '.$filtrosAplicados['vehiculo'] : null,
        ]);
        $h1 = $etiquetasFiltro ? 22 : 16;

        $this->SetFillColor($verde[0], $verde[1], $verde[2]);
        $this->Rect($sx, $sy, $uw, 1.2, 'F');

        $this->SetLineWidth(0.5);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $sy + 1.2, $uw, $h1 - 1.2);

        $this->Image($this->logoEmpresa($parametrosEmpresa), $sx + 4, $sy + 3, 35);

        $this->SetFont('Calibri', 'B', 14);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy + 1.5);
        $this->Cell($uw - 40, 8, $this->textoCalibri('REPORTE DE USO DE VEHÍCULOS - OPERACIÓN DIARIA'), 0, 2, 'L');

        $this->SetFont('Calibri', '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx + 40, $sy + 9.5);
        $this->Cell($uw - 40, 6, $this->textoCalibri('Del '.date('d/m/Y', strtotime($fechaInicio)).' al '.date('d/m/Y', strtotime($fechaFin))), 0, 2, 'L');

        if ($etiquetasFiltro) {
            $this->SetFont('Calibri', 'BI', 8);
            $this->SetTextColor($verde[0], $verde[1], $verde[2]);
            $this->SetXY($sx + 40, $sy + 16);
            $this->Cell($uw - 40, 5, $this->textoCalibri('Filtros aplicados: '.implode('   |   ', $etiquetasFiltro)), 0, 2, 'L');
        }

        $currentY = $sy + $h1 + 2;
        $currentY += $this->pintarInfoEmpresa($parametrosEmpresa, $sx, $currentY, $uw, $gris);
        $currentY += 3;

        // ── TARJETAS DE RESUMEN ─────────────────────────────────────────
        $cardW = 48;
        $cardH = 14;
        $cards = [
            ['VEHÍCULOS OPERADOS', number_format((int) ($totales['total_vehiculos'] ?? 0)), [59, 89, 152]],
            ['OPERACIONES', number_format((int) ($totales['total_operaciones'] ?? 0)), [34, 177, 76]],
            ['HORAS TRABAJADAS', number_format((float) ($totales['total_horas'] ?? 0), 2, ',', '.').' h', [192, 0, 0]],
            ['RECORRIDO (KM / H)', number_format((float) ($totales['total_km'] ?? 0), 2, ',', '.').' / '.number_format((float) ($totales['total_horometro'] ?? 0), 2, ',', '.'), [155, 155, 155]],
        ];

        $cardX = $sx;
        foreach ($cards as $card) {
            $this->SetLineWidth(0.3);
            $this->SetDrawColor($card[2][0], $card[2][1], $card[2][2]);
            $this->SetFillColor($card[2][0], $card[2][1], $card[2][2]);
            $this->Rect($cardX, $currentY, $cardW, $cardH, 'FD');

            $this->SetFont('Calibri', 'B', 7);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($cardX, $currentY);
            $this->Cell($cardW, 5, $this->textoCalibri($card[0]), 0, 1, 'C');

            $this->SetFont('Calibri', 'B', 9);
            $this->SetXY($cardX, $currentY + 5);
            $this->Cell($cardW, 9, $this->textoCalibri($card[1]), 0, 1, 'C');

            $cardX += $cardW + 2;
        }

        $currentY += $cardH + 8;

        // ── TABLA DE VEHÍCULOS ──────────────────────────────────────────
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetLineWidth(0.3);
        $this->Rect($sx, $currentY, $uw, 8, 'FD');

        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor(30, 30, 30);
        $this->SetXY($sx, $currentY);
        $this->Cell($uw, 8, $this->textoCalibri('DETALLE POR VEHÍCULO'), 0, 1, 'C');

        $currentY += 8;

        $cols = [
            ['label' => 'CÓDIGO', 'w' => 20, 'align' => 'L'],
            ['label' => 'PLACA', 'w' => 20, 'align' => 'C'],
            ['label' => 'COMBUSTIBLE', 'w' => 26, 'align' => 'C'],
            ['label' => 'MEDICIÓN', 'w' => 22, 'align' => 'C'],
            ['label' => 'OPERAC.', 'w' => 18, 'align' => 'C'],
            ['label' => 'DÍAS', 'w' => 14, 'align' => 'C'],
            ['label' => 'HORAS TRAB.', 'w' => 24, 'align' => 'R'],
            ['label' => 'PROM. H/OP', 'w' => 20, 'align' => 'R'],
            ['label' => 'RECORRIDO', 'w' => 35.9, 'align' => 'R'],
        ];

        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($negro[0], $negro[1], $negro[2]);
        $this->SetLineWidth(0.2);
        $this->SetFont('Calibri', 'B', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        $colX = $sx;
        foreach ($cols as $col) {
            $this->Rect($colX, $currentY, $col['w'], 7, 'FD');
            $this->SetXY($colX, $currentY);
            $this->Cell($col['w'], 7, $this->textoCalibri($col['label']), 0, 0, $col['align']);
            $colX += $col['w'];
        }

        $currentY += 7;

        $this->SetFont('Calibri', '', 7.5);
        $this->SetTextColor($negro[0], $negro[1], $negro[2]);

        if ($vehiculos->isEmpty()) {
            $this->SetDrawColor($gris[0], $gris[1], $gris[2]);
            $this->SetLineWidth(0.1);
            $this->Rect($sx, $currentY, $uw, 7);
            $this->SetXY($sx, $currentY);
            $this->Cell($uw, 7, $this->textoCalibri('No hay operaciones diarias en el rango seleccionado.'), 0, 0, 'C');
            $currentY += 7;
        }

        foreach ($vehiculos->values() as $fila => $v) {
            $v = (object) $v;
            $medicionLabel = $v->tipo_medicion === 'kilometraje' ? 'Kilometraje' : 'Horómetro';

            $valores = [
                $v->codigo,
                $v->nro_placa,
                $v->tipo_combustible,
                $medicionLabel,
                (string) $v->total_operaciones,
                (string) $v->dias_operados,
                number_format((float) $v->total_horas, 2, ',', '.').' h',
                number_format((float) $v->promedio_horas, 2, ',', '.').' h',
                number_format((float) $v->total_recorrido, 2, ',', '.').' '.$v->unidad_recorrido,
            ];

            $conFondo = $fila % 2 === 1;
            $this->SetFillColor($filaAlterna[0], $filaAlterna[1], $filaAlterna[2]);

            $colX = $sx;
            foreach ($cols as $idx => $col) {
                $this->SetDrawColor($gris[0], $gris[1], $gris[2]);
                $this->SetLineWidth(0.1);
                $this->Rect($colX, $currentY, $col['w'], 6, $conFondo ? 'FD' : 'D');
                $this->SetXY($colX + 1, $currentY + 0.5);
                $this->Cell($col['w'] - 2, 6, $this->textoCalibri($valores[$idx]), 0, 0, $col['align']);
                $colX += $col['w'];
            }

            $currentY += 6;
        }

        // Fila de TOTALES (sólo lo que es sumable entre vehículos: el promedio y
        // el recorrido de distinta unidad no se totalizan por fila).
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetDrawColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->SetFont('Calibri', 'B', 8);
        $this->SetTextColor(30, 30, 30);

        $totalRecorrido = number_format((float) ($totales['total_km'] ?? 0), 2, ',', '.').' km / '
            .number_format((float) ($totales['total_horometro'] ?? 0), 2, ',', '.').' h';

        $totalesFila = [
            'TOTALES',
            '',
            '',
            '',
            (string) ($totales['total_operaciones'] ?? 0),
            '',
            number_format((float) ($totales['total_horas'] ?? 0), 2, ',', '.').' h',
            '',
            $totalRecorrido,
        ];

        $colX = $sx;
        foreach ($cols as $idx => $col) {
            $this->Rect($colX, $currentY, $col['w'], 7, 'FD');
            $this->SetXY($colX + 1, $currentY);
            $this->Cell($col['w'] - 2, 7, $this->textoCalibri($totalesFila[$idx]), 0, 0, $col['align']);
            $colX += $col['w'];
        }

        $this->pintarPieDePagina($uw, $gris);

        return $this->Output($modo, $nombreArchivo ?? 'reporte_operacion_diaria_uso.pdf');
    }

    /**
     * Bitácora detallada de UN vehículo (una fila por operación diaria): horas
     * trabajadas, combustible cargado ese día, controles de mantenimiento y
     * material trasladado. Las columnas de mantenimiento y de material son
     * dinámicas (dependen de lo registrado en el rango), por eso la tabla va
     * en horizontal (Letter apaisado) con anchos proporcionales.
     *
     * $datos es lo que devuelve OperacionDiariaReportController::obtenerDetalleVehiculo()
     * (['tipo_medicion','columnas'=>['mantenimiento','material'],'filas','totales']);
     * todo llega ya agregado de la base de datos.
     */
    public function generarReporteOperacionDiariaDetalle($vehiculo, array $datos, $fechaInicio, $fechaFin, string $modo = 'I', ?string $nombreArchivo = null)
    {
        $filas = collect($datos['filas'] ?? []);
        $colsMant = collect($datos['columnas']['mantenimiento'] ?? []);
        $colsMat = collect($datos['columnas']['material'] ?? []);
        $totales = ($datos['totales'] ?? []) + [
            'operaciones' => 0,
            'horas_trabajadas' => 0,
            'litros' => 0,
            'costo' => 0,
            'litros_por_hora' => 0,
        ];

        $esKilometraje = ($datos['tipo_medicion'] ?? $vehiculo->tipo_medicion) === 'kilometraje';
        $lectura = $esKilometraje ? 'KILOMETRAJE' : 'HORÓMETRO';

        $azul = [39, 42, 84];
        $grisEncabezado = [201, 201, 201];
        $verde = [24, 125, 170];
        $gris = [90, 90, 90];

        $num = fn ($v, int $d = 2): string => $v === null ? '-' : number_format((float) $v, $d, ',', '.');
        $bs = fn ($v): string => $v === null ? '-' : 'Bs. '.number_format((float) $v, 2, ',', '.');
        $fecha = fn ($v): string => $v ? date('d/m/Y', strtotime((string) $v)) : '-';

        $parametrosEmpresa = $this->parametrosEmpresa();

        $this->AddPage('L', 'Letter');
        $this->SetMargins(8, 8, 8);
        $this->SetAutoPageBreak(true, 15);
        $this->registrarFuenteCalibri();

        $sx = 8;
        $sy = 8;
        $uw = 263.4;

        // ── Encabezado ──────────────────────────────────────────────────
        // Sin fondos sólidos para ahorrar tinta: filete de acento fino arriba y
        // el recuadro sólo con borde (sin relleno).
        $h1 = 22;
        $this->SetFillColor($verde[0], $verde[1], $verde[2]);
        $this->Rect($sx, $sy, $uw, 0.5, 'F');
        $this->SetLineWidth(0.5);
        $this->SetDrawColor($azul[0], $azul[1], $azul[2]);
        $this->Rect($sx, $sy, $uw, $h1);
        $this->Image($this->logoEmpresa($parametrosEmpresa), $sx + 4, $sy + 3, 34);

        $this->SetFont('Calibri', 'B', 13);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy + 1.5);
        $this->Cell($uw - 40, 8, $this->textoCalibri('DETALLE DE HORAS TRABAJADAS Y CONSUMO DE COMBUSTIBLE'), 0, 2, 'L');

        $this->SetFont('Calibri', '', 9);
        $this->SetTextColor($gris[0], $gris[1], $gris[2]);
        $this->SetXY($sx + 40, $sy + 9.5);
        $this->Cell($uw - 40, 6, $this->textoCalibri('Del '.date('d/m/Y', strtotime($fechaInicio)).' al '.date('d/m/Y', strtotime($fechaFin))), 0, 2, 'L');

        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor($azul[0], $azul[1], $azul[2]);
        $this->SetXY($sx + 40, $sy + 15.5);
        $combustible = $vehiculo->tipoCombustible?->tipo_combustible ? '   |   '.$vehiculo->tipoCombustible->tipo_combustible : '';
        $this->Cell($uw - 40, 5, $this->textoCalibri('Vehículo: '.$vehiculo->codigo.' - '.($vehiculo->nro_placa ?: 'Sin placa').'   |   '.trim($vehiculo->marca.' '.$vehiculo->modelo).$combustible), 0, 2, 'L');

        $currentY = $sy + $h1 + 2;
        $currentY += $this->pintarInfoEmpresa($parametrosEmpresa, $sx, $currentY, $uw, $gris);
        $currentY += 3;

        // ── Tabla: anchos proporcionales (se reparten en $uw) ────────────
        $pesos = [
            2.0,
            4.2,
            1.3,
            2.2,
            2.2,
            1.6,   // TRABAJO DE EQUIPO (6)
            1.8,
            1.5,
            2.2,
            2.2,              // COMBUSTIBLE (4)
        ];
        foreach ($colsMant as $c) {
            $pesos[] = 2.3;
        }
        foreach ($colsMat as $c) {
            $pesos[] = 2.3;
        }
        $pesos[] = 3.6;                       // OBSERVACIONES

        $sumaPesos = array_sum($pesos);
        $anchos = array_map(fn ($p) => round($p / $sumaPesos * $uw, 2), $pesos);
        // El ancho real de la tabla = suma exacta de columnas (evita que easyTable
        // reciba un width mayor que la suma y descuadre la última columna).
        $anchoTabla = round(array_sum($anchos), 2);
        $anchoStr = '{'.implode(', ', $anchos).'}';

        $this->SetXY($sx, $currentY);
        $tabla = new easyTable($this, $anchoStr, "width:{$anchoTabla}; border:1; border-color:30,30,30; border-width:0.2; font-family:Calibri; valign:M; paddingX:1; paddingY:0.6; min-height:5;");

        // Fila de grupos (colspans): fondo celeste claro (poca tinta), texto de
        // acento en negrita.
        $tabla->rowStyle('bgcolor:201,201,201; font-style:B; font-size:6.5; font-color:30,30,30;');
        $tabla->easyCell($this->textoCalibri('TRABAJO DE EQUIPO'), 'align:C; colspan:6;');
        $tabla->easyCell($this->textoCalibri('CONSUMO Y COSTO DE COMBUSTIBLE'), 'align:C; colspan:4;');
        if ($colsMant->isNotEmpty()) {
            $tabla->easyCell($this->textoCalibri('MANTENIMIENTO'), 'align:C; colspan:'.$colsMant->count().';');
        }
        if ($colsMat->isNotEmpty()) {
            $tabla->easyCell($this->textoCalibri('MATERIAL TRASLADADO'), 'align:C; colspan:'.$colsMat->count().';');
        }
        $tabla->easyCell($this->textoCalibri('OBSERVACIONES'), 'align:C;');
        $tabla->printRow(true);

        // Fila de encabezados de columna: celeste un poco más claro.
        $tabla->rowStyle('bgcolor:201,201,201; font-style:B; font-size:6; font-color:30,30,30;');
        $encabezados = [
            ['FECHA', 'L'],
            ['OPERADOR', 'L'],
            ['N° PARTE', 'C'],
            [$lectura.' INICIAL', 'R'],
            [$lectura.' FINAL', 'R'],
            ['TOTAL HORAS', 'R'],
            ['LITROS DIÉSEL', 'R'],
            ['C / LITRO', 'R'],
            ['COSTO BS.', 'R'],
            [$lectura.' DE CARGA', 'R'],
        ];
        foreach ($encabezados as [$txt, $al]) {
            $tabla->easyCell($this->textoCalibri($txt), "align:{$al};");
        }
        foreach ($colsMant as $c) {
            $u = $c['unidad_medida'] ? ' ('.$c['unidad_medida'].')' : '';
            $tabla->easyCell($this->textoCalibri(mb_strtoupper($c['nombre'].$u)), 'align:R;');
        }
        foreach ($colsMat as $c) {
            $tabla->easyCell($this->textoCalibri(mb_strtoupper($c['nombre'])), 'align:R;');
        }
        $tabla->easyCell($this->textoCalibri(''), 'align:L;');
        $tabla->printRow(true);

        // Filas de datos.
        if ($filas->isEmpty()) {
            $tabla->rowStyle('font-size:7; font-color:'.implode(',', $gris).';');
            $tabla->easyCell($this->textoCalibri('El vehículo no tiene operaciones diarias en el rango seleccionado.'), 'align:C; colspan:'.count($anchos).';');
            $tabla->printRow();
        }

        foreach ($filas as $fila) {
            $carga = $fila['carga'] ?? null;

            $tabla->rowStyle('font-style:; font-size:6; font-color:30,30,30;');
            $tabla->easyCell($this->textoCalibri($fecha($fila['fecha'] ?? null)), 'align:L;');
            $tabla->easyCell($this->textoCalibri($fila['operador'] ?: '-'), 'align:L;');
            $tabla->easyCell($this->textoCalibri((string) ($fila['nro_parte'] ?? '-')), 'align:C;');
            $tabla->easyCell($this->textoCalibri($num($fila['lectura_inicio'] ?? null)), 'align:R;');
            $tabla->easyCell($this->textoCalibri($num($fila['lectura_fin'] ?? null)), 'align:R;');
            $tabla->easyCell($this->textoCalibri($num($fila['horas_trabajadas'] ?? 0)), 'align:R;');
            $tabla->easyCell($this->textoCalibri($carga ? $num($carga['litros']) : '-'), 'align:R;');
            $tabla->easyCell($this->textoCalibri($carga ? $num($carga['precio_unitario']) : '-'), 'align:R;');
            $tabla->easyCell($this->textoCalibri($carga ? $bs($carga['costo']) : '-'), 'align:R;');
            $tabla->easyCell($this->textoCalibri($carga ? $num($carga['lectura_carga']) : '-'), 'align:R;');

            foreach ($colsMant as $c) {
                $m = $fila['mantenimientos'][$c['id']] ?? null;
                if ($m === null) {
                    $valor = '-';
                } elseif ($c['tipo_valor'] === 'booleano') {
                    $valor = ($m['realizado'] ?? null) === 'SI' ? 'Sí' : (($m['realizado'] ?? null) === 'NO' ? 'No' : '-');
                } else {
                    $valor = ($m['valor'] ?? null) === null ? '-' : $num($m['valor']);
                }
                $tabla->easyCell($this->textoCalibri($valor), 'align:R;');
            }

            foreach ($colsMat as $c) {
                $cant = $fila['materiales'][$c['id']] ?? null;
                $tabla->easyCell($this->textoCalibri($cant === null ? '-' : $num($cant)), 'align:R;');
            }

            $tabla->easyCell($this->textoCalibri($fila['observaciones'] ?: '-'), 'align:L;');
            $tabla->printRow();
        }

        // Fila de TOTALES: fondo amarillo claro para destacar el cierre de la
        // tabla, en la misma línea que el recuadro RESUMEN.
        if ($filas->isNotEmpty()) {
            $tabla->rowStyle('bgcolor:201,201,201; font-style:B; font-size:6; font-color:30,30,30;');
            $tabla->easyCell($this->textoCalibri('TOTALES'), 'align:R; colspan:5;');
            $tabla->easyCell($this->textoCalibri($num($totales['horas_trabajadas'])), 'align:R;');
            $tabla->easyCell($this->textoCalibri($num($totales['litros'])), 'align:R;');
            $tabla->easyCell($this->textoCalibri(''), 'align:R;');
            $tabla->easyCell($this->textoCalibri($bs($totales['costo'])), 'align:R;');
            $tabla->easyCell($this->textoCalibri(''), 'align:R;');
            foreach ($colsMant as $c) {
                $tot = ($c['tipo_valor'] ?? null) === 'booleano' ? ($c['total'].' sí') : $num($c['total']);
                $tabla->easyCell($this->textoCalibri($tot), 'align:R;');
            }
            foreach ($colsMat as $c) {
                $tabla->easyCell($this->textoCalibri($num($c['total'])), 'align:R;');
            }
            $tabla->easyCell($this->textoCalibri(''), 'align:L;');
            $tabla->printRow();
        }

        $tabla->endTable();

        // ── Recuadro RESUMEN ────────────────────────────────────────────
        $y = $this->GetY() + 4;
        if ($y > 165) {
            $this->AddPage('L', 'Letter');
            $y = $this->GetY();
        }

        $boxW = 128;
        $this->SetDrawColor(30, 30, 30);
        $this->SetLineWidth(0.3);
        // Encabezado del recuadro con fondo gris #c9c9c9 (poca tinta).
        $this->SetFillColor($grisEncabezado[0], $grisEncabezado[1], $grisEncabezado[2]);
        $this->Rect($sx, $y, $boxW, 7, 'FD');
        $this->SetFont('Calibri', 'B', 9);
        $this->SetTextColor(30, 30, 30);
        $this->SetXY($sx, $y);
        $this->Cell($boxW, 7, $this->textoCalibri('RESUMEN'), 0, 0, 'C');

        $lineas = [
            ['TOTAL HORAS TRABAJADAS', $num($totales['horas_trabajadas']).' horas'],
            ['CONSUMO DE COMBUSTIBLE', $num($totales['litros']).' litros de diésel'],
            ['COSTO COMBUSTIBLE', $bs($totales['costo'])],
            ['CONSUMO DE COMBUSTIBLE POR HORA DE TRABAJO', $num($totales['litros_por_hora']).' litros/hora'],
        ];

        $ly = $y + 7;
        foreach ($lineas as [$et, $val]) {
            $this->Rect($sx, $ly, $boxW, 7);
            $this->SetFont('Calibri', 'B', 7.5);
            $this->SetTextColor(30, 30, 30);
            $this->SetXY($sx + 2, $ly);
            $this->Cell($boxW * 0.62, 7, $this->textoCalibri($et.' :'), 0, 0, 'L');
            $this->SetFont('Calibri', 'B', 8);
            $this->SetTextColor(30, 30, 30);
            $this->Cell($boxW * 0.36, 7, $this->textoCalibri($val), 0, 0, 'R');
            $ly += 7;
        }

        $this->pintarPieDePagina($uw, $gris);

        return $this->Output($modo, $nombreArchivo ?? 'bitacora_operacion_diaria.pdf');
    }
}

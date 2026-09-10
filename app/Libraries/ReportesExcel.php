<?php

namespace App\Libraries;

use App\Models\ParametrosEmpresa;
use App\Models\Vehiculo;
use App\Models\VehiculoExterno;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Versión en Excel (.xlsx) de los reportes que App\Libraries\Reportes genera
 * en PDF. Es un espejo del PDF, no un reemplazo: recibe exactamente los mismos
 * arrays/colecciones ya agregados que le pasan los controladores al PDF, con
 * las mismas columnas y los mismos totales, y NO consulta la base de datos.
 *
 * Diferencias deliberadas respecto del PDF:
 *  - Los importes/cantidades se escriben como números reales con formato de
 *    celda (no como texto ya formateado), para que se puedan sumar, ordenar y
 *    llevar a una tabla dinámica.
 *  - Cada tabla lleva panel inmovilizado y autofiltro sobre su encabezado.
 *
 * Todos los métodos públicos devuelven el contenido binario del .xlsx (igual
 * que el modo 'S' de FPDF), para que el controlador lo entregue con response().
 */
class ReportesExcel
{
    /** Misma paleta que los PDF de Reportes.php (azul del título del reporte y acento). */
    private const AZUL = '272A54';

    private const CELESTE = '187DAA';

    /**
     * Relleno de toda la "chrome" de las tablas (barra de sección, encabezado
     * de columnas, fila de grupos y fila de TOTALES): gris #c9c9c9, por
     * convención de la empresa — antes era azul. El texto sobre este relleno
     * va en negro para que resalte (ver self::TEXTO).
     */
    private const GRIS_RELLENO = 'C9C9C9';

    private const FILA_ALTERNA = 'F4F6FA';

    private const TEXTO = '1E1E1E';

    private const GRIS_TEXTO = '5A5A5A';

    public const FORMATO_ENTERO = '#,##0';

    public const FORMATO_DECIMAL = '#,##0.00';

    public const FORMATO_MONEDA = '"Bs. "#,##0.00';

    /** Nº de filas que ocupa el bloque de encabezado antes del contenido. */
    private const FILA_CONTENIDO = 7;

    /* =====================================================================
     |  Reportes de cargas de combustible
     | ================================================================== */

    /**
     * Reporte de cargas de combustible (resumen por vehículo), espejo de
     * Reportes::generarReporteCargasCombustible().
     *
     * @param  array{total_litros: float, total_costo: float, cantidad_cargas: int, vehiculos: iterable<int, array<string, mixed>>}  $resumen
     * @param  array{tipo_vehiculo?: ?string}  $filtrosAplicados
     */
    public function generarReporteCargasCombustible(array $resumen, $fechaInicio, $fechaFin, array $filtrosAplicados = []): string
    {
        $vehiculos = collect($resumen['vehiculos'] ?? []);
        $totalLitros = (float) ($resumen['total_litros'] ?? 0);
        $totalCosto = (float) ($resumen['total_costo'] ?? 0);
        $cantidadCargas = (int) ($resumen['cantidad_cargas'] ?? 0);

        $libro = $this->crearLibro('Reporte de cargas de combustible');
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Cargas de combustible');

        $columnas = [
            ['label' => 'CÓD. CONTABLE', 'ancho' => 18, 'align' => 'left'],
            ['label' => 'PLACA', 'ancho' => 14, 'align' => 'center'],
            ['label' => 'MARCA', 'ancho' => 22, 'align' => 'left'],
            ['label' => 'TOTAL LITROS', 'ancho' => 14, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'TOTAL COSTO (Bs.)', 'ancho' => 18, 'align' => 'right', 'formato' => self::FORMATO_MONEDA],
            ['label' => 'NRO. CARGAS', 'ancho' => 13, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'PRECIO PROM.', 'ancho' => 14, 'align' => 'right', 'formato' => self::FORMATO_MONEDA],
        ];

        $fila = $this->pintarEncabezado(
            $hoja,
            'REPORTE DE CARGAS DE COMBUSTIBLE',
            'Del '.$this->fecha($fechaInicio).' al '.$this->fecha($fechaFin),
            $this->lineasDeFiltros(['Tipo de vehículo' => $filtrosAplicados['tipo_vehiculo'] ?? null]),
            count($columnas),
        );

        $fila = $this->pintarTarjetas($hoja, $fila, [
            ['TOTAL LITROS', $totalLitros, self::FORMATO_DECIMAL],
            ['TOTAL COSTO', $totalCosto, self::FORMATO_MONEDA],
            ['COSTO / LITRO', $totalLitros > 0 ? round($totalCosto / $totalLitros, 2) : 0, self::FORMATO_MONEDA],
            ['CANTIDAD DE CARGAS', $cantidadCargas, self::FORMATO_ENTERO],
        ], count($columnas));

        $fila = $this->pintarTituloSeccion($hoja, $fila, 'DETALLE POR VEHÍCULO', count($columnas));

        $filas = $vehiculos->map(fn ($v) => [
            $v['codigo'] ?? '—',
            $v['nro_placa'] ?? '—',
            $v['marca'] ?? '—',
            (float) ($v['total_litros'] ?? 0),
            (float) ($v['total_costo'] ?? 0),
            (int) ($v['cantidad_cargas'] ?? 0),
            (float) ($v['precio_promedio'] ?? 0),
        ]);

        $this->pintarTabla($hoja, $fila, $columnas, $filas, [
            'TOTALES', '', '',
            $totalLitros,
            $totalCosto,
            $cantidadCargas,
            $totalLitros > 0 ? round($totalCosto / $totalLitros, 2) : 0,
        ], 'No hay cargas de combustible en el rango seleccionado.');

        return $this->guardar($libro);
    }

    /**
     * Reporte general de rendimiento (uno o varios vehículos comparados),
     * espejo de Reportes::generarReporteRendimiento(). $resultado es lo que
     * devuelve obtenerResumenRendimiento() con $soloResumen = true.
     *
     * @param  iterable<int, object>  $resultado
     * @param  array{tipo_combustible?: ?string, area?: ?string}  $filtrosAplicados
     */
    public function generarReporteRendimiento($resultado, $fechaInicio, $fechaFin, array $filtrosAplicados = []): string
    {
        $resultado = collect($resultado);

        $libro = $this->crearLibro('Reporte de rendimiento de combustible');
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Rendimiento');

        $columnas = [
            ['label' => 'CÓDIGO', 'ancho' => 16, 'align' => 'left'],
            ['label' => 'PLACA', 'ancho' => 14, 'align' => 'center'],
            ['label' => 'COMBUSTIBLE', 'ancho' => 16, 'align' => 'center'],
            ['label' => 'TIPO MEDICIÓN', 'ancho' => 16, 'align' => 'center'],
            ['label' => 'CARGAS', 'ancho' => 10, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'LITROS', 'ancho' => 14, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'RECORRIDO / HORAS', 'ancho' => 18, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'RENDIMIENTO', 'ancho' => 15, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'UNIDAD', 'ancho' => 10, 'align' => 'center'],
        ];

        $totalCargas = (int) $resultado->sum('total_cargas');
        $totalLitros = round((float) $resultado->sum(fn ($r) => (float) $r->total_litros), 2);
        $totalRecorrido = round((float) $resultado->sum(fn ($r) => (float) $r->total_recorrido), 2);

        $fila = $this->pintarEncabezado(
            $hoja,
            'REPORTE DE RENDIMIENTO DE COMBUSTIBLE',
            'Del '.$this->fecha($fechaInicio).' al '.$this->fecha($fechaFin),
            $this->lineasDeFiltros([
                'Tipo de combustible' => $filtrosAplicados['tipo_combustible'] ?? null,
                'Área' => $filtrosAplicados['area'] ?? null,
            ]),
            count($columnas),
        );

        $fila = $this->pintarTarjetas($hoja, $fila, [
            ['VEHÍCULOS', $resultado->count(), self::FORMATO_ENTERO],
            ['TOTAL CARGAS', $totalCargas, self::FORMATO_ENTERO],
            ['TOTAL LITROS', $totalLitros, self::FORMATO_DECIMAL],
            ['RECORRIDO / HORAS', $totalRecorrido, self::FORMATO_DECIMAL],
        ], count($columnas));

        $fila = $this->pintarTituloSeccion($hoja, $fila, 'DETALLE POR VEHÍCULO', count($columnas));

        $filas = $resultado->map(function ($r) {
            // Sin recorrido acumulado el rendimiento no es calculable: se deja
            // la celda vacía en vez de escribir un 0 que se sumaría/promediaría.
            $sinDatos = (float) $r->total_recorrido === 0.0;

            return [
                $r->codigo,
                $r->nro_placa,
                $r->tipo_combustible,
                $r->tipo_medicion === 'horometro' ? 'Horómetro' : 'Kilometraje',
                (int) $r->total_cargas,
                (float) $r->total_litros,
                (float) $r->total_recorrido,
                $sinDatos ? null : (float) $r->rendimiento_promedio,
                $sinDatos ? 'Sin datos' : $r->unidad_medida,
            ];
        });

        // El rendimiento no se totaliza: km/L y L/h no son comparables entre sí.
        $this->pintarTabla($hoja, $fila, $columnas, $filas, [
            'TOTALES', '', '', '',
            $totalCargas,
            $totalLitros,
            $totalRecorrido,
            null,
            '',
        ], 'No hay datos de rendimiento para el rango seleccionado.');

        return $this->guardar($libro);
    }

    /**
     * Detalle carga por carga del rendimiento de UN vehículo, espejo de
     * Reportes::generarReporteDetalleRendimiento().
     *
     * @param  Vehiculo  $vehiculo
     * @param  iterable<int, object>  $detalle
     */
    public function generarReporteDetalleRendimiento($vehiculo, $detalle, $fechaInicio, $fechaFin): string
    {
        $detalle = collect($detalle);
        $esHorometro = $vehiculo->tipo_medicion === 'horometro';
        $unidad = $esHorometro ? 'L/h' : 'km/L';

        $libro = $this->crearLibro('Detalle de rendimiento '.$vehiculo->codigo);
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Detalle rendimiento');

        $columnas = [
            ['label' => 'FECHA DE CARGA', 'ancho' => 20, 'align' => 'center'],
            ['label' => 'LITROS', 'ancho' => 14, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'MED. ANTERIOR', 'ancho' => 16, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'MED. ACTUAL', 'ancho' => 16, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => $esHorometro ? 'HORAS' : 'RECORRIDO', 'ancho' => 16, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'RENDIMIENTO ('.$unidad.')', 'ancho' => 20, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
        ];

        $totalLitros = round((float) $detalle->sum(fn ($d) => (float) $d->litros), 2);
        $totalRecorrido = round((float) $detalle->sum(fn ($d) => (float) $d->recorrido), 2);
        $rendimientoPromedio = $esHorometro
            ? ($totalRecorrido > 0 ? round($totalLitros / $totalRecorrido, 2) : 0)
            : ($totalLitros > 0 ? round($totalRecorrido / $totalLitros, 2) : 0);

        $fila = $this->pintarEncabezado(
            $hoja,
            'DETALLE DE RENDIMIENTO DE COMBUSTIBLE',
            'Del '.$this->fecha($fechaInicio).' al '.$this->fecha($fechaFin),
            array_filter([
                'Vehículo: '.$vehiculo->codigo.' - '.($vehiculo->nro_placa ?: 'Sin placa').'   |   '.$vehiculo->marca,
                $vehiculo->tipoCombustible?->tipo_combustible
                    ? 'Combustible: '.$vehiculo->tipoCombustible->tipo_combustible.'   |   Medición: '.($esHorometro ? 'Horómetro' : 'Kilometraje')
                    : null,
            ]),
            count($columnas),
        );

        $fila = $this->pintarTarjetas($hoja, $fila, [
            ['CARGAS', $detalle->count(), self::FORMATO_ENTERO],
            ['TOTAL LITROS', $totalLitros, self::FORMATO_DECIMAL],
            [$esHorometro ? 'TOTAL HORAS' : 'TOTAL RECORRIDO', $totalRecorrido, self::FORMATO_DECIMAL],
            ['RENDIMIENTO ('.$unidad.')', $rendimientoPromedio, self::FORMATO_DECIMAL],
        ], count($columnas));

        $fila = $this->pintarTituloSeccion($hoja, $fila, 'CARGAS DEL VEHÍCULO', count($columnas));

        $filas = $detalle->map(fn ($d) => [
            $this->fechaHora($d->fecha_carga),
            (float) $d->litros,
            (float) $d->medicion_anterior,
            (float) $d->medicion_actual,
            (float) $d->recorrido,
            (float) $d->rendimiento,
        ]);

        $this->pintarTabla($hoja, $fila, $columnas, $filas, [
            'TOTALES',
            $totalLitros,
            null,
            null,
            $totalRecorrido,
            $rendimientoPromedio,
        ], 'No hay cargas con medición anterior disponible en este rango.');

        return $this->guardar($libro);
    }

    /* =====================================================================
     |  Reportes de control de carga de material (vehículos externos)
     | ================================================================== */

    /**
     * Espejo de Reportes::generarReporteControlCargas().
     *
     * @param  array{vehiculos: iterable<int, array<string, mixed>>, totales: array<string, int|float>}  $resumen
     * @param  string  $ambito  'todos' | 'exterior' | 'nacional' — sólo para dejar constancia del filtro.
     */
    public function generarReporteControlCargas(array $resumen, $fechaDesde, $fechaHasta, string $ambito = 'todos'): string
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

        $libro = $this->crearLibro('Reporte de control de carga de material');
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Control de cargas');

        $columnas = [
            ['label' => '#', 'ancho' => 6, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'PLACA', 'ancho' => 16, 'align' => 'left'],
            ['label' => 'PROPIETARIO', 'ancho' => 34, 'align' => 'left'],
            ['label' => 'FLETES', 'ancho' => 11, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'VIAJES', 'ancho' => 11, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'AL EXTERIOR', 'ancho' => 13, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'NACIONALES', 'ancho' => 13, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'MONTO PAGADO', 'ancho' => 18, 'align' => 'right', 'formato' => self::FORMATO_MONEDA],
        ];

        $fila = $this->pintarEncabezado(
            $hoja,
            'REPORTE DE CONTROL DE CARGA DE MATERIAL',
            'Fletes abiertos del '.$this->fecha($fechaDesde).' al '.$this->fecha($fechaHasta),
            $this->lineasDeFiltros(['Viajes' => $this->etiquetaAmbito($ambito)]),
            count($columnas),
        );

        $fila = $this->pintarTarjetas($hoja, $fila, [
            ['VEHÍCULOS EXTERNOS', (int) $totales['total_vehiculos'], self::FORMATO_ENTERO],
            ['TOTAL FLETES', (int) $totales['total_fletes'], self::FORMATO_ENTERO],
            ['TOTAL VIAJES', (int) $totales['total_viajes'], self::FORMATO_ENTERO],
            ['AL EXTERIOR', (int) $totales['viajes_exterior'], self::FORMATO_ENTERO],
            ['NACIONALES', (int) $totales['viajes_nacional'], self::FORMATO_ENTERO],
            ['MONTO PAGADO', (float) $totales['monto_total'], self::FORMATO_MONEDA],
        ], count($columnas));

        $fila = $this->pintarTituloSeccion($hoja, $fila, 'VIAJES POR VEHÍCULO EXTERNO', count($columnas));

        $filas = $vehiculos->values()->map(fn ($v, $i) => [
            $i + 1,
            $v['nro_placa'] ?? '—',
            $v['propietario'] ?? '—',
            (int) ($v['total_fletes'] ?? 0),
            (int) ($v['total_viajes'] ?? 0),
            (int) ($v['viajes_exterior'] ?? 0),
            (int) ($v['viajes_nacional'] ?? 0),
            (float) ($v['monto_total'] ?? 0),
        ]);

        $this->pintarTabla($hoja, $fila, $columnas, $filas, [
            'TOTALES', '', '',
            (int) $totales['total_fletes'],
            (int) $totales['total_viajes'],
            (int) $totales['viajes_exterior'],
            (int) $totales['viajes_nacional'],
            (float) $totales['monto_total'],
        ], 'No hay fletes en el rango y filtro seleccionados.');

        return $this->guardar($libro);
    }

    /**
     * Espejo de Reportes::generarReporteControlCargasDetalle(): dos tablas
     * (resumen por material + fletes del vehículo), cada una en su propia hoja
     * para que ambas se puedan filtrar y ordenar por separado.
     *
     * @param  VehiculoExterno  $vehiculo
     * @param  array{fletes: iterable<int, array<string, mixed>>, materiales: iterable<int, array<string, mixed>>, totales: array<string, int|float>}  $detalle
     */
    public function generarReporteControlCargasDetalle($vehiculo, array $detalle, $fechaDesde, $fechaHasta): string
    {
        $fletes = collect($detalle['fletes'] ?? []);
        $materiales = collect($detalle['materiales'] ?? []);
        $totales = ($detalle['totales'] ?? []) + [
            'total_fletes' => 0,
            'total_viajes' => 0,
            'total_materiales' => 0,
            'monto_total' => 0,
        ];

        $placa = $vehiculo->nro_placa ?: 'Sin placa';
        $subtitulo = 'Fletes abiertos del '.$this->fecha($fechaDesde).' al '.$this->fecha($fechaHasta);
        $lineaVehiculo = 'Vehículo externo: '.$placa.($vehiculo->propietario ? '   |   '.$vehiculo->propietario : '');

        $libro = $this->crearLibro('Detalle de control de carga de material - '.$placa);

        /* ── Hoja 1: fletes del vehículo ─────────────────────────────── */
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Fletes');

        $columnasFletes = [
            ['label' => 'Nº FLETE', 'ancho' => 16, 'align' => 'left'],
            ['label' => 'APERTURA', 'ancho' => 18, 'align' => 'left'],
            ['label' => 'CIERRE', 'ancho' => 18, 'align' => 'left'],
            ['label' => 'ESTADO', 'ancho' => 13, 'align' => 'left'],
            ['label' => 'ÁMBITO', 'ancho' => 20, 'align' => 'left'],
            ['label' => 'CONDUCTOR', 'ancho' => 24, 'align' => 'left'],
            ['label' => 'VIAJES', 'ancho' => 10, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'VIAJES POR MATERIAL', 'ancho' => 40, 'align' => 'left', 'ajustar' => true],
            ['label' => 'MONTO (Bs.)', 'ancho' => 16, 'align' => 'right', 'formato' => self::FORMATO_MONEDA],
        ];

        $fila = $this->pintarEncabezado($hoja, 'DETALLE DE CONTROL DE CARGA DE MATERIAL', $subtitulo, [$lineaVehiculo], count($columnasFletes));

        $fila = $this->pintarTarjetas($hoja, $fila, [
            ['FLETES', (int) $totales['total_fletes'], self::FORMATO_ENTERO],
            ['TOTAL VIAJES', (int) $totales['total_viajes'], self::FORMATO_ENTERO],
            ['TIPOS DE MATERIAL', (int) $totales['total_materiales'], self::FORMATO_ENTERO],
            ['MONTO PAGADO', (float) $totales['monto_total'], self::FORMATO_MONEDA],
        ], count($columnasFletes));

        $fila = $this->pintarTituloSeccion($hoja, $fila, 'FLETES DEL VEHÍCULO', count($columnasFletes));

        $filasFletes = $fletes->map(fn ($flete) => [
            (string) ($flete['nro'] ?? '—'),
            $this->fechaHora($flete['fecha_apertura'] ?? null),
            $this->fechaHora($flete['fecha_cierre'] ?? null),
            (string) ($flete['estado_carga'] ?? '—'),
            ($flete['es_al_exterior'] ?? false)
                ? 'Exterior'.(! empty($flete['pais']) ? ' · '.$flete['pais'] : '')
                : 'Nacional',
            (string) ($flete['nombre_conductor'] ?? '—'),
            (int) ($flete['viajes_count'] ?? 0),
            collect($flete['materiales'] ?? [])
                ->map(fn ($m) => ($m['material'] ?? '—').': '.($m['viajes'] ?? 0))
                ->implode(', ') ?: '—',
            ($flete['monto_pago'] ?? null) !== null ? (float) $flete['monto_pago'] : null,
        ]);

        $this->pintarTabla($hoja, $fila, $columnasFletes, $filasFletes, [
            'TOTALES', '', '', '', '', '',
            (int) $totales['total_viajes'],
            '',
            (float) $totales['monto_total'],
        ], 'Sin fletes abiertos en el rango seleccionado.');

        /* ── Hoja 2: resumen por material ────────────────────────────── */
        $hojaMat = $libro->createSheet();
        $hojaMat->setTitle('Materiales');

        $columnasMat = [
            ['label' => 'MATERIAL', 'ancho' => 40, 'align' => 'left'],
            ['label' => 'VIAJES', 'ancho' => 12, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => '% DEL TOTAL', 'ancho' => 14, 'align' => 'right', 'formato' => '0.0"  %"'],
        ];

        $filaMat = $this->pintarEncabezado($hojaMat, 'RESUMEN DE TIPOS DE CARGA (VIAJES POR MATERIAL)', $subtitulo, [$lineaVehiculo], count($columnasMat));
        $filaMat = $this->pintarTituloSeccion($hojaMat, $filaMat, 'VIAJES POR MATERIAL', count($columnasMat));

        $totalViajes = (int) $totales['total_viajes'];
        $filasMat = $materiales->map(fn ($mat) => [
            $mat['material'] ?? '—',
            (int) ($mat['viajes'] ?? 0),
            $totalViajes > 0 ? round((int) ($mat['viajes'] ?? 0) / $totalViajes * 100, 1) : 0.0,
        ]);

        $this->pintarTabla($hojaMat, $filaMat, $columnasMat, $filasMat, [
            'TOTAL',
            $totalViajes,
            $totalViajes > 0 ? 100.0 : 0.0,
        ], 'El vehículo no registró viajes en el rango seleccionado.');

        $libro->setActiveSheetIndex(0);

        return $this->guardar($libro);
    }

    /* =====================================================================
     |  Reportes de operación diaria
     | ================================================================== */

    /**
     * Espejo de Reportes::generarReporteOperacionDiariaUso().
     *
     * @param  array{vehiculos: iterable<int, array<string, mixed>>, totales: array<string, int|float>}  $resumen
     * @param  array{tipo_combustible?: ?string, area?: ?string, vehiculo?: ?string}  $filtrosAplicados
     */
    public function generarReporteOperacionDiariaUso(array $resumen, $fechaInicio, $fechaFin, array $filtrosAplicados = []): string
    {
        $vehiculos = collect($resumen['vehiculos'] ?? []);
        $totales = ($resumen['totales'] ?? []) + [
            'total_operaciones' => 0,
            'total_vehiculos' => 0,
            'total_horas' => 0,
            'total_km' => 0,
            'total_horometro' => 0,
            'operaciones_dia' => 0,
            'operaciones_noche' => 0,
        ];

        $libro = $this->crearLibro('Reporte de uso de vehículos');
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Uso de vehículos');

        $columnas = [
            ['label' => 'CÓDIGO', 'ancho' => 16, 'align' => 'left'],
            ['label' => 'PLACA', 'ancho' => 14, 'align' => 'center'],
            ['label' => 'COMBUSTIBLE', 'ancho' => 16, 'align' => 'center'],
            ['label' => 'MEDICIÓN', 'ancho' => 14, 'align' => 'center'],
            ['label' => 'OPERACIONES', 'ancho' => 13, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'DÍAS OPERADOS', 'ancho' => 13, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'HORAS TRAB.', 'ancho' => 14, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'PROM. H/OP', 'ancho' => 13, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'RECORRIDO', 'ancho' => 15, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'UNIDAD', 'ancho' => 10, 'align' => 'center'],
            ['label' => 'TURNO DÍA', 'ancho' => 11, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'TURNO NOCHE', 'ancho' => 12, 'align' => 'center', 'formato' => self::FORMATO_ENTERO],
            ['label' => 'ÚLTIMA OPERACIÓN', 'ancho' => 20, 'align' => 'center'],
        ];

        $fila = $this->pintarEncabezado(
            $hoja,
            'REPORTE DE USO DE VEHÍCULOS EN OPERACIÓN DIARIA',
            'Del '.$this->fecha($fechaInicio).' al '.$this->fecha($fechaFin),
            $this->lineasDeFiltros([
                'Tipo de combustible' => $filtrosAplicados['tipo_combustible'] ?? null,
                'Área' => $filtrosAplicados['area'] ?? null,
                'Vehículos' => $filtrosAplicados['vehiculo'] ?? null,
            ]),
            count($columnas),
        );

        $fila = $this->pintarTarjetas($hoja, $fila, [
            ['VEHÍCULOS', (int) $totales['total_vehiculos'], self::FORMATO_ENTERO],
            ['OPERACIONES', (int) $totales['total_operaciones'], self::FORMATO_ENTERO],
            ['HORAS TRABAJADAS', (float) $totales['total_horas'], self::FORMATO_DECIMAL],
            ['RECORRIDO (km)', (float) $totales['total_km'], self::FORMATO_DECIMAL],
            ['HORÓMETRO (h)', (float) $totales['total_horometro'], self::FORMATO_DECIMAL],
            ['DÍA / NOCHE', (int) $totales['operaciones_dia'].' / '.(int) $totales['operaciones_noche'], null],
        ], count($columnas));

        $fila = $this->pintarTituloSeccion($hoja, $fila, 'DETALLE POR VEHÍCULO', count($columnas));

        $filas = $vehiculos->map(fn ($v) => [
            $v['codigo'] ?? '—',
            $v['nro_placa'] ?? '—',
            $v['tipo_combustible'] ?? '—',
            ($v['tipo_medicion'] ?? null) === 'kilometraje' ? 'Kilometraje' : 'Horómetro',
            (int) ($v['total_operaciones'] ?? 0),
            (int) ($v['dias_operados'] ?? 0),
            (float) ($v['total_horas'] ?? 0),
            (float) ($v['promedio_horas'] ?? 0),
            (float) ($v['total_recorrido'] ?? 0),
            $v['unidad_recorrido'] ?? '',
            (int) ($v['operaciones_dia'] ?? 0),
            (int) ($v['operaciones_noche'] ?? 0),
            $this->fechaHora($v['ultima_operacion'] ?? null),
        ]);

        // El promedio y el recorrido no se totalizan por columna: km y horas de
        // horómetro no son la misma unidad (van separados en las tarjetas).
        $this->pintarTabla($hoja, $fila, $columnas, $filas, [
            'TOTALES', '', '', '',
            (int) $totales['total_operaciones'],
            '',
            (float) $totales['total_horas'],
            null,
            null,
            '',
            (int) $totales['operaciones_dia'],
            (int) $totales['operaciones_noche'],
            '',
        ], 'No hay operaciones diarias en el rango seleccionado.');

        return $this->guardar($libro);
    }

    /**
     * Bitácora detallada de UN vehículo, espejo de
     * Reportes::generarReporteOperacionDiariaDetalle(): una fila por operación
     * diaria con encabezado de dos niveles (grupos + columnas) y columnas
     * dinámicas de mantenimiento y material.
     *
     * @param  Vehiculo  $vehiculo
     * @param  array{tipo_medicion?: string, columnas?: array, filas?: iterable, totales?: array}  $datos
     */
    public function generarReporteOperacionDiariaDetalle($vehiculo, array $datos, $fechaInicio, $fechaFin): string
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

        $libro = $this->crearLibro('Bitácora de operación '.$vehiculo->codigo);
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Bitácora');

        $columnas = [
            ['label' => 'FECHA', 'ancho' => 12, 'align' => 'left'],
            ['label' => 'OPERADOR', 'ancho' => 26, 'align' => 'left'],
            ['label' => 'N° PARTE', 'ancho' => 10, 'align' => 'center'],
            ['label' => $lectura.' INICIAL', 'ancho' => 15, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => $lectura.' FINAL', 'ancho' => 15, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'TOTAL HORAS', 'ancho' => 12, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'LITROS', 'ancho' => 11, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'C / LITRO', 'ancho' => 11, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
            ['label' => 'COSTO (Bs.)', 'ancho' => 14, 'align' => 'right', 'formato' => self::FORMATO_MONEDA],
            ['label' => $lectura.' DE CARGA', 'ancho' => 16, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL],
        ];

        $grupos = [
            ['label' => 'TRABAJO DE EQUIPO', 'span' => 6],
            ['label' => 'CONSUMO Y COSTO DE COMBUSTIBLE', 'span' => 4],
        ];

        foreach ($colsMant as $c) {
            $unidad = ! empty($c['unidad_medida']) ? ' ('.$c['unidad_medida'].')' : '';
            $columnas[] = [
                'label' => mb_strtoupper($c['nombre'].$unidad),
                'ancho' => 14,
                'align' => 'right',
                // Los controles booleanos se muestran como Sí/No, no como número.
                'formato' => ($c['tipo_valor'] ?? null) === 'booleano' ? null : self::FORMATO_DECIMAL,
            ];
        }
        if ($colsMant->isNotEmpty()) {
            $grupos[] = ['label' => 'MANTENIMIENTO', 'span' => $colsMant->count()];
        }

        foreach ($colsMat as $c) {
            $columnas[] = ['label' => mb_strtoupper($c['nombre']), 'ancho' => 14, 'align' => 'right', 'formato' => self::FORMATO_DECIMAL];
        }
        if ($colsMat->isNotEmpty()) {
            $grupos[] = ['label' => 'MATERIAL TRASLADADO', 'span' => $colsMat->count()];
        }

        $columnas[] = ['label' => 'OBSERVACIONES', 'ancho' => 34, 'align' => 'left', 'ajustar' => true];
        $grupos[] = ['label' => 'OBSERVACIONES', 'span' => 1];

        $combustible = $vehiculo->tipoCombustible?->tipo_combustible ? '   |   '.$vehiculo->tipoCombustible->tipo_combustible : '';

        $fila = $this->pintarEncabezado(
            $hoja,
            'DETALLE DE HORAS TRABAJADAS Y CONSUMO DE COMBUSTIBLE',
            'Del '.$this->fecha($fechaInicio).' al '.$this->fecha($fechaFin),
            ['Vehículo: '.$vehiculo->codigo.' - '.($vehiculo->nro_placa ?: 'Sin placa').'   |   '.trim($vehiculo->marca.' '.$vehiculo->modelo).$combustible],
            count($columnas),
        );

        $fila = $this->pintarTarjetas($hoja, $fila, [
            ['OPERACIONES', (int) $totales['operaciones'], self::FORMATO_ENTERO],
            ['TOTAL HORAS TRABAJADAS', (float) $totales['horas_trabajadas'], self::FORMATO_DECIMAL],
            ['CONSUMO (litros)', (float) $totales['litros'], self::FORMATO_DECIMAL],
            ['COSTO COMBUSTIBLE', (float) $totales['costo'], self::FORMATO_MONEDA],
            ['LITROS / HORA', (float) $totales['litros_por_hora'], self::FORMATO_DECIMAL],
        ], count($columnas));

        $filasTabla = $filas->map(function ($f) use ($colsMant, $colsMat) {
            $carga = $f['carga'] ?? null;

            $valores = [
                $this->fecha($f['fecha'] ?? null),
                $f['operador'] ?: '—',
                (string) ($f['nro_parte'] ?? '—'),
                $this->numeroONull($f['lectura_inicio'] ?? null),
                $this->numeroONull($f['lectura_fin'] ?? null),
                (float) ($f['horas_trabajadas'] ?? 0),
                $carga ? (float) $carga['litros'] : null,
                $carga ? (float) $carga['precio_unitario'] : null,
                $carga ? (float) $carga['costo'] : null,
                $carga ? $this->numeroONull($carga['lectura_carga']) : null,
            ];

            foreach ($colsMant as $c) {
                $m = $f['mantenimientos'][$c['id']] ?? null;

                if ($m === null) {
                    $valores[] = null;
                } elseif (($c['tipo_valor'] ?? null) === 'booleano') {
                    $valores[] = match ($m['realizado'] ?? null) {
                        'SI' => 'Sí',
                        'NO' => 'No',
                        default => null,
                    };
                } else {
                    $valores[] = $this->numeroONull($m['valor'] ?? null);
                }
            }

            foreach ($colsMat as $c) {
                $valores[] = $this->numeroONull($f['materiales'][$c['id']] ?? null);
            }

            $valores[] = $f['observaciones'] ?: '—';

            return $valores;
        });

        $filaTotales = [
            'TOTALES', '', '', '', '',
            (float) $totales['horas_trabajadas'],
            (float) $totales['litros'],
            null,
            (float) $totales['costo'],
            null,
        ];
        foreach ($colsMant as $c) {
            $filaTotales[] = ($c['tipo_valor'] ?? null) === 'booleano'
                ? $c['total'].' sí'
                : (float) $c['total'];
        }
        foreach ($colsMat as $c) {
            $filaTotales[] = (float) $c['total'];
        }
        $filaTotales[] = '';

        $this->pintarTabla(
            $hoja,
            $fila,
            $columnas,
            $filasTabla,
            $filaTotales,
            'El vehículo no tiene operaciones diarias en el rango seleccionado.',
            $grupos,
        );

        return $this->guardar($libro);
    }

    /* =====================================================================
     |  Helpers de construcción del libro
     | ================================================================== */

    private function crearLibro(string $titulo): Spreadsheet
    {
        $libro = new Spreadsheet;

        $parametros = $this->parametrosEmpresa();
        $libro->getProperties()
            ->setCreator($parametros?->nombre_empresa ?: config('app.name'))
            ->setCompany($parametros?->nombre_empresa ?: '')
            ->setTitle($titulo)
            ->setSubject($titulo);

        return $libro;
    }

    /**
     * Bloque de encabezado (logo, título, rango de fechas, datos de la empresa
     * y líneas de filtros aplicados). Devuelve la primera fila libre.
     *
     * @param  array<int, string>  $lineasExtra
     */
    private function pintarEncabezado(Worksheet $hoja, string $titulo, string $subtitulo, array $lineasExtra, int $totalColumnas): int
    {
        $ultima = $this->letraColumna($totalColumnas);

        // Fila 1: sólo el logo (flota sobre la celda, no ocupa contenido).
        $hoja->getRowDimension(1)->setRowHeight(34);
        $logo = new Drawing;
        $logo->setName('Logo');
        $logo->setDescription($titulo);
        $logo->setPath($this->logoEmpresa());
        $logo->setHeight(38);
        $logo->setOffsetX(2);
        $logo->setOffsetY(3);
        $logo->setCoordinates('A1');
        $logo->setWorksheet($hoja);

        $hoja->mergeCells('A2:'.$ultima.'2');
        $hoja->setCellValue('A2', $titulo);
        $hoja->getStyle('A2')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB(self::AZUL);

        $hoja->mergeCells('A3:'.$ultima.'3');
        $hoja->setCellValue('A3', $subtitulo);
        $hoja->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB(self::GRIS_TEXTO);

        $hoja->mergeCells('A4:'.$ultima.'4');
        $hoja->setCellValue('A4', $this->lineaEmpresa());
        $hoja->getStyle('A4')->getFont()->setSize(8)->getColor()->setRGB(self::GRIS_TEXTO);

        $hoja->getStyle('A2:'.$ultima.'4')
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $fila = 5;
        foreach ($lineasExtra as $linea) {
            $hoja->mergeCells('A'.$fila.':'.$ultima.$fila);
            $hoja->setCellValue('A'.$fila, $linea);
            $hoja->getStyle('A'.$fila)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB(self::CELESTE);
            $hoja->getStyle('A'.$fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $fila++;
        }

        // Preparación para impresión: apaisado y ajustado al ancho de la hoja.
        $hoja->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        return max($fila, self::FILA_CONTENIDO);
    }

    /**
     * Bloque de tarjetas KPI (etiqueta arriba, valor abajo), equivalente a las
     * tarjetas de color del PDF. Devuelve la primera fila libre.
     *
     * @param  array<int, array{0: string, 1: mixed, 2: ?string}>  $tarjetas
     */
    private function pintarTarjetas(Worksheet $hoja, int $fila, array $tarjetas, int $totalColumnas): int
    {
        // Las tarjetas se reparten TODO el ancho de la tabla: cada una ocupa el
        // mismo nº de columnas y el resto de la división se reparte de a una
        // entre las primeras, para que no quede una franja suelta a la derecha
        // ni etiquetas apretadas en una sola columna angosta.
        $cantidad = max(count($tarjetas), 1);
        $ancho = max(1, (int) floor($totalColumnas / $cantidad));
        $resto = max(0, $totalColumnas - $ancho * $cantidad);

        $columna = 1;
        foreach (array_values($tarjetas) as $indice => [$etiqueta, $valor, $formato]) {
            if ($columna > $totalColumnas) {
                break;
            }

            $anchoTarjeta = $ancho + ($indice < $resto ? 1 : 0);
            $desde = $this->letraColumna($columna);
            $hasta = $this->letraColumna(min($columna + $anchoTarjeta - 1, $totalColumnas));

            $hoja->mergeCells($desde.$fila.':'.$hasta.$fila);
            $hoja->setCellValue($desde.$fila, $etiqueta);
            $hoja->getStyle($desde.$fila)->getFont()->setBold(true)->setSize(8)->getColor()->setRGB(self::TEXTO);
            $hoja->getStyle($desde.$fila)->getAlignment()->setWrapText(true);
            $hoja->getStyle($desde.$fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS_RELLENO);

            $hoja->mergeCells($desde.($fila + 1).':'.$hasta.($fila + 1));
            $this->escribirValor($hoja, $desde.($fila + 1), $valor, $formato);
            $hoja->getStyle($desde.($fila + 1))->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::TEXTO);
            $hoja->getStyle($desde.($fila + 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::FILA_ALTERNA);

            $hoja->getStyle($desde.$fila.':'.$hasta.($fila + 1))
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $this->pintarBordes($hoja, $desde.$fila.':'.$hasta.($fila + 1));

            $columna += $anchoTarjeta;
        }

        $hoja->getRowDimension($fila + 1)->setRowHeight(20);

        return $fila + 3;
    }

    /**
     * Barra de título de sección (equivalente a la barra azul del PDF).
     * Devuelve la primera fila libre.
     */
    private function pintarTituloSeccion(Worksheet $hoja, int $fila, string $texto, int $totalColumnas): int
    {
        $rango = 'A'.$fila.':'.$this->letraColumna($totalColumnas).$fila;

        $hoja->mergeCells($rango);
        $hoja->setCellValue('A'.$fila, $texto);
        $hoja->getStyle($rango)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB(self::TEXTO);
        $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS_RELLENO);
        $hoja->getStyle($rango)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension($fila)->setRowHeight(18);

        return $fila + 1;
    }

    /**
     * Tabla completa: encabezado (opcionalmente con una fila de grupos por
     * encima), filas de datos con franjas alternadas y fila de totales.
     * Devuelve la primera fila libre.
     *
     * @param  array<int, array{label: string, ancho: int|float, align: string, formato?: ?string, ajustar?: bool}>  $columnas
     * @param  iterable<int, array<int, mixed>>  $filas
     * @param  array<int, mixed>|null  $totales
     * @param  array<int, array{label: string, span: int}>  $grupos
     */
    private function pintarTabla(Worksheet $hoja, int $fila, array $columnas, $filas, ?array $totales, string $mensajeVacio, array $grupos = []): int
    {
        $total = count($columnas);
        $ultima = $this->letraColumna($total);

        // Fila de grupos (colspans), sólo en la bitácora de operación diaria.
        if ($grupos) {
            $columna = 1;
            foreach ($grupos as $grupo) {
                $desde = $this->letraColumna($columna);
                $hasta = $this->letraColumna(min($columna + $grupo['span'] - 1, $total));
                $hoja->mergeCells($desde.$fila.':'.$hasta.$fila);
                $hoja->setCellValue($desde.$fila, $grupo['label']);
                $columna += $grupo['span'];
            }

            $rangoGrupos = 'A'.$fila.':'.$ultima.$fila;
            $hoja->getStyle($rangoGrupos)->getFont()->setBold(true)->setSize(8)->getColor()->setRGB(self::TEXTO);
            $hoja->getStyle($rangoGrupos)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS_RELLENO);
            $hoja->getStyle($rangoGrupos)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $this->pintarBordes($hoja, $rangoGrupos);
            $fila++;
        }

        // Encabezados de columna.
        $filaEncabezado = $fila;
        foreach ($columnas as $indice => $columna) {
            $letra = $this->letraColumna($indice + 1);
            $hoja->setCellValue($letra.$fila, $columna['label']);
            $hoja->getColumnDimension($letra)->setWidth((float) $columna['ancho']);
        }

        $rangoEncabezado = 'A'.$fila.':'.$ultima.$fila;
        $hoja->getStyle($rangoEncabezado)->getFont()->setBold(true)->setSize(9)->getColor()->setRGB(self::TEXTO);
        $hoja->getStyle($rangoEncabezado)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS_RELLENO);
        $hoja->getStyle($rangoEncabezado)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $hoja->getRowDimension($fila)->setRowHeight(26);
        $this->pintarBordes($hoja, $rangoEncabezado);
        $fila++;

        // Filas de datos.
        $filaPrimerDato = $fila;
        $indiceFila = 0;
        foreach ($filas as $valores) {
            foreach ($columnas as $indice => $columna) {
                $celda = $this->letraColumna($indice + 1).$fila;
                $this->escribirValor($hoja, $celda, $valores[$indice] ?? null, $columna['formato'] ?? null);
                $hoja->getStyle($celda)->getAlignment()
                    ->setHorizontal($this->alineacion($columna['align']))
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(! empty($columna['ajustar']));
            }

            $rangoFila = 'A'.$fila.':'.$ultima.$fila;
            $hoja->getStyle($rangoFila)->getFont()->setSize(9)->getColor()->setRGB(self::TEXTO);
            if ($indiceFila % 2 === 1) {
                $hoja->getStyle($rangoFila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::FILA_ALTERNA);
            }
            $this->pintarBordes($hoja, $rangoFila);

            $fila++;
            $indiceFila++;
        }

        $hayDatos = $indiceFila > 0;

        if (! $hayDatos) {
            $rangoVacio = 'A'.$fila.':'.$ultima.$fila;
            $hoja->mergeCells($rangoVacio);
            $hoja->setCellValue('A'.$fila, $mensajeVacio);
            $hoja->getStyle($rangoVacio)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB(self::GRIS_TEXTO);
            $hoja->getStyle($rangoVacio)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->pintarBordes($hoja, $rangoVacio);
            $fila++;
        }

        // Fila de totales (sólo si hay datos que totalizar, igual que el PDF).
        if ($totales !== null && $hayDatos) {
            foreach ($columnas as $indice => $columna) {
                $celda = $this->letraColumna($indice + 1).$fila;
                $this->escribirValor($hoja, $celda, $totales[$indice] ?? null, $columna['formato'] ?? null);
                $hoja->getStyle($celda)->getAlignment()->setHorizontal($this->alineacion($columna['align']));
            }

            $rangoTotales = 'A'.$fila.':'.$ultima.$fila;
            $hoja->getStyle($rangoTotales)->getFont()->setBold(true)->setSize(9)->getColor()->setRGB(self::TEXTO);
            $hoja->getStyle($rangoTotales)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS_RELLENO);
            $hoja->getStyle($rangoTotales)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $hoja->getRowDimension($fila)->setRowHeight(18);
            $this->pintarBordes($hoja, $rangoTotales);
            $fila++;
        }

        // Autofiltro + panel inmovilizado para que el encabezado siga visible
        // al desplazarse por tablas largas.
        if ($hayDatos) {
            $hoja->setAutoFilter('A'.$filaEncabezado.':'.$ultima.($filaPrimerDato + $indiceFila - 1));
        }
        $hoja->freezePane('A'.$filaPrimerDato);
        $hoja->getPageSetup()->setPrintArea('A1:'.$ultima.($fila - 1));
        $hoja->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($filaEncabezado, $filaEncabezado);

        return $fila;
    }

    /**
     * Escribe un valor respetando su tipo: los numéricos van como número con
     * formato de celda (para poder sumarlos en Excel) y el resto como texto
     * explícito (evita que un código o una placa se interprete como fórmula,
     * número o fecha).
     */
    private function escribirValor(Worksheet $hoja, string $celda, mixed $valor, ?string $formato): void
    {
        if ($valor === null || $valor === '') {
            $hoja->setCellValue($celda, null);

            return;
        }

        if ($formato !== null && is_numeric($valor)) {
            $hoja->setCellValue($celda, (float) $valor);
            $hoja->getStyle($celda)->getNumberFormat()->setFormatCode($formato);

            return;
        }

        $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
    }

    private function pintarBordes(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('BFC6D4');
    }

    private function alineacion(string $align): string
    {
        return match ($align) {
            'center' => Alignment::HORIZONTAL_CENTER,
            'right' => Alignment::HORIZONTAL_RIGHT,
            default => Alignment::HORIZONTAL_LEFT,
        };
    }

    private function letraColumna(int $indice): string
    {
        return Coordinate::stringFromColumnIndex($indice);
    }

    /**
     * Guarda el libro en memoria y devuelve su contenido binario (equivalente
     * al modo 'S' de FPDF), para entregarlo con response() desde el controlador.
     */
    private function guardar(Spreadsheet $libro): string
    {
        $escritor = new Xlsx($libro);

        ob_start();
        $escritor->save('php://output');
        $contenido = (string) ob_get_clean();

        // PhpSpreadsheet mantiene referencias circulares entre libro y hojas:
        // sin esto el objeto no se libera hasta el final de la petición.
        $libro->disconnectWorksheets();

        return $contenido;
    }

    /* =====================================================================
     |  Helpers de datos y formato
     | ================================================================== */

    private function parametrosEmpresa(): ?ParametrosEmpresa
    {
        return ParametrosEmpresa::first();
    }

    /**
     * Misma regla que Reportes::logoEmpresa(): el logo configurado en
     * Parámetros de la Empresa sólo se usa si el archivo existe Y es una
     * imagen parseable; si no, el logo estático de respaldo.
     */
    private function logoEmpresa(): string
    {
        $logoRespaldo = public_path('images/logo/logo-plus-metals.png');
        $configurado = $this->parametrosEmpresa()?->logo_empresa;

        if (! $configurado) {
            return $logoRespaldo;
        }

        $ruta = storage_path('app/public/'.$configurado);

        return is_file($ruta) && @getimagesize($ruta) !== false ? $ruta : $logoRespaldo;
    }

    /**
     * Franja con los datos de la empresa, igual que pintarInfoEmpresa() del PDF.
     */
    private function lineaEmpresa(): string
    {
        $parametros = $this->parametrosEmpresa();

        if (! $parametros) {
            return '';
        }

        return implode('   |   ', array_filter([
            $parametros->nombre_empresa,
            $parametros->direccion_empresa,
            $parametros->telefono_empresa ? 'Tel. '.$parametros->telefono_empresa : null,
            $parametros->nit_empresa ? 'NIT: '.$parametros->nit_empresa : null,
        ]));
    }

    /**
     * Convierte un mapa etiqueta => valor en las líneas "Filtro aplicado: …"
     * del encabezado, descartando los filtros sin valor.
     *
     * @param  array<string, ?string>  $filtros
     * @return array<int, string>
     */
    private function lineasDeFiltros(array $filtros): array
    {
        $lineas = [];

        foreach ($filtros as $etiqueta => $valor) {
            if ($valor) {
                $lineas[] = 'Filtro aplicado: '.$etiqueta.': '.$valor;
            }
        }

        return $lineas;
    }

    private function etiquetaAmbito(string $ambito): ?string
    {
        return match ($ambito) {
            'exterior' => 'Sólo al exterior',
            'nacional' => 'Sólo nacionales',
            default => null,
        };
    }

    private function fecha(mixed $valor): string
    {
        return $valor ? date('d/m/Y', strtotime((string) $valor)) : '—';
    }

    private function fechaHora(mixed $valor): string
    {
        return $valor ? date('d/m/Y H:i', strtotime((string) $valor)) : '—';
    }

    /**
     * Los valores nulos deben quedar como celda vacía (no como 0): un 0
     * escrito donde no hubo lectura falsearía sumas y promedios de Excel.
     */
    private function numeroONull(mixed $valor): ?float
    {
        return $valor === null || $valor === '' ? null : (float) $valor;
    }
}

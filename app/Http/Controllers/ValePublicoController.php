<?php

namespace App\Http\Controllers;

use App\Models\ParametrosEmpresa;
use App\Models\Vale;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ValePublicoController extends Controller
{
    /**
     * Vista pública de solo lectura para verificar un vale al escanear su
     * código QR. No requiere autenticación: la URL sólo expone md5(id) —
     * nunca el id real — y sólo permite consultar el estado del vale, no
     * editarlo ni listar otros. Pensada para que cualquiera (p.ej. el
     * personal del surtidor) pueda confirmar que el vale es válido, si ya
     * fue usado o si está anulado.
     */
    public function show(string $hash): Response
    {
        // md5(id) no se puede revertir: se compara contra cada id existente
        // en vez de reconstruir el id desde el hash. Sólo se trae la
        // columna id (liviana e indexada) para esta primera pasada.
        $id = Vale::query()
            ->pluck('id')
            ->first(fn ($id) => hash_equals(md5((string) $id), $hash));

        abort_unless($id, 404);

        $vale = Vale::with(['vehiculo', 'conductor.persona', 'grifo', 'tipoCombustible', 'cargasCombustible.grifo'])
            ->findOrFail($id);

        $vencido = $vale->estado_vale === 'PENDIENTE' && $vale->fecha_vencimiento->isPast();

        $usadoEn = null;
        if ($vale->estado_vale === 'USADO' && $vale->cargasCombustible->isNotEmpty()) {
            $carga = $vale->cargasCombustible->first();
            $usadoEn = [
                'fecha_carga' => $carga->fecha_carga?->format('d/m/Y H:i'),
                'litros' => $carga->litros,
                'grifo' => $carga->grifo?->razon_social,
            ];
        }

        return Inertia::render('Vales/Publico', [
            'vale' => [
                'nro' => $vale->nro,
                'estado_vale' => $vale->estado_vale,
                'vencido' => $vencido,
                'fecha_emision' => $vale->fecha_emision_f,
                'fecha_vencimiento' => $vale->fecha_vencimiento_f,
                'litros' => $vale->litros,
                'precio' => $vale->precio,
                'total' => round($vale->litros * $vale->precio, 2),
                'tipo_combustible' => $vale->tipoCombustible?->tipo_combustible,
                'vehiculo' => $vale->vehiculo ? [
                    'codigo' => $vale->vehiculo->codigo,
                    'nro_placa' => $vale->vehiculo->nro_placa,
                    'marca' => $vale->vehiculo->marca,
                    'modelo' => $vale->vehiculo->modelo,
                    'anio' => $vale->vehiculo->anio,
                ] : null,
                'conductor' => $vale->conductor ? [
                    'nombre_completo' => $vale->conductor->persona?->nombre_completo,
                    'ci' => $vale->conductor->persona?->ci,
                ] : null,
                'grifo' => $vale->grifo ? [
                    'razon_social' => $vale->grifo->razon_social,
                    'direccion' => $vale->grifo->direccion,
                    'ciudad' => $vale->grifo->ciudad,
                ] : null,
                'usado_en' => $usadoEn,
            ],
            'empresa' => $this->datosEmpresa(),
        ]);
    }

    /**
     * @return array{nombre: ?string, logo_url: ?string}
     */
    private function datosEmpresa(): array
    {
        $parametrosEmpresa = ParametrosEmpresa::first();
        $logo = $parametrosEmpresa?->logo_empresa;

        return [
            'nombre' => $parametrosEmpresa?->nombre_empresa,
            'logo_url' => $logo && Storage::disk('public')->exists($logo)
                ? Storage::disk('public')->url($logo)
                : null,
        ];
    }
}

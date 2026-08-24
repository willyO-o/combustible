<?php

namespace App\ValueObjects;

class ValeConfig implements \JsonSerializable
{
    public function __construct(
        public int $tiempo_expiracion = 1,
        // Mes (1-12, numeración estándar de PHP: 1 = enero, 12 = diciembre)
        // a partir del cual las series numeradas por gestión (vales, órdenes
        // de trabajo, solicitudes de mantenimiento...) empiezan a
        // corresponder a la gestión siguiente. Por defecto 12 (diciembre):
        // el ciclo contable coincide con el año calendario, sin adelanto.
        public int $mes_ciclo_contable = 12,
        // Cantidad de dígitos con la que se rellenan (con ceros a la
        // izquierda) los números de serie (nro_vale, nro_orden,
        // nro_solicitud...), ej. 6 dígitos = 000003. Por defecto 6.
        public int $digitos_serie = 6,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tiempo_expiracion: $data['tiempo_expiracion'] ?? 1,
            mes_ciclo_contable: $data['mes_ciclo_contable'] ?? 12,
            digitos_serie: $data['digitos_serie'] ?? 6,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'tiempo_expiracion' => $this->tiempo_expiracion,
            'mes_ciclo_contable' => $this->mes_ciclo_contable,
            'digitos_serie' => $this->digitos_serie,
        ];
    }
}

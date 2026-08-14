<?php

namespace App\ValueObjects;

class ValeConfig implements \JsonSerializable
{
    public function __construct(
        public int $tiempo_expiracion = 1,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tiempo_expiracion: $data['tiempo_expiracion'] ?? 1,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'tiempo_expiracion' => $this->tiempo_expiracion,
        ];
    }
}

<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use App\ValueObjects\ValeConfig;

class ParametrosVale implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value) || $value === '') {
            return ValeConfig::fromArray([]);
        }

        $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return ValeConfig::fromArray($data);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {

        if (is_array($value)) {
            $value = ValeConfig::fromArray($value);
        }

        if (!$value instanceof ValeConfig) {
            throw new \InvalidArgumentException('The given value is not an instance of ValeConfig.');
        }


        return json_encode($value->jsonSerialize(), JSON_THROW_ON_ERROR);
    }
}

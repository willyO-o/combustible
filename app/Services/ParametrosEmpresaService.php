<?php

namespace App\Services;

use App\Models\ParametrosEmpresa;
use Illuminate\Support\Facades\Cache;

class ParametrosEmpresaService
{
    protected const CACHE_KEY = 'parametros_empresa';

    public function get(): ParametrosEmpresa
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return ParametrosEmpresa::firstOrFail();
        });
    }

    public function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

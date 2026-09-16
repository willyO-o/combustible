<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Aviso diario de vales de combustible próximos a vencer (ver
// NotificarValesPorVencerCommand).
Schedule::command('vales:notificar-vencimiento')->daily();

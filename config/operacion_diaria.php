<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Regularización de operaciones diarias
    |--------------------------------------------------------------------------
    |
    | Correos de los usuarios encargados de la regularización (separados por
    | coma). Sólo ellos activan/programan el modo estricto y habilitan a un
    | usuario a registrar días pasados (User::esEncargadoDeRegularizacion()).
    | No es un permiso de Roles a propósito: ni el super-admin entra si su
    | correo no está aquí. Tras cambiarlo en producción: php artisan config:cache.
    |
    */

    'regularizacion' => [
        'encargados' => array_values(array_filter(array_map(
            fn (string $correo) => mb_strtolower(trim($correo)),
            explode(',', (string) env('OPERACION_REGULARIZACION_ENCARGADOS', '')),
        ))),
    ],

];

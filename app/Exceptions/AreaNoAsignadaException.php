<?php

namespace App\Exceptions;

use Exception;

class AreaNoAsignadaException extends Exception
{

    public function __construct(string $message = 'El vehículo no tiene un área asignada.')
    {
        parent::__construct($message);
    }
}

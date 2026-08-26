<?php

namespace App\Exceptions;

use Exception;

class ConductorNoAsignadoException extends Exception
{
    public function __construct(string $message = 'El vehículo no tiene un conductor asignado actualmente.')
    {
        parent::__construct($message);
    }
}

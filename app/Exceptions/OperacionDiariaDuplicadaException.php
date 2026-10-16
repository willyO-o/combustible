<?php

namespace App\Exceptions;

use Exception;

class OperacionDiariaDuplicadaException extends Exception
{
    public function __construct(string $message = 'El vehículo ya tiene una operación diaria registrada en esa fecha.')
    {
        parent::__construct($message);
    }
}

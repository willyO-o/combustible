<?php

use Luecano\NumeroALetras\NumeroALetras;

if (!function_exists('utf8Decode')) {
    function utf8Decode(string|null $string): string
    {
        if (empty($string)) {
            return '';
        }
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $string);
    }
}


if (!function_exists('eliminarEspaciosMultiples')) {
    function eliminarEspaciosMultiples(string $cadena): string
    {

        if (!$cadena) {
            return $cadena;
        }

        $cadena = trim(preg_replace('/\s+/', ' ', $cadena));
        return $cadena;
    }
}
if (!function_exists('monedaLiteral')) {
    function monedaLiteral($numero, $moneda = 'BOLIVIANOS'): string
    {

        if (!is_numeric($numero)) {
            return '';
        }
        $formatter = new NumeroALetras();

        $literal = $formatter->toInvoice($numero, 2, $moneda);
        return $literal;
    }
}


if (!function_exists('numeroLiteral')) {
    function numeroLiteral($numero, $medida = ''): string
    {

        if (!is_numeric($numero)) {
            return '';
        }
        $formatter = new NumeroALetras();
        // formatear el número  y de manera textual  sin usar to Invoice, solo el número y la medida
        $literal = $formatter->toWords($numero, 2);
        return $literal.' '.$medida;
    }
}

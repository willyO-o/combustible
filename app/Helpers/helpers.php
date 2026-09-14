<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Luecano\NumeroALetras\NumeroALetras;

if (! function_exists('utf8Decode')) {
    function utf8Decode(?string $string): string
    {
        if (empty($string)) {
            return '';
        }

        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $string);
    }
}

if (! function_exists('eliminarEspaciosMultiples')) {
    function eliminarEspaciosMultiples(string $cadena): string
    {

        if (! $cadena) {
            return $cadena;
        }

        $cadena = trim(preg_replace('/\s+/', ' ', $cadena));

        return $cadena;
    }
}
if (! function_exists('monedaLiteral')) {
    function monedaLiteral($numero, $moneda = 'BOLIVIANOS'): string
    {

        if (! is_numeric($numero)) {
            return '';
        }
        $formatter = new NumeroALetras;

        $literal = $formatter->toInvoice($numero, 2, $moneda);

        return $literal;
    }
}

if (! function_exists('numeroLiteral')) {
    function numeroLiteral($numero, $medida = ''): string
    {

        if (! is_numeric($numero)) {
            return '';
        }
        $formatter = new NumeroALetras;
        // formatear el número  y de manera textual  sin usar to Invoice, solo el número y la medida
        $literal = $formatter->toWords($numero, 2);

        return $literal.' '.$medida;
    }
}

if (! function_exists('convertirImagenAWebp')) {
    /**
     * Guarda una foto subida (evidencia, fotografía, etc.) convertida a
     * .webp, para que pese mucho menos que el JPEG/PNG original de una
     * cámara de celular. Usa GD (viene con PHP, sin librerías nuevas):
     * corrige la orientación EXIF de fotos de celular tomadas en vertical,
     * redimensiona si excede $ladoMaximoPx (conservando proporción) y
     * codifica a webp con $calidad. Devuelve la ruta relativa dentro del
     * disco, exactamente como devuelve UploadedFile::store() — mismo
     * contrato, así que reemplaza ese llamado sin tocar el resto del código
     * que ya guarda esa ruta en una columna varchar.
     *
     * Si GD no logra leer el archivo (formato no soportado/corrupto), cae
     * de vuelta a guardar el original tal cual, para no romper el flujo.
     */
    function convertirImagenAWebp(
        UploadedFile $archivo,
        string $directorio,
        string $disco = 'public',
        int $calidad = 80,
        int $ladoMaximoPx = 1920
    ): string {
        $imagen = @imagecreatefromstring(file_get_contents($archivo->getRealPath()));

        if ($imagen === false) {
            return $archivo->store($directorio, $disco);
        }

        if ($archivo->getMimeType() === 'image/jpeg') {
            $imagen = corregirOrientacionExif($imagen, $archivo->getRealPath());
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        if (max($ancho, $alto) > $ladoMaximoPx) {
            $factor = $ladoMaximoPx / max($ancho, $alto);
            $anchoNuevo = max(1, (int) round($ancho * $factor));
            $altoNuevo = max(1, (int) round($alto * $factor));

            $redimensionada = imagecreatetruecolor($anchoNuevo, $altoNuevo);
            imagecopyresampled($redimensionada, $imagen, 0, 0, 0, 0, $anchoNuevo, $altoNuevo, $ancho, $alto);
            imagedestroy($imagen);
            $imagen = $redimensionada;
        }

        $directorio = trim($directorio, '/');
        $rutaRelativa = $directorio.'/'.Str::random(40).'.webp';

        // imagewebp() escribe con funciones nativas de filesystem (no pasa
        // por el disco de Storage), así que el directorio destino se crea
        // primero a través del disco para respetar dónde apunte cada uno.
        Storage::disk($disco)->makeDirectory($directorio);
        imagewebp($imagen, Storage::disk($disco)->path($rutaRelativa), $calidad);
        imagedestroy($imagen);

        return $rutaRelativa;
    }
}

if (! function_exists('corregirOrientacionExif')) {
    /**
     * Rota un recurso de imagen GD según el tag EXIF "Orientation" de un
     * JPEG (típico de fotos tomadas con celular en vertical, que la cámara
     * guarda "acostadas" + un flag de rotación). Sin esto, la evidencia
     * convertida a webp queda de lado o al revés en cualquier visor que no
     * aplique EXIF por su cuenta (a diferencia de la mayoría de apps de
     * galería, que sí lo hacen). Silencioso ante cualquier error (exif_read_data
     * lanza warning con archivos sin datos EXIF) o si la extensión exif no
     * está habilitada: en ese caso devuelve la imagen sin rotar.
     */
    function corregirOrientacionExif($imagen, string $rutaArchivo)
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($rutaArchivo);
        $orientacion = $exif['Orientation'] ?? 1;

        return match ($orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => $imagen,
        };
    }
}

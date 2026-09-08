<?php

/**
 * generar_fuentes.php
 * -------------------------------------------------------------
 * Convertidor de las fuentes Calibri (Microsoft Fluent Fonts)
 * al formato de definición de fuente que usa FPDF (.php + .z).
 *
 * Uso:
 *   - Por navegador:  http://localhost/fontcalibri/fpdf-calibri/generar_fuentes.php
 *   - Por consola:    php generar_fuentes.php
 *
 * Requisitos: PHP con la extensión zlib activada (para comprimir el .z).
 *
 * Genera en la carpeta ./font/:
 *   calibri.php   / calibri.z     -> Calibri Regular
 *   calibrib.php  / calibrib.z    -> Calibri Bold
 *   calibrii.php  / calibrii.z    -> Calibri Italic
 *   calibribi.php / calibribi.z   -> Calibri Bold Italic
 * -------------------------------------------------------------
 */
define('MAKEFONT_AS_LIBRARY', true); // evita que makefont.php intente ejecutar su CLI
require __DIR__.'/makefont/makefont.php';

$cli = (PHP_SAPI === 'cli');
$nl = $cli ? "\n" : "<br>\n";
if (! $cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font:14px/1.5 monospace">';
}

if (! function_exists('gzcompress')) {
    exit('ERROR: la extensión zlib de PHP no está disponible. Actívala en php.ini.'.$nl);
}

// TTF de origen  =>  nombre base del archivo de definición FPDF
$fuentes = [
    'makefont/Fluent_Calibri.ttf' => 'calibri',
    'makefont/Fluent_Calibri-Bold.ttf' => 'calibrib',
    'makefont/Fluent_Calibri-Italic.ttf' => 'calibrii',
    'makefont/Fluent_Calibri-BoldItalic.ttf' => 'calibribi',
];

$destino = __DIR__.'/font';
if (! is_dir($destino) && ! mkdir($destino, 0775, true)) {
    exit('ERROR: no se pudo crear la carpeta '.$destino.$nl);
}

$cwd = getcwd();
chdir($destino); // MakeFont() escribe en el directorio actual

foreach ($fuentes as $ttfRel => $base) {
    $ttf = __DIR__.'/'.$ttfRel;
    if (! is_file($ttf)) {
        echo "OMITIDA (no encontrada): $ttfRel".$nl;

        continue;
    }

    echo "Procesando $ttfRel ...".$nl;

    // enc = cp1252  (Latin-1 / Windows Occidental: cubre español, €, comillas, etc.)
    // embed = true  (incrusta la fuente en el PDF)
    // subset = true (solo incrusta los glifos de cp1252 -> PDF más ligero)
    MakeFont($ttf, 'cp1252', true, true);

    // MakeFont nombra los archivos según el basename del TTF; los renombramos
    // al convenio de FPDF (calibri, calibrib, calibrii, calibribi).
    $srcBase = pathinfo($ttf, PATHINFO_FILENAME); // p.ej. "Fluent_Calibri-Bold"
    foreach (['z', 'php'] as $ext) {
        $src = "$srcBase.$ext";
        $dst = "$base.$ext";
        if (is_file($src)) {
            @unlink($dst);
            rename($src, $dst);
            echo "  -> font/$dst".$nl;
        }
    }
    // El .php generado apunta al .z con el nombre del TTF original;
    // lo reescribimos al nuevo nombre ($base.z).
    $php = file_get_contents("$base.php");
    $php = str_replace("\$file = '$srcBase.z'", "\$file = '$base.z'", $php);
    file_put_contents("$base.php", $php);
    echo $nl;
}

chdir($cwd);

echo "LISTO. Copia la carpeta 'font/' a tu proyecto y añade las 4 fuentes con AddFont().".$nl;
if (! $cli) {
    echo '</pre>';
}

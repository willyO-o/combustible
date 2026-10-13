<?php

namespace Tests\Unit;

use App\Libraries\Reportes;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ReportesTest extends TestCase
{
    private function textoCalibri(?string $texto): string
    {
        $metodo = new ReflectionMethod(Reportes::class, 'textoCalibri');

        return $metodo->invoke(new Reportes, $texto);
    }

    /**
     * Regresión: con empty() el string '0' se descartaba y cualquier total o
     * cantidad en cero quedaba en blanco en los PDF.
     */
    public function test_texto_calibri_conserva_el_cero(): void
    {
        $this->assertSame('0', $this->textoCalibri('0'));
    }

    public function test_texto_calibri_devuelve_vacio_para_null_y_string_vacio(): void
    {
        $this->assertSame('', $this->textoCalibri(null));
        $this->assertSame('', $this->textoCalibri(''));
    }

    public function test_texto_calibri_convierte_a_cp1252(): void
    {
        $this->assertSame(iconv('UTF-8', 'CP1252', 'VEHÍCULOS — Diésel'), $this->textoCalibri('VEHÍCULOS — Diésel'));
    }

    /**
     * Regresión: con bytes UTF-8 inválidos iconv devolvía false y, como el
     * método promete un string, el PDF completo caía con un error 500.
     */
    public function test_texto_calibri_no_falla_con_bytes_utf8_invalidos(): void
    {
        $resultado = $this->textoCalibri("Volvo\xFF \xC3\x28 FH");

        $this->assertStringStartsWith('Volvo', $resultado);
        $this->assertStringEndsWith('FH', $resultado);
    }

    public function test_texto_calibri_no_falla_con_caracteres_fuera_de_cp1252(): void
    {
        $resultado = $this->textoCalibri('Placa 🚛 中文 — Ñandú');

        $this->assertStringStartsWith('Placa ', $resultado);
        $this->assertStringContainsString(iconv('UTF-8', 'CP1252', '— Ñandú'), $resultado);
    }
}

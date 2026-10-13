<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\EncargadoArea;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersonaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    public function test_crea_una_persona_sin_usuario(): void
    {
        $response = $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '11111111',
            'nombres' => 'Juan',
            'paterno' => 'Perez',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'personal',
            'crear_usuario' => false,
        ]);

        $response->assertRedirect(route('personas.index'));

        $persona = Persona::where('ci', '11111111')->firstOrFail();
        $this->assertNull($persona->user);
        $this->assertNull($persona->conductor);
    }

    public function test_crea_una_persona_conductor_con_usuario_y_vehiculo_asignado(): void
    {
        $vehiculo = Vehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '22222222',
            'nombres' => 'Carlos',
            'paterno' => 'Gomez',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'conductor',
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
            'crear_usuario' => true,
            'email' => 'carlos.gomez@example.com',
            'estado_usuario' => 'ACTIVO',
        ]);

        $response->assertRedirect(route('personas.index'));

        $persona = Persona::where('ci', '22222222')->firstOrFail();

        $this->assertNotNull($persona->user);
        $this->assertSame('carlos.gomez@example.com', $persona->user->email);
        $this->assertTrue($persona->user->hasRole('conductor'));
        $this->assertTrue(Hash::check($persona->ci.'#Plusmetals', $persona->user->password));

        $this->assertDatabaseHas('conductor', ['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    public function test_crea_una_persona_jefe_de_area_con_encargo(): void
    {
        $area = Area::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '33333333',
            'nombres' => 'Maria',
            'paterno' => 'Lopez',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'jefe-area',
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'crear_usuario' => true,
            'email' => 'maria.lopez@example.com',
            'estado_usuario' => 'ACTIVO',
        ]);

        $response->assertRedirect(route('personas.index'));

        $persona = Persona::where('ci', '33333333')->firstOrFail();

        $this->assertTrue($persona->user->hasRole('jefe-area'));
        $this->assertDatabaseHas('encargado_area', [
            'id_persona' => $persona->id,
            'id_area' => $area->id,
            'estado_encargo' => 'ACTIVO',
        ]);
    }

    public function test_editar_conductor_reasigna_vehiculo(): void
    {
        $vehiculoInicial = Vehiculo::factory()->create();
        $vehiculoNuevo = Vehiculo::factory()->create();

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '44444444',
            'nombres' => 'Pedro',
            'paterno' => 'Rojas',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'conductor',
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculoInicial->id,
            'crear_usuario' => false,
        ]);

        $persona = Persona::where('ci', '44444444')->firstOrFail();

        $this->actingAs($this->admin)->post(route('personas.update', $persona->id), [
            '_method' => 'PUT',
            'ci' => '44444444',
            'nombres' => 'Pedro',
            'paterno' => 'Rojas',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'conductor',
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculoNuevo->id,
            'crear_usuario' => false,
        ]);

        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculoInicial->id,
            'estado_asignacion' => 'REASIGNADO',
        ]);
        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculoNuevo->id,
            'estado_asignacion' => 'ACTIVO',
        ]);

        $asignacionAnterior = Asignacion::where('id_vehiculo', $vehiculoInicial->id)->firstOrFail();
        $this->assertNotNull($asignacionAnterior->fecha_culminacion);
    }

    public function test_editar_persona_de_conductor_a_personal_cierra_asignacion_activa(): void
    {
        $vehiculo = Vehiculo::factory()->create();

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '55555555',
            'nombres' => 'Ana',
            'paterno' => 'Diaz',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'conductor',
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
            'crear_usuario' => true,
            'email' => 'ana.diaz@example.com',
            'estado_usuario' => 'ACTIVO',
        ]);

        $persona = Persona::where('ci', '55555555')->firstOrFail();

        $this->actingAs($this->admin)->post(route('personas.update', $persona->id), [
            '_method' => 'PUT',
            'ci' => '55555555',
            'nombres' => 'Ana',
            'paterno' => 'Diaz',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'personal',
            'crear_usuario' => true,
            'email' => 'ana.diaz@example.com',
            'estado_usuario' => 'ACTIVO',
        ]);

        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'INACTIVO',
        ]);

        $this->assertFalse($persona->user->fresh()->hasRole('conductor'));
    }

    public function test_login_bloqueado_para_usuario_inactivo(): void
    {
        $user = User::factory()->create(['estado_usuario' => 'INACTIVO']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_index_lista_personas_con_jefe_de_area(): void
    {
        $area = Area::factory()->create();

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '66666666',
            'nombres' => 'Luis',
            'paterno' => 'Vargas',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'jefe-area',
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'crear_usuario' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('personas.index'));

        $response->assertOk();
    }

    public function test_edit_carga_datos_de_jefe_de_area(): void
    {
        $area = Area::factory()->create();

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '77777777',
            'nombres' => 'Sofia',
            'paterno' => 'Mamani',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'jefe-area',
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'crear_usuario' => false,
        ]);

        $persona = Persona::where('ci', '77777777')->firstOrFail();

        $response = $this->actingAs($this->admin)->get(route('personas.edit', $persona->id));

        $response->assertOk();
    }

    /**
     * Abre el .xlsx de la respuesta y devuelve sus celdas (valores crudos,
     * sin formatear) como colección de filas.
     *
     * @return Collection<int, array<int, mixed>>
     */
    private function celdasDelExcel(string $contenido): Collection
    {
        $archivo = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($archivo, $contenido);

        try {
            return collect(IOFactory::load($archivo)->getActiveSheet()->toArray(null, true, false));
        } finally {
            @unlink($archivo);
        }
    }

    /**
     * Valores de las tarjetas de resumen del Excel (fila de etiquetas
     * seguida de la fila de valores), indexados por etiqueta.
     *
     * @return array<string, int>
     */
    private function tarjetasDelExcel(Collection $celdas, string $primeraEtiqueta): array
    {
        $indice = $celdas->search(fn ($fila) => in_array($primeraEtiqueta, $fila, true));
        $this->assertNotFalse($indice, "El Excel no tiene la tarjeta {$primeraEtiqueta}.");

        $tarjetas = [];
        foreach ($celdas[$indice] as $columna => $etiqueta) {
            if ($etiqueta !== null) {
                $tarjetas[$etiqueta] = (int) $celdas[$indice + 1][$columna];
            }
        }

        return $tarjetas;
    }

    public function test_exporta_el_listado_de_personas_en_pdf(): void
    {
        Persona::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('personas.reporte.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('listado_personas_', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /**
     * El tipo sigue el mismo criterio que la vista (conductor > jefe de área
     * > personal > sin asignar), con las áreas a cargo y la cuenta de usuario.
     */
    public function test_el_excel_de_personas_trae_tipo_areas_y_usuario(): void
    {
        $jefe = Persona::factory()->create(['ci' => '5550001', 'nombres' => 'Ana', 'paterno' => 'Rojas', 'materno' => 'Lima', 'fecha_nacimiento' => now()->subYears(40)->subDay()]);
        $area = Area::factory()->create(['nombre_area' => 'Mina Norte']);
        EncargadoArea::create(['id_persona' => $jefe->id, 'id_area' => $area->id, 'tipo_encargo' => 'TITULAR', 'fecha_inicio' => now(), 'estado_encargo' => 'ACTIVO']);
        User::factory()->create(['id_persona' => $jefe->id, 'email' => 'ana.rojas@empresa.com']);

        $conductor = Persona::factory()->create(['ci' => '5550002']);
        Conductor::factory()->create(['id' => $conductor->id]);

        Persona::factory()->create(['ci' => '5550003', 'fecha_nacimiento' => null]);

        $celdas = $this->celdasDelExcel(
            $this->actingAs($this->admin)->get(route('personas.reporte.excel'))->assertOk()->getContent()
        );
        $filaDe = fn (string $ci) => $celdas->first(fn ($f) => in_array($ci, $f, true));

        $filaJefe = $filaDe('5550001');
        $this->assertSame(['5550001', 'Ana', 'Rojas', 'Lima'], array_slice($filaJefe, 1, 4));
        $this->assertSame(now()->subYears(40)->subDay()->format('d/m/Y'), $filaJefe[6]);
        $this->assertSame(40, (int) $filaJefe[7]);
        $this->assertIsNumeric($filaJefe[7], 'La edad debe escribirse como número.');
        $this->assertSame('Jefe de Área', $filaJefe[8]);
        $this->assertSame('Mina Norte', $filaJefe[9]);
        $this->assertSame('ana.rojas@empresa.com', $filaJefe[10]);
        $this->assertSame('ACTIVO', $filaJefe[11]);

        $this->assertSame('Conductor', $filaDe('5550002')[8]);

        $filaSinNada = $filaDe('5550003');
        // Sin relaciones ligadas: celdas en blanco, sin textos de relleno.
        $this->assertNull($filaSinNada[8]);
        $this->assertNull($filaSinNada[9]);
        $this->assertNull($filaSinNada[6]);
        $this->assertNull($filaSinNada[7]);
        $this->assertNull($filaSinNada[10]);
        $this->assertNull($filaSinNada[11]);

        $this->actingAs($this->admin)->get(route('personas.reporte.pdf'))->assertOk();
    }

    public function test_los_reportes_de_personas_respetan_los_filtros(): void
    {
        Persona::factory()->create(['ci' => '4440001', 'paterno' => 'Mamani']);
        Persona::factory()->create(['ci' => '4440002', 'paterno' => 'Choque']);

        $celdas = $this->celdasDelExcel(
            $this->actingAs($this->admin)->get(route('personas.reporte.excel', ['paterno' => 'Mamani']))->assertOk()->getContent()
        )->flatten();

        $this->assertTrue($celdas->contains('4440001'));
        $this->assertFalse($celdas->contains('4440002'), 'Una persona fuera del filtro no debe exportarse.');
        $this->assertTrue($celdas->contains('Filtro aplicado: Apellido paterno: Mamani'));

        $sinResultados = ['ci' => 'NO-EXISTE'];
        $this->actingAs($this->admin)->get(route('personas.reporte.pdf', $sinResultados))->assertOk();
        $this->assertTrue(
            $this->celdasDelExcel($this->actingAs($this->admin)->get(route('personas.reporte.excel', $sinResultados))->getContent())
                ->flatten()->contains('No hay personas que coincidan con los filtros seleccionados.'),
        );
    }

    public function test_el_excel_de_personas_cuenta_por_estado_y_con_usuario(): void
    {
        $conUsuario = Persona::factory()->create(['estado_persona' => 'ACTIVO']);
        User::factory()->create(['id_persona' => $conUsuario->id]);
        Persona::factory()->create(['estado_persona' => 'INACTIVO']);
        Persona::factory()->create(['estado_persona' => 'RETIRADO']);

        $celdas = $this->celdasDelExcel(
            $this->actingAs($this->admin)->get(route('personas.reporte.excel'))->assertOk()->getContent()
        );

        $this->assertTrue($celdas->flatten()->contains('Sin filtros: todas las personas registradas'));
        $this->assertSame([
            'TOTAL PERSONAS' => 3,
            'ACTIVAS' => 1,
            'INACTIVAS' => 1,
            'RETIRADAS' => 1,
            'CON USUARIO' => 1,
        ], $this->tarjetasDelExcel($celdas, 'TOTAL PERSONAS'));
    }

    /**
     * Texto impreso en el PDF: FPDF comprime cada stream con zlib y escribe
     * el texto como "(...) Tj"; se ignoran los streams de imagen (logo).
     */
    private function textoDelPdf(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

        return collect($streams[1])
            ->map(fn (string $stream) => @gzuncompress($stream) ?: $stream)
            ->flatMap(fn (string $contenido) => preg_match_all('/\((.*?)(?<!\\\\)\)\s*Tj/s', $contenido, $textos) ? $textos[1] : [])
            ->implode("\n");
    }

    public function test_el_pdf_de_personas_deja_en_blanco_lo_que_no_tiene_registros_ligados(): void
    {
        $sinNada = Persona::factory()->create(['ci' => '7770001', 'celular' => null, 'fecha_nacimiento' => null]);

        $texto = $this->textoDelPdf(
            $this->actingAs($this->admin)->get(route('personas.reporte.pdf'))->assertOk()->getContent()
        );

        $this->assertStringContainsString($sinNada->ci, $texto, 'No se pudo leer el texto del PDF.');
        foreach (['Sin asignar', 'Sin usuario', "\x97"] as $relleno) {
            $this->assertStringNotContainsString($relleno, $texto);
        }
    }

    public function test_si_falla_un_reporte_de_personas_vuelve_al_listado_con_un_aviso(): void
    {
        Exceptions::fake();
        Persona::factory()->create(['paterno' => 'Mamani']);
        DB::statement('ALTER TABLE encargado_area RENAME TO encargado_area_no_disponible');

        $this->actingAs($this->admin)->get(route('personas.reporte.pdf', ['paterno' => 'Mamani']))
            ->assertRedirect(route('personas.index', ['paterno' => 'Mamani']))
            ->assertSessionHas('error', 'No se pudo generar el reporte en PDF. Intente nuevamente o contacte al administrador.');

        $this->actingAs($this->admin)->get(route('personas.reporte.excel', ['paterno' => 'Mamani']))
            ->assertRedirect(route('personas.index', ['paterno' => 'Mamani']))
            ->assertSessionHas('error');

        Exceptions::assertReported(QueryException::class);
    }

    public function test_los_reportes_de_personas_requieren_iniciar_sesion(): void
    {
        $this->get(route('personas.reporte.pdf'))->assertRedirect(route('login'));
        $this->get(route('personas.reporte.excel'))->assertRedirect(route('login'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Cargo;
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
use Inertia\Testing\AssertableInertia as Assert;
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
            'roles' => [],
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
            'roles' => ['conductor'],
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

    /**
     * @return array<string, mixed>
     */
    private function payloadConductorConUsuario(array $extra = []): array
    {
        return array_merge([
            'ci' => '7770001',
            'nombres' => 'Rosa',
            'paterno' => 'Mamani',
            'estado_persona' => 'ACTIVO',
            'roles' => ['conductor'],
            'estado_conductor' => 'ACTIVO',
            'crear_usuario' => true,
            'email' => 'rosa.mamani@example.com',
            'estado_usuario' => 'ACTIVO',
        ], $extra);
    }

    public function test_crea_el_usuario_con_la_contrasena_inicial_escrita_en_el_formulario(): void
    {
        $this->actingAs($this->admin)
            ->post(route('personas.store'), $this->payloadConductorConUsuario(['password' => 'Clave2026segura']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('personas.index'));

        $usuario = Persona::where('ci', '7770001')->firstOrFail()->user;
        $this->assertTrue(Hash::check('Clave2026segura', $usuario->password));
        $this->assertFalse(Hash::check('7770001#Plusmetals', $usuario->password));
    }

    public function test_con_la_contrasena_vacia_usa_el_formato_ci_plusmetals(): void
    {
        $this->actingAs($this->admin)
            ->post(route('personas.store'), $this->payloadConductorConUsuario(['password' => '']))
            ->assertSessionHasNoErrors();

        $usuario = Persona::where('ci', '7770001')->firstOrFail()->user;
        $this->assertTrue(Hash::check('7770001#Plusmetals', $usuario->password));
    }

    public function test_rechaza_una_contrasena_inicial_debil_sin_crear_nada(): void
    {
        foreach (['abc', 'soloLetrasSinNumero', '12345678'] as $debil) {
            $this->actingAs($this->admin)
                ->post(route('personas.store'), $this->payloadConductorConUsuario(['password' => $debil]))
                ->assertSessionHasErrors('password');
        }

        $this->assertDatabaseMissing('persona', ['ci' => '7770001']);
        $this->assertDatabaseMissing('users', ['email' => 'rosa.mamani@example.com']);
    }

    public function test_la_contrasena_se_ignora_si_no_se_crea_usuario(): void
    {
        $this->actingAs($this->admin)
            ->post(route('personas.store'), $this->payloadConductorConUsuario([
                'crear_usuario' => false,
                'email' => '',
                // Valor que quedó en el campo oculto: no debe bloquear el guardado.
                'password' => 'x',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('personas.index'));

        $this->assertNull(Persona::where('ci', '7770001')->firstOrFail()->user);
    }

    public function test_al_editar_una_persona_sin_usuario_crea_la_cuenta_con_la_contrasena_escrita(): void
    {
        $persona = Persona::factory()->create(['ci' => '7770002', 'paterno' => 'Quispe']);

        $this->actingAs($this->admin)->post(route('personas.update', $persona->id), [
            '_method' => 'PUT',
            'ci' => '7770002',
            'nombres' => $persona->nombres,
            'paterno' => 'Quispe',
            'estado_persona' => 'ACTIVO',
            'roles' => [],
            'crear_usuario' => true,
            'email' => 'personal@example.com',
            'estado_usuario' => 'ACTIVO',
            'password' => 'Ingreso2026',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Ingreso2026', $persona->fresh()->user->password));
    }

    public function test_al_editar_una_persona_que_ya_tiene_usuario_no_cambia_su_contrasena(): void
    {
        $persona = Persona::factory()->create(['ci' => '7770003', 'paterno' => 'Choque']);
        $usuario = User::factory()->create(['id_persona' => $persona->id, 'email' => 'choque@example.com']);
        $hashAnterior = $usuario->password;

        $this->actingAs($this->admin)->post(route('personas.update', $persona->id), [
            '_method' => 'PUT',
            'ci' => '7770003',
            'nombres' => $persona->nombres,
            'paterno' => 'Choque',
            'estado_persona' => 'ACTIVO',
            'roles' => [],
            'crear_usuario' => true,
            'email' => 'choque@example.com',
            'estado_usuario' => 'ACTIVO',
            // Aunque llegue (incluso inválida), se descarta: la cuenta ya existe.
            'password' => 'x',
        ])->assertSessionHasNoErrors();

        $this->assertSame($hashAnterior, $usuario->fresh()->password);
    }

    public function test_verificar_ci_responde_que_no_existe(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('search.persona-por-ci', ['ci' => '5550001']))
            ->assertOk()
            ->assertExactJson(['existe' => false]);
    }

    public function test_verificar_ci_avisa_si_ya_existe_con_sus_datos(): void
    {
        $persona = Persona::factory()->create(['ci' => '5550002', 'nombres' => 'Ana', 'paterno' => 'Rojas', 'materno' => null]);
        User::factory()->create(['id_persona' => $persona->id]);

        $this->actingAs($this->admin)
            ->getJson(route('search.persona-por-ci', ['ci' => ' 5550002 ']))
            ->assertOk()
            ->assertJsonPath('existe', true)
            ->assertJsonPath('persona.id', $persona->id)
            ->assertJsonPath('persona.nombre_completo', 'Ana Rojas')
            ->assertJsonPath('persona.tipo', 'Personal')
            ->assertJsonPath('persona.eliminada', false)
            ->assertJsonPath('persona.url', route('personas.edit', $persona->id));
    }

    public function test_verificar_ci_de_un_conductor_enlaza_a_su_ficha(): void
    {
        $conductor = Conductor::factory()->create();
        $conductor->persona->update(['ci' => '5550003']);

        $this->actingAs($this->admin)
            ->getJson(route('search.persona-por-ci', ['ci' => '5550003']))
            ->assertJsonPath('persona.tipo', 'Conductor')
            ->assertJsonPath('persona.url', route('conductores.show', $conductor->id));
    }

    public function test_verificar_ci_ignora_a_la_persona_que_se_esta_editando(): void
    {
        $persona = Persona::factory()->create(['ci' => '5550004']);

        $this->actingAs($this->admin)
            ->getJson(route('search.persona-por-ci', ['ci' => '5550004', 'ignorar' => $persona->id]))
            ->assertExactJson(['existe' => false]);
    }

    /**
     * El CI es único en la base aun con borrado lógico: una persona eliminada
     * también impide volver a registrarlo, y hay que avisarlo.
     */
    public function test_verificar_ci_avisa_si_pertenece_a_una_persona_eliminada(): void
    {
        $persona = Persona::factory()->create(['ci' => '5550005']);
        $persona->delete();

        $this->actingAs($this->admin)
            ->getJson(route('search.persona-por-ci', ['ci' => '5550005']))
            ->assertJsonPath('existe', true)
            ->assertJsonPath('persona.eliminada', true)
            ->assertJsonPath('persona.url', null);

        // Y efectivamente el alta con ese CI se rechaza.
        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '5550005',
            'nombres' => 'Otro',
            'paterno' => 'Nombre',
            'estado_persona' => 'ACTIVO',
            'roles' => [],
            'crear_usuario' => false,
        ])->assertSessionHasErrors('ci');
    }

    /**
     * Rutas web: los errores no se responden en JSON (bootstrap/app.php sólo
     * lo hace para api/*), igual que el resto de buscadores. CiInput.vue
     * nunca envía un CI vacío y, ante cualquier respuesta inesperada, no avisa
     * ni bloquea (el servidor valida al guardar).
     */
    public function test_verificar_ci_exige_el_ci(): void
    {
        $this->actingAs($this->admin)
            ->get(route('search.persona-por-ci'))
            ->assertSessionHasErrors('ci');
    }

    public function test_verificar_ci_requiere_iniciar_sesion(): void
    {
        $this->get(route('search.persona-por-ci', ['ci' => '123']))->assertRedirect(route('login'));
    }

    public function test_crea_una_persona_jefe_de_area_con_encargo(): void
    {
        $area = Area::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '33333333',
            'nombres' => 'Maria',
            'paterno' => 'Lopez',
            'estado_persona' => 'ACTIVO',
            'roles' => ['jefe-area'],
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
            'roles' => ['conductor'],
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
            'roles' => ['conductor'],
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
            'roles' => ['conductor'],
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
            'roles' => [],
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
            'roles' => ['jefe-area'],
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
            'roles' => ['jefe-area'],
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
        $this->assertSame('Jefe de Área', $filaJefe[9]);
        $this->assertSame('Mina Norte', $filaJefe[10]);
        $this->assertSame('ana.rojas@empresa.com', $filaJefe[11]);
        $this->assertSame('ACTIVO', $filaJefe[12]);

        $this->assertSame('Conductor', $filaDe('5550002')[9]);

        $filaSinNada = $filaDe('5550003');
        // Sin relaciones ligadas: celdas en blanco, sin textos de relleno.
        $this->assertNull($filaSinNada[8], 'Sin cargo asignado: la celda CARGO queda en blanco.');
        $this->assertNull($filaSinNada[9]);
        $this->assertNull($filaSinNada[10]);
        $this->assertNull($filaSinNada[6]);
        $this->assertNull($filaSinNada[7]);
        $this->assertNull($filaSinNada[11]);
        $this->assertNull($filaSinNada[12]);

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
     * el texto como "(...) Tj" en los streams de contenido de página. Se
     * descartan las fuentes incrustadas (/Length1) y las imágenes: son
     * binarias y, según los caracteres usados (nombres aleatorios de las
     * factories), a veces contienen bytes que parecen "(...) Tj" y daban
     * falsos positivos intermitentes (ej. el byte \x97 de la raya).
     */
    private function textoDelPdf(string $pdf): string
    {
        preg_match_all('/<<([^<>]*(?:<<[^<>]*>>[^<>]*)*)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams, PREG_SET_ORDER);

        return collect($streams)
            ->reject(fn (array $stream) => preg_match('#/Length1|/Subtype\s*/Image|/Type\s*/XObject#', $stream[1]) === 1)
            ->map(fn (array $stream) => @gzuncompress($stream[2]) ?: $stream[2])
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

    public function test_el_formulario_ofrece_todos_los_roles_asignables_incluido_tecnico_de_mantenimiento(): void
    {
        Role::firstOrCreate(['name' => 'tecnico-mantenimiento', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->actingAs($this->admin)->get(route('personas.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personas/Create')
                ->where('roles', fn ($roles) => collect($roles)->pluck('name')->sort()->values()->all()
                    === ['administrador', 'conductor', 'jefe-area', 'tecnico-mantenimiento'])
            );
    }

    public function test_crea_una_persona_con_varios_roles_y_sus_registros(): void
    {
        Role::firstOrCreate(['name' => 'tecnico-mantenimiento', 'guard_name' => 'web']);
        $area = Area::factory()->create();
        $vehiculo = Vehiculo::factory()->create();

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '8880001',
            'nombres' => 'Luis',
            'paterno' => 'Quispe',
            'estado_persona' => 'ACTIVO',
            'roles' => ['conductor', 'jefe-area', 'tecnico-mantenimiento'],
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'crear_usuario' => true,
            'email' => 'luis.quispe@example.com',
            'estado_usuario' => 'ACTIVO',
        ])->assertSessionHasNoErrors()->assertRedirect(route('personas.index'));

        $persona = Persona::where('ci', '8880001')->firstOrFail();

        $this->assertTrue($persona->user->hasAllRoles(['conductor', 'jefe-area', 'tecnico-mantenimiento']));
        $this->assertDatabaseHas('conductor', ['id' => $persona->id]);
        $this->assertDatabaseHas('asignacion', ['id_conductor' => $persona->id, 'id_vehiculo' => $vehiculo->id, 'estado_asignacion' => 'ACTIVO']);
        $this->assertDatabaseHas('encargado_area', ['id_persona' => $persona->id, 'id_area' => $area->id, 'estado_encargo' => 'ACTIVO']);
    }

    public function test_los_roles_sin_registro_propio_exigen_crear_el_usuario(): void
    {
        Role::firstOrCreate(['name' => 'tecnico-mantenimiento', 'guard_name' => 'web']);

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '8880002',
            'nombres' => 'Ana',
            'paterno' => 'Flores',
            'estado_persona' => 'ACTIVO',
            'roles' => ['conductor', 'tecnico-mantenimiento'],
            'estado_conductor' => 'ACTIVO',
            'crear_usuario' => false,
        ])->assertSessionHasErrors(['roles.1']);

        $this->assertDatabaseMissing('persona', ['ci' => '8880002']);
    }

    public function test_conductor_y_jefe_de_area_no_exigen_usuario(): void
    {
        $area = Area::factory()->create();

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '8880003',
            'nombres' => 'Pedro',
            'paterno' => 'Choque',
            'estado_persona' => 'ACTIVO',
            'roles' => ['conductor', 'jefe-area'],
            'estado_conductor' => 'ACTIVO',
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'crear_usuario' => false,
        ])->assertSessionHasNoErrors()->assertRedirect(route('personas.index'));

        $persona = Persona::where('ci', '8880003')->firstOrFail();
        $this->assertNull($persona->user);
        $this->assertDatabaseHas('conductor', ['id' => $persona->id]);
        $this->assertDatabaseHas('encargado_area', ['id_persona' => $persona->id, 'estado_encargo' => 'ACTIVO']);
    }

    public function test_rechaza_un_rol_oculto_o_inexistente(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '8880004',
            'nombres' => 'Eva',
            'paterno' => 'Rojas',
            'estado_persona' => 'ACTIVO',
            'roles' => ['super-admin', 'rol-que-no-existe'],
            'crear_usuario' => true,
            'email' => 'eva@example.com',
            'estado_usuario' => 'ACTIVO',
        ])->assertSessionHasErrors(['roles.0', 'roles.1']);

        $this->assertDatabaseMissing('persona', ['ci' => '8880004']);
    }

    /**
     * Persona jefe de área + conductor con usuario, vehículo y área.
     *
     * @return array{0: Persona, 1: Area, 2: Vehiculo}
     */
    private function crearPersonaJefeYConductor(): array
    {
        $area = Area::factory()->create();
        $vehiculo = Vehiculo::factory()->create();

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '8880005',
            'nombres' => 'Raul',
            'paterno' => 'Mendoza',
            'estado_persona' => 'ACTIVO',
            'roles' => ['conductor', 'jefe-area'],
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'crear_usuario' => true,
            'email' => 'raul@example.com',
            'estado_usuario' => 'ACTIVO',
        ])->assertSessionHasNoErrors();

        return [Persona::where('ci', '8880005')->firstOrFail(), $area, $vehiculo];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadEdicion(Persona $persona, Area $area, array $roles): array
    {
        return [
            '_method' => 'PUT',
            'ci' => $persona->ci,
            'nombres' => $persona->nombres,
            'paterno' => $persona->paterno,
            'estado_persona' => 'ACTIVO',
            'roles' => $roles,
            'estado_conductor' => 'ACTIVO',
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'crear_usuario' => true,
            'email' => $persona->user->email,
            'estado_usuario' => 'ACTIVO',
        ];
    }

    public function test_editar_quitando_solo_jefe_de_area_conserva_el_conductor_y_su_vehiculo(): void
    {
        [$persona, $area, $vehiculo] = $this->crearPersonaJefeYConductor();

        $this->actingAs($this->admin)
            ->post(route('personas.update', $persona), $this->payloadEdicion($persona, $area, ['conductor']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('personas.index'));

        $usuario = $persona->user->fresh();
        $this->assertTrue($usuario->hasRole('conductor'));
        $this->assertFalse($usuario->hasRole('jefe-area'));
        $this->assertDatabaseHas('asignacion', ['id_conductor' => $persona->id, 'id_vehiculo' => $vehiculo->id, 'estado_asignacion' => 'ACTIVO']);
        $this->assertDatabaseHas('encargado_area', ['id_persona' => $persona->id, 'estado_encargo' => 'INACTIVO']);
        $this->assertDatabaseMissing('encargado_area', ['id_persona' => $persona->id, 'estado_encargo' => 'ACTIVO']);
    }

    public function test_editar_quitando_solo_conductor_conserva_el_encargo_de_area(): void
    {
        [$persona, $area, $vehiculo] = $this->crearPersonaJefeYConductor();

        $this->actingAs($this->admin)
            ->post(route('personas.update', $persona), $this->payloadEdicion($persona, $area, ['jefe-area']))
            ->assertSessionHasNoErrors();

        $usuario = $persona->user->fresh();
        $this->assertFalse($usuario->hasRole('conductor'));
        $this->assertTrue($usuario->hasRole('jefe-area'));
        $this->assertDatabaseHas('asignacion', ['id_conductor' => $persona->id, 'id_vehiculo' => $vehiculo->id, 'estado_asignacion' => 'INACTIVO']);
        $this->assertDatabaseHas('encargado_area', ['id_persona' => $persona->id, 'id_area' => $area->id, 'estado_encargo' => 'ACTIVO']);
    }

    public function test_editar_agrega_un_rol_sin_tocar_los_registros_existentes(): void
    {
        Role::firstOrCreate(['name' => 'tecnico-mantenimiento', 'guard_name' => 'web']);
        [$persona, $area, $vehiculo] = $this->crearPersonaJefeYConductor();

        $this->actingAs($this->admin)
            ->post(route('personas.update', $persona), $this->payloadEdicion($persona, $area, ['conductor', 'jefe-area', 'tecnico-mantenimiento']))
            ->assertSessionHasNoErrors();

        $this->assertTrue($persona->user->fresh()->hasAllRoles(['conductor', 'jefe-area', 'tecnico-mantenimiento']));
        $this->assertSame(1, EncargadoArea::where('id_persona', $persona->id)->count());
        $this->assertSame(1, Asignacion::where('id_conductor', $persona->id)->count());
        $this->assertDatabaseHas('encargado_area', ['id_persona' => $persona->id, 'estado_encargo' => 'ACTIVO']);
        $this->assertDatabaseHas('asignacion', ['id_conductor' => $persona->id, 'id_vehiculo' => $vehiculo->id, 'estado_asignacion' => 'ACTIVO']);
    }

    public function test_edit_precarga_los_roles_de_la_cuenta_y_de_sus_registros(): void
    {
        Role::firstOrCreate(['name' => 'tecnico-mantenimiento', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        [$persona] = $this->crearPersonaJefeYConductor();

        // La cuenta sólo refleja técnico + super-admin (oculto); conductor y
        // jefe-area se deducen de sus registros de dominio.
        $persona->user->syncRoles(['tecnico-mantenimiento', 'super-admin']);

        $this->actingAs($this->admin)->get(route('personas.edit', $persona))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Personas/Edit')
                ->where('rolesAsignados', fn ($roles) => collect($roles)->sort()->values()->all()
                    === ['conductor', 'jefe-area', 'tecnico-mantenimiento'])
            );
    }

    public function test_editar_una_persona_conserva_el_rol_oculto_super_admin(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        [$persona, $area] = $this->crearPersonaJefeYConductor();
        $persona->user->assignRole('super-admin');

        $this->actingAs($this->admin)
            ->post(route('personas.update', $persona), $this->payloadEdicion($persona, $area, ['conductor']))
            ->assertSessionHasNoErrors();

        $this->assertTrue($persona->user->fresh()->hasRole('super-admin'));
    }

    public function test_edit_no_vuelve_a_marcar_conductor_si_se_le_quito_el_rol_y_no_tiene_vehiculo(): void
    {
        $persona = Persona::factory()->create();
        Conductor::factory()->create(['id' => $persona->id]);
        $usuario = User::factory()->create(['id_persona' => $persona->id]);
        $usuario->assignRole('administrador');

        $this->actingAs($this->admin)->get(route('personas.edit', $persona))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('rolesAsignados', ['administrador']));
    }

    public function test_edit_marca_conductor_si_tiene_un_vehiculo_asignado_aunque_su_cuenta_no_tenga_el_rol(): void
    {
        $persona = Persona::factory()->create();
        $conductor = Conductor::factory()->create(['id' => $persona->id]);
        $usuario = User::factory()->create(['id_persona' => $persona->id]);
        $usuario->assignRole('jefe-area');
        Asignacion::create([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $this->actingAs($this->admin)->get(route('personas.edit', $persona))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('rolesAsignados', fn ($roles) => collect($roles)->sort()->values()->all() === ['conductor', 'jefe-area']));
    }

    public function test_edit_marca_conductor_a_una_persona_sin_cuenta_con_registro_de_conductor(): void
    {
        $persona = Persona::factory()->create();
        Conductor::factory()->create(['id' => $persona->id]);

        $this->actingAs($this->admin)->get(route('personas.edit', $persona))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('rolesAsignados', ['conductor']));
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadPersonaConCargo(array $extra = []): array
    {
        return array_merge([
            'ci' => '9990001',
            'nombres' => 'Lucía',
            'paterno' => 'Vargas',
            'estado_persona' => 'ACTIVO',
            'roles' => [],
            'crear_usuario' => false,
        ], $extra);
    }

    public function test_registra_la_persona_con_su_cargo(): void
    {
        $cargo = Cargo::where('cargo', 'Analista')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('personas.store'), $this->payloadPersonaConCargo(['id_cargo' => $cargo->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('personas.index'));

        $this->assertSame($cargo->id, Persona::where('ci', '9990001')->value('id_cargo'));
    }

    public function test_el_cargo_es_opcional(): void
    {
        $this->actingAs($this->admin)
            ->post(route('personas.store'), $this->payloadPersonaConCargo(['id_cargo' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Persona::where('ci', '9990001')->value('id_cargo'));
    }

    public function test_no_permite_asignar_un_cargo_inactivo(): void
    {
        $inactivo = Cargo::factory()->inactivo()->create();

        $this->actingAs($this->admin)
            ->post(route('personas.store'), $this->payloadPersonaConCargo(['id_cargo' => $inactivo->id]))
            ->assertSessionHasErrors(['id_cargo' => 'El cargo seleccionado no existe o está inactivo.']);

        $this->assertDatabaseMissing('persona', ['ci' => '9990001']);
    }

    public function test_al_editar_conserva_el_cargo_actual_aunque_se_haya_desactivado(): void
    {
        $cargo = Cargo::factory()->create(['cargo' => 'Cadenero']);
        $persona = Persona::factory()->create(['id_cargo' => $cargo->id, 'estado_persona' => 'ACTIVO']);
        $cargo->update(['estado_cargo' => 'INACTIVO']);

        $this->actingAs($this->admin)->get(route('personas.edit', $persona))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cargos', fn ($cargos) => collect($cargos)->pluck('cargo')->contains('Cadenero'))
            );

        $this->actingAs($this->admin)->post(route('personas.update', $persona), [
            '_method' => 'PUT',
            'ci' => $persona->ci,
            'nombres' => 'Nombre corregido',
            'paterno' => $persona->paterno ?? 'Perez',
            'estado_persona' => 'ACTIVO',
            'roles' => [],
            'id_cargo' => $cargo->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($cargo->id, $persona->fresh()->id_cargo);
        $this->assertSame('Nombre corregido', $persona->fresh()->nombres);
    }

    public function test_al_editar_se_puede_quitar_el_cargo(): void
    {
        $persona = Persona::factory()->create([
            'id_cargo' => Cargo::where('cargo', 'Supervisor')->value('id'),
            'estado_persona' => 'ACTIVO',
        ]);

        $this->actingAs($this->admin)->post(route('personas.update', $persona), [
            '_method' => 'PUT',
            'ci' => $persona->ci,
            'nombres' => $persona->nombres,
            'paterno' => $persona->paterno ?? 'Perez',
            'estado_persona' => 'ACTIVO',
            'roles' => [],
            'id_cargo' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull($persona->fresh()->id_cargo);
    }

    public function test_create_ofrece_solo_los_cargos_activos(): void
    {
        Cargo::factory()->inactivo()->create(['cargo' => 'Desactivado']);

        $this->actingAs($this->admin)->get(route('personas.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cargos', fn ($cargos) => collect($cargos)->pluck('cargo')->contains('Técnico')
                    && ! collect($cargos)->pluck('cargo')->contains('Desactivado'))
            );
    }

    public function test_el_listado_muestra_y_filtra_por_cargo(): void
    {
        $tecnico = Cargo::where('cargo', 'Técnico')->firstOrFail();
        Persona::factory()->create(['ci' => '8000001', 'id_cargo' => $tecnico->id]);
        Persona::factory()->create(['ci' => '8000002', 'id_cargo' => null]);

        $this->actingAs($this->admin)->get(route('personas.index', ['id_cargo' => $tecnico->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('personas.data', 1)
                ->where('personas.data.0.ci', '8000001')
                ->where('personas.data.0.cargo.cargo', 'Técnico')
                ->where('filters.id_cargo', (string) $tecnico->id)
                ->has('cargos')
            );

        $this->actingAs($this->admin)->get(route('personas.index', ['id_cargo' => 'ninguno']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('personas.data', fn ($personas) => collect($personas)->pluck('ci')->contains('8000002')
                    && ! collect($personas)->pluck('ci')->contains('8000001'))
            );
    }

    public function test_los_reportes_de_personas_traen_el_cargo_y_respetan_su_filtro(): void
    {
        $supervisor = Cargo::where('cargo', 'Supervisor')->firstOrFail();
        Persona::factory()->create(['ci' => '8100001', 'id_cargo' => $supervisor->id]);
        Persona::factory()->create(['ci' => '8100002']);

        $celdas = $this->celdasDelExcel(
            $this->actingAs($this->admin)
                ->get(route('personas.reporte.excel', ['id_cargo' => $supervisor->id]))
                ->assertOk()
                ->getContent()
        );

        $fila = $celdas->first(fn ($f) => in_array('8100001', $f, true));
        $this->assertSame('Supervisor', $fila[8]);
        $this->assertNull($celdas->first(fn ($f) => in_array('8100002', $f, true)));
        // El encabezado deja constancia del filtro por cargo.
        $this->assertNotNull($celdas->first(fn ($f) => collect($f)->contains(fn ($v) => is_string($v) && str_contains($v, 'Cargo: Supervisor'))));

        $this->actingAs($this->admin)->get(route('personas.reporte.pdf', ['id_cargo' => $supervisor->id]))->assertOk();
    }
}

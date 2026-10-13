<?php

namespace Tests\Feature;

use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\DocumentoConductor;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConductorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    public function test_show_incluye_los_datos_personales_el_vehiculo_actual_y_el_historial(): void
    {
        $conductor = Conductor::factory()->create();

        $vehiculoAnterior = Vehiculo::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculoAnterior->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'fecha_culminacion' => now()->subDay(),
            'estado_asignacion' => 'REASIGNADO',
            'detalle' => 'Asignación anterior',
        ]);

        $vehiculoActual = Vehiculo::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculoActual->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
            'detalle' => 'Asignación vigente',
        ]);

        $response = $this->actingAs($this->admin)->get(route('conductores.show', $conductor->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Conductores/Show')
            ->where('conductor.id', $conductor->id)
            ->where('conductor.persona.ci', $conductor->persona->ci)
            ->has('conductor.asignaciones_activas', 1)
            ->where('conductor.asignaciones_activas.0.id', $vehiculoActual->id)
            ->has('historialAsignaciones', 2)
            ->where('historialAsignaciones.0.vehiculo.id', $vehiculoActual->id)
            ->where('historialAsignaciones.0.estado_asignacion', 'ACTIVO')
            ->where('historialAsignaciones.1.vehiculo.id', $vehiculoAnterior->id)
            ->where('historialAsignaciones.1.estado_asignacion', 'REASIGNADO')
        );
    }

    public function test_show_de_un_conductor_sin_asignaciones_no_falla(): void
    {
        $conductor = Conductor::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('conductores.show', $conductor->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Conductores/Show')
            ->has('conductor.asignaciones_activas', 0)
            ->has('historialAsignaciones', 0)
        );
    }

    /**
     * Usuario con permiso para crear cuentas (el administrador real lo tiene
     * vía todosLosPermisos()).
     */
    private function adminConPermisoDeUsuarios(): User
    {
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'usuarios.crear', 'guard_name' => 'web']);
        $this->admin->givePermissionTo('usuarios.crear');

        return $this->admin;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadConductorConCuenta(array $extra = []): array
    {
        return array_merge([
            'ci' => '6660001',
            'nombres' => 'Mario',
            'paterno' => 'Flores',
            'estado_conductor' => 'ACTIVO',
            'crear_usuario' => '1',
            'email' => 'mario.flores@example.com',
            'estado_usuario' => 'ACTIVO',
        ], $extra);
    }

    public function test_store_crea_el_usuario_del_conductor_con_rol_conductor_y_contrasena_escrita(): void
    {
        $this->actingAs($this->adminConPermisoDeUsuarios())
            ->post(route('conductores.store'), $this->payloadConductorConCuenta(['password' => 'Operario2026']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('conductores.index'))
            ->assertSessionHas('success', 'Conductor registrado exitosamente, con su usuario de acceso.');

        $persona = Persona::where('ci', '6660001')->firstOrFail();
        $usuario = User::where('id_persona', $persona->id)->firstOrFail();

        $this->assertSame('mario.flores@example.com', $usuario->email);
        $this->assertSame('ACTIVO', $usuario->estado_usuario);
        $this->assertSame(['conductor'], $usuario->getRoleNames()->all());
        $this->assertTrue(Hash::check('Operario2026', $usuario->password));
        $this->assertDatabaseHas('conductor', ['id' => $persona->id]);
    }

    public function test_store_con_contrasena_vacia_usa_el_formato_ci_plusmetals(): void
    {
        $this->actingAs($this->adminConPermisoDeUsuarios())
            ->post(route('conductores.store'), $this->payloadConductorConCuenta(['password' => '']))
            ->assertSessionHasNoErrors();

        $usuario = User::where('email', 'mario.flores@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('6660001#Plusmetals', $usuario->password));
    }

    public function test_store_sin_marcar_crear_usuario_no_crea_cuenta_aunque_lleguen_datos(): void
    {
        $this->actingAs($this->adminConPermisoDeUsuarios())
            ->post(route('conductores.store'), $this->payloadConductorConCuenta([
                'crear_usuario' => '0',
                'email' => 'no-es-correo',
                'password' => 'x',
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Conductor registrado exitosamente.');

        $persona = Persona::where('ci', '6660001')->firstOrFail();
        $this->assertNull(User::where('id_persona', $persona->id)->first());
    }

    public function test_store_valida_la_cuenta_sin_crear_nada_si_hay_errores(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);
        $admin = $this->adminConPermisoDeUsuarios();

        $casos = [
            ['email' => ''],
            ['email' => 'no-es-correo'],
            ['email' => 'ocupado@example.com'],
            ['password' => 'corta1'],
            ['estado_usuario' => 'OTRO'],
        ];

        foreach ($casos as $caso) {
            $this->actingAs($admin)
                ->post(route('conductores.store'), $this->payloadConductorConCuenta($caso))
                ->assertSessionHasErrors(array_key_first($caso));
        }

        $this->assertDatabaseMissing('persona', ['ci' => '6660001']);
    }

    public function test_store_sin_permiso_de_usuarios_no_puede_crear_la_cuenta(): void
    {
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        $this->actingAs($this->admin)
            ->post(route('conductores.store'), $this->payloadConductorConCuenta())
            ->assertForbidden();

        $this->assertDatabaseMissing('persona', ['ci' => '6660001']);
        $this->assertDatabaseMissing('users', ['email' => 'mario.flores@example.com']);
    }

    public function test_store_no_deja_el_conductor_a_medias_si_falla_la_cuenta(): void
    {
        // Sin el rol "conductor" la asignación del rol falla: toda la
        // transacción se revierte (ni persona, ni conductor, ni usuario).
        Permission::firstOrCreate(['name' => 'usuarios.crear', 'guard_name' => 'web']);
        $this->admin->givePermissionTo('usuarios.crear');
        Exceptions::fake();

        $this->actingAs($this->admin)
            ->post(route('conductores.store'), $this->payloadConductorConCuenta());

        $this->assertDatabaseMissing('persona', ['ci' => '6660001']);
        $this->assertDatabaseMissing('users', ['email' => 'mario.flores@example.com']);
    }

    public function test_editar_un_conductor_no_crea_ni_toca_cuentas(): void
    {
        $conductor = Conductor::factory()->create();
        $this->adminConPermisoDeUsuarios();

        $this->actingAs($this->admin)->post(route('conductores.update', $conductor->id), [
            '_method' => 'PUT',
            'ci' => $conductor->persona->ci,
            'nombres' => $conductor->persona->nombres,
            'paterno' => $conductor->persona->paterno ?? 'Apellido',
            'estado_conductor' => 'ACTIVO',
            'crear_usuario' => '1',
            'email' => 'intento@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['email' => 'intento@example.com']);
    }

    public function test_store_crea_la_persona_y_el_conductor_asociado(): void
    {
        $response = $this->actingAs($this->admin)->post(route('conductores.store'), [
            'ci' => '12345678',
            'nombres' => 'Juan',
            'paterno' => 'Pérez',
            'celular' => '70000000',
            'estado_conductor' => 'ACTIVO',
        ]);

        $response->assertRedirect(route('conductores.index'));

        $persona = Persona::where('ci', '12345678')->firstOrFail();
        $this->assertSame('Juan', $persona->nombres);

        $this->assertDatabaseHas('conductor', [
            'id' => $persona->id,
            'estado_conductor' => 'ACTIVO',
        ]);
    }

    public function test_store_rechaza_un_ci_ya_registrado_en_persona(): void
    {
        Persona::factory()->create(['ci' => '12345678']);

        $response = $this->actingAs($this->admin)->post(route('conductores.store'), [
            'ci' => '12345678',
            'nombres' => 'Juan',
            'paterno' => 'Pérez',
            'estado_conductor' => 'ACTIVO',
        ]);

        $response->assertSessionHasErrors('ci');
    }

    public function test_store_permite_registrar_documentos_opcionales(): void
    {
        Storage::fake('public');

        $archivo = UploadedFile::fake()->create('licencia.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('conductores.store'), [
            'ci' => '12345678',
            'nombres' => 'Juan',
            'paterno' => 'Pérez',
            'estado_conductor' => 'ACTIVO',
            'documentos' => [
                [
                    'tipo_documento' => 'LICENCIA_DE_CONDUCIR',
                    'numero_documento' => 'LIC-001',
                    'categoria' => 'B-2',
                    'estado_documento' => 'VIGENTE',
                    'archivo' => $archivo,
                ],
            ],
        ]);

        $response->assertRedirect(route('conductores.index'));

        $persona = Persona::where('ci', '12345678')->firstOrFail();
        $documento = DocumentoConductor::where('id_conductor', $persona->id)->firstOrFail();

        $this->assertSame('LIC-001', $documento->numero_documento);
        Storage::disk('public')->assertExists($documento->archivo);
    }

    public function test_store_no_requiere_documentos(): void
    {
        $response = $this->actingAs($this->admin)->post(route('conductores.store'), [
            'ci' => '12345678',
            'nombres' => 'Juan',
            'paterno' => 'Pérez',
            'estado_conductor' => 'ACTIVO',
        ]);

        $response->assertRedirect(route('conductores.index'));
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_update_actualiza_los_datos_personales_y_el_estado_del_conductor(): void
    {
        $conductor = Conductor::factory()->create(['estado_conductor' => 'ACTIVO']);

        $response = $this->actingAs($this->admin)->put(route('conductores.update', $conductor->id), [
            'ci' => $conductor->persona->ci,
            'nombres' => 'Nombre Actualizado',
            'paterno' => 'Apellido Actualizado',
            'estado_conductor' => 'INACTIVO',
        ]);

        $response->assertRedirect(route('conductores.index'));

        $this->assertDatabaseHas('persona', [
            'id' => $conductor->id,
            'nombres' => 'Nombre Actualizado',
            'paterno' => 'Apellido Actualizado',
        ]);
        $this->assertDatabaseHas('conductor', [
            'id' => $conductor->id,
            'estado_conductor' => 'INACTIVO',
        ]);
    }

    public function test_update_crea_actualiza_y_elimina_documentos_segun_lo_enviado(): void
    {
        Storage::fake('public');

        $conductor = Conductor::factory()->create();
        $documentoAConservar = DocumentoConductor::factory()->create([
            'id_conductor' => $conductor->id,
            'tipo_documento' => 'CI',
            'numero_documento' => 'CI-001',
        ]);
        $documentoAEliminar = DocumentoConductor::factory()->create([
            'id_conductor' => $conductor->id,
            'tipo_documento' => 'OTRO',
        ]);

        $response = $this->actingAs($this->admin)->put(route('conductores.update', $conductor->id), [
            'ci' => $conductor->persona->ci,
            'nombres' => $conductor->persona->nombres,
            'paterno' => $conductor->persona->paterno,
            'estado_conductor' => $conductor->estado_conductor,
            'documentos' => [
                [
                    'id' => $documentoAConservar->id,
                    'tipo_documento' => 'CI',
                    'numero_documento' => 'CI-001-EDITADO',
                    'estado_documento' => 'VIGENTE',
                ],
                [
                    'tipo_documento' => 'CERTIFICADO_MEDICO',
                    'numero_documento' => 'CERT-NUEVO',
                    'estado_documento' => 'VIGENTE',
                ],
            ],
        ]);

        $response->assertRedirect(route('conductores.index'));

        $this->assertDatabaseHas('documento_conductor', [
            'id' => $documentoAConservar->id,
            'numero_documento' => 'CI-001-EDITADO',
        ]);
        $this->assertDatabaseHas('documento_conductor', [
            'id_conductor' => $conductor->id,
            'numero_documento' => 'CERT-NUEVO',
        ]);
        $this->assertDatabaseMissing('documento_conductor', [
            'id' => $documentoAEliminar->id,
        ]);
        $this->assertSame(2, DocumentoConductor::where('id_conductor', $conductor->id)->count());
    }

    public function test_update_rechaza_un_documento_con_id_de_otro_conductor(): void
    {
        $conductor = Conductor::factory()->create();
        $otroConductor = Conductor::factory()->create();
        $documentoAjeno = DocumentoConductor::factory()->create(['id_conductor' => $otroConductor->id]);

        $response = $this->actingAs($this->admin)->put(route('conductores.update', $conductor->id), [
            'ci' => $conductor->persona->ci,
            'nombres' => $conductor->persona->nombres,
            'paterno' => $conductor->persona->paterno,
            'estado_conductor' => $conductor->estado_conductor,
            'documentos' => [
                [
                    'id' => $documentoAjeno->id,
                    'tipo_documento' => 'OTRO',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('documentos.0.id');
    }

    public function test_destroy_elimina_el_conductor_sin_afectar_la_persona(): void
    {
        $conductor = Conductor::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('conductores.destroy', $conductor->id));

        $response->assertRedirect(route('conductores.index'));
        $this->assertSoftDeleted('conductor', ['id' => $conductor->id]);
        $this->assertDatabaseHas('persona', ['id' => $conductor->id]);
    }

    /* ------------------------------------------------------------------ *
     |  Formulario reutilizable (create + edit) y asignación de vehículo
     * ------------------------------------------------------------------ */

    public function test_create_renderiza_el_formulario_con_los_vehiculos_activos(): void
    {
        Vehiculo::factory()->create(['estado_vehiculo' => 'ACTIVO']);
        Vehiculo::factory()->create(['estado_vehiculo' => 'RETIRADO']);

        $response = $this->actingAs($this->admin)->get(route('conductores.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Conductores/Form')
            ->missing('conductor')
            ->has('vehiculos', 1)
        );
    }

    public function test_edit_renderiza_el_formulario_con_el_conductor_y_su_asignacion_actual(): void
    {
        $conductor = Conductor::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->admin)->get(route('conductores.edit', $conductor->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Conductores/Form')
            ->where('conductor.id', $conductor->id)
            ->where('asignacionActual.estado_asignacion', 'ACTIVO')
            ->where('asignacionActual.vehiculo.codigo', $vehiculo->codigo)
            // Al editar no se puede (re)asignar vehículo: no se envía el catálogo.
            ->missing('vehiculos')
        );
    }

    public function test_store_asigna_el_vehiculo_cuando_se_envia_id_vehiculo(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $response = $this->actingAs($this->admin)->post(route('conductores.store'), [
            'ci' => '12345678',
            'nombres' => 'Juan',
            'paterno' => 'Pérez',
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 1200,
            'detalle' => 'Asignación al registrar',
        ]);

        $response->assertRedirect(route('conductores.index'));

        $persona = Persona::where('ci', '12345678')->firstOrFail();
        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 1200,
            'id_usuario' => $this->admin->id,
        ]);
    }

    public function test_store_sin_id_vehiculo_no_crea_ninguna_asignacion(): void
    {
        $this->actingAs($this->admin)->post(route('conductores.store'), [
            'ci' => '12345678',
            'nombres' => 'Juan',
            'paterno' => 'Pérez',
            'estado_conductor' => 'ACTIVO',
        ])->assertRedirect(route('conductores.index'));

        $persona = Persona::where('ci', '12345678')->firstOrFail();
        $this->assertDatabaseCount('asignacion', 0);
        $this->assertDatabaseMissing('asignacion', ['id_conductor' => $persona->id]);
    }

    public function test_store_exige_la_lectura_inicial_del_vehiculo_al_asignarlo(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);

        $response = $this->actingAs($this->admin)->post(route('conductores.store'), [
            'ci' => '12345678',
            'nombres' => 'Juan',
            'paterno' => 'Pérez',
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
        ]);

        $response->assertSessionHasErrors('horometro_inicial');
        $this->assertDatabaseCount('conductor', 0);
    }

    public function test_update_ignora_por_completo_los_datos_de_asignacion_de_vehiculo(): void
    {
        $conductor = Conductor::factory()->create();
        $vehiculoActual = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $asignacion = Asignacion::create([
            'id_vehiculo' => $vehiculoActual->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 100,
        ]);
        $otroVehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        // Aunque el request traiga id_vehiculo, update() no toca la asignación.
        $response = $this->actingAs($this->admin)->put(route('conductores.update', $conductor->id), [
            'ci' => $conductor->persona->ci,
            'nombres' => 'Nombre Editado',
            'paterno' => $conductor->persona->paterno,
            'estado_conductor' => 'INACTIVO',
            'id_vehiculo' => $otroVehiculo->id,
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 999,
        ]);

        $response->assertRedirect(route('conductores.index'));
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('persona', ['id' => $conductor->id, 'nombres' => 'Nombre Editado']);
        $this->assertDatabaseCount('asignacion', 1);
        $this->assertSame('ACTIVO', $asignacion->fresh()->estado_asignacion);
        $this->assertSame($vehiculoActual->id, $asignacion->fresh()->id_vehiculo);
    }

    public function test_asignar_vehiculo_desde_el_formulario_esta_bloqueado_sin_rol_de_gestion(): void
    {
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        $usuario = User::factory()->create();
        $usuario->assignRole('conductor');

        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);

        $response = $this->actingAs($usuario)->post(route('conductores.store'), [
            'ci' => '12345678',
            'nombres' => 'Juan',
            'paterno' => 'Pérez',
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
            'kilometraje_inicial' => 100,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('conductor', 0);
        $this->assertDatabaseCount('asignacion', 0);
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

    private function licencia(Conductor $conductor, string $numero, string $categoria, $vencimiento): DocumentoConductor
    {
        return DocumentoConductor::create([
            'id_conductor' => $conductor->id,
            'tipo_documento' => 'LICENCIA_DE_CONDUCIR',
            'numero_documento' => $numero,
            'categoria' => $categoria,
            'fecha_vencimiento' => $vencimiento,
        ]);
    }

    public function test_exporta_el_listado_de_conductores_en_pdf(): void
    {
        Conductor::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('conductores.reporte.pdf'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('listado_conductores_', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /**
     * Cada fila trae la licencia MÁS RECIENTE (no la primera cargada) y los
     * vehículos vigentes, marcando los provisionales; un documento que no es
     * licencia (ej. CI) no debe confundirse con ella.
     */
    public function test_el_excel_trae_la_licencia_mas_reciente_y_los_vehiculos_asignados(): void
    {
        $persona = Persona::factory()->create(['ci' => '7654321', 'nombres' => 'Juan', 'paterno' => 'Pérez', 'materno' => 'Quispe', 'celular' => '71234567']);
        $conductor = Conductor::factory()->create(['id' => $persona->id]);

        $this->licencia($conductor, 'LIC-ANTIGUA', 'B', now()->subYear());
        $this->licencia($conductor, 'LIC-ACTUAL', 'C', now()->addYear());
        DocumentoConductor::create([
            'id_conductor' => $conductor->id,
            'tipo_documento' => 'CI',
            'numero_documento' => '7654321',
            'fecha_vencimiento' => now()->addYears(5),
        ]);

        $volqueta = Vehiculo::factory()->create(['codigo' => 'VOL-01', 'nro_placa' => '1111AAA']);
        $camioneta = Vehiculo::factory()->create(['codigo' => 'CAM-02', 'nro_placa' => '2222BBB']);
        Asignacion::create(['id_vehiculo' => $volqueta->id, 'id_conductor' => $conductor->id, 'fecha_asignacion' => now(), 'estado_asignacion' => 'ACTIVO']);
        Asignacion::create(['id_vehiculo' => $camioneta->id, 'id_conductor' => $conductor->id, 'fecha_asignacion' => now(), 'estado_asignacion' => 'PROVISIONAL']);

        $response = $this->actingAs($this->admin)->get(route('conductores.reporte.excel'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $fila = $this->celdasDelExcel($response->getContent())->first(fn ($f) => in_array('7654321', $f, true));

        $this->assertNotNull($fila, 'El Excel no contiene la fila del conductor.');
        $this->assertSame(['7654321', 'Juan', 'Pérez', 'Quispe', '71234567'], array_slice($fila, 1, 5));
        $this->assertSame('LIC-ACTUAL', $fila[6]);
        $this->assertSame('C', $fila[7]);
        $this->assertSame(now()->addYear()->format('d/m/Y'), $fila[8]);
        $this->assertSame('Vigente', $fila[9]);
        $this->assertStringContainsString('VOL-01 — 1111AAA', $fila[10]);
        $this->assertStringContainsString('CAM-02 — 2222BBB (Provisional)', $fila[10]);
        $this->assertSame('ACTIVO', $fila[11]);
    }

    public function test_el_excel_marca_la_licencia_vencida_y_los_datos_faltantes(): void
    {
        $conLicenciaVencida = Conductor::factory()->create();
        $this->licencia($conLicenciaVencida, 'LIC-VENCIDA', 'A', now()->subDay());
        $sinNada = Conductor::factory()->create();

        $celdas = $this->celdasDelExcel(
            $this->actingAs($this->admin)->get(route('conductores.reporte.excel'))->assertOk()->getContent()
        );

        $vencida = $celdas->first(fn ($f) => in_array('LIC-VENCIDA', $f, true));
        $this->assertSame('Vencida', $vencida[9]);

        $vacia = $celdas->first(fn ($f) => in_array($sinNada->persona->ci, $f, true));
        // Sin licencia ni vehículo: celdas en blanco, sin textos de relleno.
        $this->assertNull($vacia[6]);
        $this->assertNull($vacia[7]);
        $this->assertNull($vacia[8]);
        $this->assertNull($vacia[9]);
        $this->assertNull($vacia[10]);

        $this->actingAs($this->admin)->get(route('conductores.reporte.pdf'))->assertOk();
    }

    /**
     * Lo exportado coincide con el listado filtrado (todas sus páginas) y el
     * encabezado deja constancia del filtro; un listado sin resultados no
     * rompe ninguno de los dos formatos.
     */
    public function test_los_reportes_de_conductores_respetan_los_filtros(): void
    {
        Conductor::factory()->create(['id' => Persona::factory()->create(['ci' => '1112223'])->id]);
        Conductor::factory()->create(['id' => Persona::factory()->create(['ci' => '9998887'])->id]);

        $celdas = $this->celdasDelExcel(
            $this->actingAs($this->admin)->get(route('conductores.reporte.excel', ['ci' => '111']))->assertOk()->getContent()
        )->flatten();

        $this->assertTrue($celdas->contains('1112223'));
        $this->assertFalse($celdas->contains('9998887'), 'Un conductor fuera del filtro no debe exportarse.');
        $this->assertTrue($celdas->contains('Filtro aplicado: CI: 111'));

        $sinResultados = ['ci' => 'NO-EXISTE'];
        $this->actingAs($this->admin)->get(route('conductores.reporte.pdf', $sinResultados))->assertOk();
        $this->assertTrue(
            $this->celdasDelExcel($this->actingAs($this->admin)->get(route('conductores.reporte.excel', $sinResultados))->getContent())
                ->flatten()->contains('No hay conductores que coincidan con los filtros seleccionados.'),
        );
    }

    public function test_el_excel_de_conductores_cuenta_por_estado_y_sin_vehiculo(): void
    {
        $conVehiculo = Conductor::factory()->create(['estado_conductor' => 'ACTIVO']);
        Asignacion::create(['id_vehiculo' => Vehiculo::factory()->create()->id, 'id_conductor' => $conVehiculo->id, 'fecha_asignacion' => now(), 'estado_asignacion' => 'ACTIVO']);
        Conductor::factory()->create(['estado_conductor' => 'ACTIVO']);
        Conductor::factory()->create(['estado_conductor' => 'INACTIVO']);
        Conductor::factory()->create(['estado_conductor' => 'RETIRADO']);

        $celdas = $this->celdasDelExcel(
            $this->actingAs($this->admin)->get(route('conductores.reporte.excel'))->assertOk()->getContent()
        );

        $this->assertTrue($celdas->flatten()->contains('Sin filtros: todos los conductores registrados'));
        $this->assertSame([
            'TOTAL CONDUCTORES' => 4,
            'ACTIVOS' => 2,
            'INACTIVOS' => 1,
            'RETIRADOS' => 1,
            'SIN VEHÍCULO ASIGNADO' => 3,
        ], $this->tarjetasDelExcel($celdas, 'TOTAL CONDUCTORES'));
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

    public function test_el_pdf_de_conductores_deja_en_blanco_lo_que_no_tiene_registros_ligados(): void
    {
        $sinNada = Conductor::factory()->create();
        $sinNada->persona->update(['celular' => null]);

        $texto = $this->textoDelPdf(
            $this->actingAs($this->admin)->get(route('conductores.reporte.pdf'))->assertOk()->getContent()
        );

        $this->assertStringContainsString((string) $sinNada->persona->ci, $texto, 'No se pudo leer el texto del PDF.');
        foreach (['Sin licencia', "Sin veh\xEDculo asignado", "\x97"] as $relleno) {
            $this->assertStringNotContainsString($relleno, $texto);
        }
    }

    public function test_un_conductor_con_vehiculo_no_cuenta_como_sin_vehiculo_aunque_falte_el_codigo_y_la_placa(): void
    {
        $conductor = Conductor::factory()->create();
        $vehiculo = Vehiculo::factory()->create(['codigo' => 'TMP', 'nro_placa' => null]);
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);
        // Dato incompleto cargado antes de que el código fuera obligatorio.
        Vehiculo::whereKey($vehiculo->id)->update(['codigo' => '']);

        $celdas = $this->celdasDelExcel(
            $this->actingAs($this->admin)->get(route('conductores.reporte.excel'))->assertOk()->getContent()
        );

        $fila = $celdas->first(fn ($f) => in_array($conductor->persona->ci, $f, true));
        $this->assertNull($fila[10], 'Sin código ni placa la celda queda en blanco, sin comas sueltas.');

        $etiqueta = $celdas->search(fn ($f) => in_array('SIN VEHÍCULO ASIGNADO', $f, true));
        $columna = array_search('SIN VEHÍCULO ASIGNADO', $celdas[$etiqueta], true);
        $this->assertSame(0, (int) $celdas[$etiqueta + 1][$columna]);
    }

    public function test_si_falla_un_reporte_de_conductores_vuelve_al_listado_con_un_aviso(): void
    {
        Exceptions::fake();
        $conductor = Conductor::factory()->create();
        DB::statement('ALTER TABLE documento_conductor RENAME TO documento_conductor_no_disponible');

        $filtros = ['ci' => $conductor->persona->ci];

        $this->actingAs($this->admin)->get(route('conductores.reporte.pdf', $filtros))
            ->assertRedirect(route('conductores.index', $filtros))
            ->assertSessionHas('error');

        $this->actingAs($this->admin)->get(route('conductores.reporte.excel'))
            ->assertRedirect(route('conductores.index'))
            ->assertSessionHas('error', 'No se pudo generar el reporte en Excel. Intente nuevamente o contacte al administrador.');

        Exceptions::assertReported(QueryException::class);
    }

    public function test_los_reportes_de_conductores_requieren_iniciar_sesion(): void
    {
        $this->get(route('conductores.reporte.pdf'))->assertRedirect(route('login'));
        $this->get(route('conductores.reporte.excel'))->assertRedirect(route('login'));
    }
}

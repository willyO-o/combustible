<?php

namespace Tests\Feature;

use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\DocumentoConductor;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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
}

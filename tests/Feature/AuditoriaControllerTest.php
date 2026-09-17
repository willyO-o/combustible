<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\TipoCombustible;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditoriaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // owen-it/laravel-auditing no audita nada cuando la app corre en
        // consola (config/audit.php -> 'console'), y PHPUnit ES consola: sin
        // esto ninguna prueba generaría un solo registro en `audits`.
        config(['audit.console' => true]);

        Permission::firstOrCreate(['name' => 'auditoria.ver', 'guard_name' => 'web']);

        $rol = Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        $rol->givePermissionTo('auditoria.ver');

        $this->admin = User::factory()->create();
        $this->admin->assignRole($rol);
        $this->actingAs($this->admin);
    }

    public function test_los_modelos_registran_sus_cambios_en_la_bitacora(): void
    {
        $material = Material::factory()->create(['material' => 'Arena']);
        $material->update(['material' => 'Arena fina']);
        $material->delete();

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Material::class,
            'auditable_id' => $material->id,
            'event' => 'created',
            'user_id' => $this->admin->id,
        ]);

        $modificacion = Audit::where('auditable_type', Material::class)
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertSame(['material' => 'Arena'], $modificacion->old_values);
        $this->assertSame(['material' => 'Arena fina'], $modificacion->new_values);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Material::class,
            'event' => 'deleted',
        ]);
    }

    public function test_la_contrasena_nunca_queda_registrada_en_la_bitacora(): void
    {
        $usuario = User::factory()->create();
        $usuario->update(['password' => 'un-secreto-nuevo', 'name' => 'Nombre Cambiado']);

        $auditoria = Audit::where('auditable_type', User::class)
            ->where('auditable_id', $usuario->id)
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertArrayNotHasKey('password', $auditoria->new_values);
        $this->assertArrayNotHasKey('password', $auditoria->old_values);
        $this->assertSame('Nombre Cambiado', $auditoria->new_values['name']);
    }

    public function test_index_lista_los_movimientos_con_sus_etiquetas_en_espanol(): void
    {
        $material = Material::factory()->create(['material' => 'Grava']);
        $material->update(['material' => 'Grava lavada']);

        $response = $this->get(route('auditoria.index', ['auditable_type' => Material::class, 'event' => 'updated']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Auditoria/Index')
            ->where('auditorias.data.0.evento.label', 'Modificación')
            ->where('auditorias.data.0.modelo.label', 'Materiales')
            ->where('auditorias.data.0.registro.descriptor', 'Grava lavada')
            ->where('auditorias.data.0.usuario.nombre', $this->admin->name)
            ->where('auditorias.data.0.campos', ['Material'])
        );
    }

    public function test_index_filtra_por_modulo_evento_y_usuario(): void
    {
        Material::factory()->create(['material' => 'Arena']);
        Vehiculo::factory()->create();

        $response = $this->get(route('auditoria.index', ['auditable_type' => Material::class]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('auditorias.total', 1)
            ->where('auditorias.data.0.modelo.label', 'Materiales')
        );

        $otroUsuario = User::factory()->create();

        $this->get(route('auditoria.index', ['user_id' => $otroUsuario->id]))
            ->assertInertia(fn (Assert $page) => $page->where('auditorias.total', 0));
    }

    public function test_index_respeta_el_rango_de_fechas_y_por_defecto_muestra_el_mes_actual(): void
    {
        Material::factory()->create(['material' => 'Cemento']);

        Audit::query()->update(['created_at' => now()->subMonths(2)]);

        $this->get(route('auditoria.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auditorias.total', 0)
                ->where('filters.fecha_desde', now()->startOfMonth()->format('Y-m-d'))
                ->where('filters.fecha_hasta', now()->format('Y-m-d'))
            );

        $this->get(route('auditoria.index', [
            'fecha_desde' => now()->subMonths(3)->format('Y-m-d'),
            'auditable_type' => Material::class,
        ]))->assertInertia(fn (Assert $page) => $page->where('auditorias.total', 1));
    }

    public function test_index_busca_por_texto_dentro_de_los_valores_auditados(): void
    {
        Material::factory()->create(['material' => 'Ripio']);
        Material::factory()->create(['material' => 'Arena']);

        $this->get(route('auditoria.index', ['q' => 'Ripio']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auditorias.total', 1)
                ->where('auditorias.data.0.registro.descriptor', 'Ripio')
            );
    }

    public function test_detalle_devuelve_el_antes_y_despues_con_las_llaves_foraneas_resueltas(): void
    {
        $diesel = TipoCombustible::create(['tipo_combustible' => 'DIESEL', 'estado_tipo_combustible' => 'ACTIVO']);
        $gasolina = TipoCombustible::create(['tipo_combustible' => 'GASOLINA', 'estado_tipo_combustible' => 'ACTIVO']);

        $vehiculo = Vehiculo::factory()->create(['id_tipo_combustible' => $diesel->id]);
        $vehiculo->update(['id_tipo_combustible' => $gasolina->id]);

        $auditoria = Audit::where('auditable_type', Vehiculo::class)
            ->where('event', 'updated')
            ->firstOrFail();

        $response = $this->getJson(route('auditoria.detalle', $auditoria->id));

        $response->assertOk()
            ->assertJsonPath('evento.label', 'Modificación')
            ->assertJsonPath('modelo.label', 'Vehículos')
            ->assertJsonPath('registro.descriptor', "{$vehiculo->codigo} · {$vehiculo->nro_placa}")
            ->assertJsonPath('registro.existe', true)
            ->assertJsonPath('cambios.0.campo', 'id_tipo_combustible')
            ->assertJsonPath('cambios.0.label', 'Tipo de combustible')
            ->assertJsonPath('cambios.0.antes', "DIESEL (#{$diesel->id})")
            ->assertJsonPath('cambios.0.despues', "GASOLINA (#{$gasolina->id})")
            ->assertJsonPath('cambios.0.modificado', true)
            ->assertJsonPath('usuario.nombre', $this->admin->name);

        // El historial del mismo registro acompaña al detalle: creación + edición.
        $response->assertJsonCount(2, 'historial')
            ->assertJsonPath('historial.0.es_actual', true);
    }

    public function test_detalle_avisa_cuando_el_registro_auditado_ya_no_existe(): void
    {
        $material = Material::factory()->create(['material' => 'Piedra']);
        $id = $material->id;
        $material->forceDelete();

        $auditoria = Audit::where('auditable_type', Material::class)
            ->where('auditable_id', $id)
            ->firstOrFail();

        $this->getJson(route('auditoria.detalle', $auditoria->id))
            ->assertOk()
            ->assertJsonPath('registro.existe', false);
    }

    public function test_un_usuario_sin_el_permiso_no_puede_entrar_a_la_auditoria(): void
    {
        $conductor = User::factory()->create();
        $conductor->assignRole(Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']));

        $this->actingAs($conductor)
            ->get(route('auditoria.index'))
            ->assertForbidden();
    }
}

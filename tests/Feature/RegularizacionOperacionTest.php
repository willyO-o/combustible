<?php

namespace Tests\Feature;

use App\Http\Middleware\SoloEncargadoDeRegularizacion;
use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\HabilitacionRegularizacion;
use App\Models\OperacionDiaria;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use App\Notifications\NotificacionFormatter;
use App\Notifications\RegularizacionHabilitadaNotification;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\ConPermisosReales;
use Tests\TestCase;

/**
 * Modo transición/estricto de la operación diaria y habilitaciones de
 * regularización que otorga el encargado del .env.
 */
class RegularizacionOperacionTest extends TestCase
{
    use ConPermisosReales;
    use RefreshDatabase;

    private const CORREO_ENCARGADO = 'encargado@empresa.com';

    private User $encargado;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['operacion_diaria.regularizacion.encargados' => [self::CORREO_ENCARGADO]]);

        // OperacionDiaria::getNroAttribute() lee digitos_serie; aquí también
        // se guarda el modo estricto.
        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 1],
            'estado' => 'ACTIVO',
        ]);

        $this->encargado = User::factory()->create(['email' => self::CORREO_ENCARGADO]);
        $this->area = Area::factory()->create();
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers */
    /* ------------------------------------------------------------------ */

    private function activarModoEstricto(?CarbonInterface $desde = null): void
    {
        ParametrosEmpresa::query()->update(['regularizacion_estricta_desde' => ($desde ?? today())->toDateString()]);
    }

    private function usuarioConRol(string $rol): User
    {
        $usuario = User::factory()->create(['id_persona' => Persona::factory()->create()->id]);
        $usuario->assignRole($rol);

        return $usuario;
    }

    private function vehiculoConArea(): Vehiculo
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'kilometraje']);
        $this->area->vehiculos()->attach($vehiculo->id, [
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        return $vehiculo;
    }

    /**
     * @return array{0: User, 1: Vehiculo}
     */
    private function conductorConVehiculo(): array
    {
        $persona = Persona::factory()->create();
        $conductor = Conductor::factory()->create(['id' => $persona->id]);
        $usuario = User::factory()->create(['id_persona' => $persona->id]);
        $usuario->assignRole('conductor');

        $vehiculo = $this->vehiculoConArea();
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        return [$usuario, $vehiculo];
    }

    /**
     * Jornada del día indicado: DIA 08:00–18:00 o NOCHE 19:00–05:00.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Vehiculo $vehiculo, CarbonInterface $dia, string $turno = 'DIA', array $extra = []): array
    {
        [$inicio, $fin] = $turno === 'NOCHE'
            ? [$dia->copy()->setTime(19, 0), $dia->copy()->addDay()->setTime(5, 0)]
            : [$dia->copy()->setTime(8, 0), $dia->copy()->setTime(18, 0)];

        return array_merge([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => Conductor::factory()->create()->id,
            'turno' => $turno,
            'fecha_inicio' => $inicio->format('Y-m-d\TH:i'),
            'fecha_fin' => $fin->format('Y-m-d\TH:i'),
            'kilometraje_inicio' => 1000,
            'kilometraje_fin' => 1050,
            'notificar_observaciones' => false,
            'actividades_realizadas' => [[
                'actividad' => 'Transporte de material',
                'origen' => 'Cantera',
                'destino' => 'Planta',
            ]],
        ], $extra);
    }

    private function registrar(User $usuario, Vehiculo $vehiculo, CarbonInterface $dia, string $turno = 'DIA', array $extra = [])
    {
        return $this->actingAs($usuario)->post(route('operacion-diaria.store'), $this->payload($vehiculo, $dia, $turno, $extra));
    }

    private function habilitar(User $usuario, CarbonInterface $desde, CarbonInterface $hasta, array $extra = []): HabilitacionRegularizacion
    {
        return HabilitacionRegularizacion::factory()->create(array_merge([
            'id_usuario' => $usuario->id,
            'fecha_desde' => $desde->toDateString(),
            'fecha_hasta' => $hasta->toDateString(),
            'id_usuario_otorga' => $this->encargado->id,
        ], $extra));
    }

    /* ------------------------------------------------------------------ */
    /*  Acceso del encargado */
    /* ------------------------------------------------------------------ */

    public function test_el_encargado_entra_a_la_pantalla_de_regularizacion(): void
    {
        $jefe = $this->usuarioConRol('jefe-area');
        $this->habilitar($jefe, now()->subDays(3), now()->subDays(2));
        $this->habilitar($jefe, now()->subDays(5), now()->subDays(5), ['revocada_at' => now()->subHour()]);

        $this->actingAs($this->encargado)->get(route('operacion-diaria.regularizacion.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operacion/Regularizacion')
                ->where('modo.estricto', false)
                ->where('modo.estricto_desde', null)
                ->where('modo.hay_parametros', true)
                ->has('vigentes', 1)
                ->where('vigentes.0.usuario', $jefe->nombreVisible())
                ->has('historial', 1)
                ->where('historial.0.estado', 'REVOCADA')
                ->where('usuarios', fn ($usuarios) => collect($usuarios)->contains('id', $jefe->id))
            );
    }

    public function test_solo_el_correo_del_env_entra_ni_siquiera_el_super_admin(): void
    {
        foreach (['super-admin', 'administrador', 'jefe-area'] as $rol) {
            $usuario = $this->usuarioConRol($rol);

            $this->actingAs($usuario)->get(route('operacion-diaria.regularizacion.index'))->assertForbidden();
            $this->actingAs($usuario)->put(route('operacion-diaria.regularizacion.modo'), ['accion' => 'activar'])->assertForbidden();
            $this->actingAs($usuario)->post(route('operacion-diaria.regularizacion.habilitaciones.store'), [])->assertForbidden();
        }

        $this->assertNull(ParametrosEmpresa::regularizacionEstrictaDesde());
    }

    public function test_sin_correos_en_el_env_nadie_entra(): void
    {
        config(['operacion_diaria.regularizacion.encargados' => []]);

        $this->actingAs($this->encargado)->get(route('operacion-diaria.regularizacion.index'))->assertForbidden();
    }

    public function test_acepta_varios_encargados_sin_distinguir_mayusculas(): void
    {
        config(['operacion_diaria.regularizacion.encargados' => ['otro@empresa.com', self::CORREO_ENCARGADO]]);
        $segundo = User::factory()->create(['email' => 'Otro@Empresa.com']);

        $this->assertTrue($segundo->esEncargadoDeRegularizacion());
        $this->assertTrue($this->encargado->esEncargadoDeRegularizacion());
        $this->actingAs($segundo)->get(route('operacion-diaria.regularizacion.index'))->assertOk();
    }

    public function test_el_menu_de_regularizacion_solo_se_comparte_al_encargado(): void
    {
        $permiso = SoloEncargadoDeRegularizacion::PERMISO_INTERFAZ;

        $this->actingAs($this->encargado)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.permissions', fn ($permisos) => collect($permisos)->contains($permiso)));

        $this->actingAs($this->usuarioConRol('super-admin'))->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.permissions', fn ($permisos) => ! collect($permisos)->contains($permiso)));
    }

    /* ------------------------------------------------------------------ */
    /*  Modo transición / estricto */
    /* ------------------------------------------------------------------ */

    public function test_el_encargado_activa_programa_y_desactiva_el_modo_estricto(): void
    {
        $this->actingAs($this->encargado)->put(route('operacion-diaria.regularizacion.modo'), ['accion' => 'activar'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(ParametrosEmpresa::regularizacionEstrictaDesde()->isSameDay(today()));
        $this->assertTrue(ParametrosEmpresa::regularizacionEstrictaVigente());

        $diciembre = today()->addMonth()->startOfMonth();
        $this->actingAs($this->encargado)->put(route('operacion-diaria.regularizacion.modo'), [
            'accion' => 'programar',
            'fecha' => $diciembre->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertTrue(ParametrosEmpresa::regularizacionEstrictaDesde()->isSameDay($diciembre));
        $this->assertFalse(ParametrosEmpresa::regularizacionEstrictaVigente());

        $this->actingAs($this->encargado)->put(route('operacion-diaria.regularizacion.modo'), ['accion' => 'desactivar'])
            ->assertSessionHasNoErrors();
        $this->assertNull(ParametrosEmpresa::regularizacionEstrictaDesde());
    }

    public function test_programar_exige_una_fecha_futura(): void
    {
        foreach ([null, today()->toDateString(), today()->subDay()->toDateString()] as $fecha) {
            $this->actingAs($this->encargado)->put(route('operacion-diaria.regularizacion.modo'), [
                'accion' => 'programar',
                'fecha' => $fecha,
            ])->assertSessionHasErrors('fecha');
        }

        $this->assertNull(ParametrosEmpresa::regularizacionEstrictaDesde());
    }

    public function test_sin_parametros_de_la_empresa_no_cambia_el_modo(): void
    {
        ParametrosEmpresa::query()->delete();

        $this->actingAs($this->encargado)->put(route('operacion-diaria.regularizacion.modo'), ['accion' => 'activar'])
            ->assertSessionHas('error');
    }

    public function test_en_transicion_el_jefe_sigue_regularizando_cualquier_fecha(): void
    {
        $jefe = $this->usuarioConRol('jefe-area');
        // Programado para el futuro: todavía rige la transición.
        $this->activarModoEstricto(today()->addWeek());

        $this->registrar($jefe, $this->vehiculoConArea(), now()->subDays(10))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operacion-diaria.index'));

        $this->assertDatabaseCount('operacion_diaria', 1);
    }

    public function test_en_modo_estricto_todos_registran_solo_hoy_o_la_noche_de_ayer(): void
    {
        $this->activarModoEstricto();

        foreach (['jefe-area', 'administrador', 'super-admin'] as $rol) {
            $usuario = $this->usuarioConRol($rol);
            $vehiculo = $this->vehiculoConArea();

            $this->registrar($usuario, $vehiculo, now())->assertSessionHasNoErrors();
            $this->registrar($usuario, $vehiculo, now()->subDay(), 'NOCHE')->assertSessionHasNoErrors();

            $this->registrar($usuario, $vehiculo, now()->subDay(), 'DIA')
                ->assertSessionHasErrors(['fecha_inicio' => 'De ayer sólo se puede registrar el turno noche. Para regularizar el turno día del '.now()->subDay()->format('d/m/Y').', solicita una habilitación al encargado de regularización.']);
            $this->registrar($usuario, $vehiculo, now()->subDays(2))
                ->assertSessionHasErrors(['fecha_inicio' => 'Sólo puedes registrar operaciones de hoy (o el turno noche de ayer). Para regularizar el '.now()->subDays(2)->format('d/m/Y').', solicita una habilitación al encargado de regularización.']);
            $this->registrar($usuario, $vehiculo, now()->addDay())
                ->assertSessionHasErrors(['fecha_inicio' => 'La fecha de inicio no puede ser posterior a hoy.']);
        }

        $this->assertDatabaseCount('operacion_diaria', 6);
    }

    public function test_en_modo_estricto_el_conductor_pierde_el_turno_dia_de_ayer(): void
    {
        [$conductor, $vehiculo] = $this->conductorConVehiculo();

        // En transición el conductor sí puede registrar ayer (cualquier turno).
        $this->registrar($conductor, $vehiculo, now()->subDay(), 'DIA', ['id_conductor' => null])->assertSessionHasNoErrors();
        OperacionDiaria::query()->delete();

        $this->activarModoEstricto();

        $this->registrar($conductor, $vehiculo, now()->subDay(), 'DIA', ['id_conductor' => null])->assertSessionHasErrors('fecha_inicio');
        $this->registrar($conductor, $vehiculo, now()->subDay(), 'NOCHE', ['id_conductor' => null])->assertSessionHasNoErrors();
        $this->registrar($conductor, $vehiculo, now(), 'DIA', ['id_conductor' => null])->assertSessionHasNoErrors();
    }

    public function test_la_fecha_programada_activa_sola_el_modo_estricto(): void
    {
        $jefe = $this->usuarioConRol('jefe-area');
        $this->activarModoEstricto(today()->addDays(3));
        $vehiculo = $this->vehiculoConArea();

        $this->registrar($jefe, $vehiculo, now()->subDays(5))->assertSessionHasNoErrors();

        $this->travel(3)->days();

        $this->registrar($jefe, $vehiculo, now()->subDays(5))->assertSessionHasErrors('fecha_inicio');
    }

    public function test_en_modo_estricto_editar_sin_cambiar_el_dia_sigue_permitido(): void
    {
        $jefe = $this->usuarioConRol('jefe-area');
        $vehiculo = $this->vehiculoConArea();
        $this->registrar($jefe, $vehiculo, now()->subDays(6))->assertSessionHasNoErrors();
        $operacion = OperacionDiaria::firstOrFail();

        $this->activarModoEstricto();

        $this->actingAs($jefe)->put(route('operacion-diaria.update', $operacion), $this->payload($vehiculo, now()->subDays(6), 'DIA', [
            'id_conductor' => $operacion->id_conductor,
            'observaciones' => 'Corrige el kilometraje',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Corrige el kilometraje', $operacion->fresh()->observaciones);

        // Moverla a otro día pasado ya necesita habilitación.
        $this->actingAs($jefe)->put(route('operacion-diaria.update', $operacion), $this->payload($vehiculo, now()->subDays(4), 'DIA', [
            'id_conductor' => $operacion->id_conductor,
        ]))->assertSessionHasErrors('fecha_inicio');
    }

    /* ------------------------------------------------------------------ */
    /*  Habilitaciones */
    /* ------------------------------------------------------------------ */

    public function test_el_encargado_habilita_a_un_usuario_y_este_recibe_el_aviso(): void
    {
        $jefe = $this->usuarioConRol('jefe-area');

        $this->actingAs($this->encargado)->post(route('operacion-diaria.regularizacion.habilitaciones.store'), [
            'id_usuario' => $jefe->id,
            'fecha_desde' => now()->subDays(4)->toDateString(),
            'fecha_hasta' => now()->subDays(2)->toDateString(),
            'vigencia' => '48h',
            'motivo' => 'El operario olvidó registrar sus jornadas',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $habilitacion = HabilitacionRegularizacion::firstOrFail();
        $this->assertSame($jefe->id, $habilitacion->id_usuario);
        $this->assertSame($this->encargado->id, $habilitacion->id_usuario_otorga);
        $this->assertSame('VIGENTE', $habilitacion->estado);
        $this->assertEqualsWithDelta(now()->addHours(48)->timestamp, $habilitacion->vigente_hasta->timestamp, 5);

        Notification::assertSentTo($jefe, RegularizacionHabilitadaNotification::class, function ($notificacion) use ($jefe, $habilitacion) {
            $data = $notificacion->toArray($jefe);
            $texto = NotificacionFormatter::formatear($data);

            return $notificacion->habilitacion->is($habilitacion)
                && $data['tipo'] === 'regularizacion_habilitada'
                && $data['url'] === route('operacion-diaria.create')
                && $texto['titulo'] === 'Regularización habilitada'
                && str_contains($texto['descripcion'], now()->subDays(4)->format('d/m/Y').' al '.now()->subDays(2)->format('d/m/Y'));
        });
    }

    public function test_las_vigencias_rapidas_y_la_personalizada(): void
    {
        $jefe = $this->usuarioConRol('jefe-area');
        $datos = [
            'id_usuario' => $jefe->id,
            'fecha_desde' => now()->subDays(3)->toDateString(),
            'fecha_hasta' => now()->subDays(3)->toDateString(),
            'motivo' => 'Regularización por falta de señal',
        ];

        $this->actingAs($this->encargado)->post(route('operacion-diaria.regularizacion.habilitaciones.store'), $datos + ['vigencia' => 'fin_del_dia'])
            ->assertSessionHasNoErrors();
        $this->assertEquals(now()->endOfDay()->timestamp, HabilitacionRegularizacion::latest('id')->first()->vigente_hasta->timestamp);

        $hasta = now()->addDays(5)->setTime(18, 0);
        $this->actingAs($this->encargado)->post(route('operacion-diaria.regularizacion.habilitaciones.store'), $datos + [
            'vigencia' => 'personalizada',
            'vigente_hasta' => $hasta->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
        $this->assertEquals($hasta->timestamp, HabilitacionRegularizacion::latest('id')->first()->vigente_hasta->timestamp);

        $this->actingAs($this->encargado)->post(route('operacion-diaria.regularizacion.habilitaciones.store'), $datos + [
            'vigencia' => 'personalizada',
            'vigente_hasta' => now()->addDays(31)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('vigente_hasta');

        $this->actingAs($this->encargado)->post(route('operacion-diaria.regularizacion.habilitaciones.store'), $datos + ['vigencia' => 'personalizada'])
            ->assertSessionHasErrors('vigente_hasta');
    }

    public function test_valida_los_datos_de_la_habilitacion(): void
    {
        $jefe = $this->usuarioConRol('jefe-area');
        $base = [
            'id_usuario' => $jefe->id,
            'fecha_desde' => now()->subDays(3)->toDateString(),
            'fecha_hasta' => now()->subDays(2)->toDateString(),
            'vigencia' => '24h',
            'motivo' => 'Regularización por falta de señal',
        ];
        $ruta = route('operacion-diaria.regularizacion.habilitaciones.store');

        // Hoy no se habilita: ya se puede registrar.
        $this->actingAs($this->encargado)->post($ruta, ['fecha_hasta' => today()->toDateString()] + $base)->assertSessionHasErrors('fecha_hasta');
        $this->actingAs($this->encargado)->post($ruta, ['fecha_desde' => now()->subDay()->toDateString()] + $base)->assertSessionHasErrors('fecha_hasta');
        $this->actingAs($this->encargado)->post($ruta, ['motivo' => 'corto'] + $base)->assertSessionHasErrors('motivo');
        $this->actingAs($this->encargado)->post($ruta, ['vigencia' => '1 semana'] + $base)->assertSessionHasErrors('vigencia');

        // Usuario que no registra operaciones, o inactivo.
        $sinPermiso = User::factory()->create();
        $this->actingAs($this->encargado)->post($ruta, ['id_usuario' => $sinPermiso->id] + $base)->assertSessionHasErrors('id_usuario');
        $jefe->update(['estado_usuario' => 'INACTIVO']);
        $this->actingAs($this->encargado)->post($ruta, $base)->assertSessionHasErrors('id_usuario');

        $this->assertDatabaseCount('habilitacion_regularizacion', 0);
        Notification::assertNothingSent();
    }

    public function test_con_habilitacion_vigente_registra_solo_los_dias_habilitados(): void
    {
        $this->activarModoEstricto();
        $jefe = $this->usuarioConRol('jefe-area');
        $vehiculo = $this->vehiculoConArea();
        $this->habilitar($jefe, now()->subDays(4), now()->subDays(2));

        $this->registrar($jefe, $vehiculo, now()->subDays(4))->assertSessionHasNoErrors();
        $this->registrar($jefe, $vehiculo, now()->subDays(2), 'NOCHE')->assertSessionHasNoErrors();
        // Ayer turno día no está habilitado.
        $this->registrar($jefe, $vehiculo, now()->subDay())->assertSessionHasErrors('fecha_inicio');
        $this->registrar($jefe, $vehiculo, now()->subDays(5))->assertSessionHasErrors('fecha_inicio');

        $this->assertDatabaseCount('operacion_diaria', 2);
    }

    public function test_una_habilitacion_vencida_revocada_o_ajena_no_sirve(): void
    {
        $this->activarModoEstricto();
        $jefe = $this->usuarioConRol('jefe-area');
        $otro = $this->usuarioConRol('jefe-area');
        $vehiculo = $this->vehiculoConArea();

        $this->habilitar($jefe, now()->subDays(4), now()->subDays(4), ['vigente_hasta' => now()->subMinute()]);
        $this->habilitar($jefe, now()->subDays(4), now()->subDays(4), ['revocada_at' => now()->subMinute()]);
        $this->habilitar($otro, now()->subDays(4), now()->subDays(4));

        $this->registrar($jefe, $vehiculo, now()->subDays(4))->assertSessionHasErrors('fecha_inicio');
        $this->registrar($otro, $vehiculo, now()->subDays(4))->assertSessionHasNoErrors();
    }

    public function test_la_habilitacion_tambien_vale_para_un_conductor_en_transicion(): void
    {
        [$conductor, $vehiculo] = $this->conductorConVehiculo();
        $this->habilitar($conductor, now()->subDays(6), now()->subDays(6));

        $this->registrar($conductor, $vehiculo, now()->subDays(6), 'DIA', ['id_conductor' => null])->assertSessionHasNoErrors();
        $this->registrar($conductor, $vehiculo, now()->subDays(7), 'DIA', ['id_conductor' => null])->assertSessionHasErrors('fecha_inicio');
    }

    public function test_el_encargado_revoca_una_habilitacion_vigente(): void
    {
        $this->activarModoEstricto();
        $jefe = $this->usuarioConRol('jefe-area');
        $habilitacion = $this->habilitar($jefe, now()->subDays(3), now()->subDays(3));

        $this->actingAs($jefe)->patch(route('operacion-diaria.regularizacion.habilitaciones.revocar', $habilitacion))->assertForbidden();

        $this->actingAs($this->encargado)->patch(route('operacion-diaria.regularizacion.habilitaciones.revocar', $habilitacion))
            ->assertSessionHas('success');

        $habilitacion->refresh();
        $this->assertSame('REVOCADA', $habilitacion->estado);
        $this->assertSame($this->encargado->id, $habilitacion->id_usuario_revoca);
        $this->registrar($jefe, $this->vehiculoConArea(), now()->subDays(3))->assertSessionHasErrors('fecha_inicio');

        // Una ya revocada o vencida no se vuelve a revocar.
        $this->actingAs($this->encargado)->patch(route('operacion-diaria.regularizacion.habilitaciones.revocar', $habilitacion))
            ->assertSessionHas('error');
    }

    public function test_el_formulario_recibe_la_regla_con_las_habilitaciones_vigentes(): void
    {
        $this->activarModoEstricto();
        $jefe = $this->usuarioConRol('jefe-area');
        $this->habilitar($jefe, now()->subDays(4), now()->subDays(2));

        $this->actingAs($jefe)->get(route('operacion-diaria.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reglaFecha.modo', 'estricto')
                ->where('reglaFecha.libre', false)
                ->has('reglaFecha.habilitaciones', 1)
                ->where('reglaFecha.habilitaciones.0.desde', now()->subDays(4)->toDateString())
                ->where('reglaFecha.habilitaciones.0.hasta', now()->subDays(2)->toDateString())
                ->where('reglaFecha.habilitaciones.0.otorgada_por', $this->encargado->nombreVisible())
            );
    }

    /* ------------------------------------------------------------------ */
    /*  API (app Flutter) */
    /* ------------------------------------------------------------------ */

    public function test_la_api_aplica_la_misma_regla_y_publica_la_ventana(): void
    {
        $this->activarModoEstricto();
        $jefe = $this->usuarioConRol('jefe-area');
        $vehiculo = $this->vehiculoConArea();

        $this->actingAs($jefe, 'api')
            ->postJson(route('api.v1.operacion-diaria.store'), $this->payload($vehiculo, now()->subDays(3)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_inicio');

        $this->habilitar($jefe, now()->subDays(3), now()->subDays(3));

        $this->actingAs($jefe, 'api')
            ->postJson(route('api.v1.operacion-diaria.store'), $this->payload($vehiculo, now()->subDays(3)))
            ->assertCreated();

        $this->actingAs($jefe, 'api')
            ->getJson(route('api.v1.parametros.colecciones', ['incluir' => 'operaciones_diarias']))
            ->assertOk()
            ->assertJsonPath('data.operaciones_diarias.regla_fecha.modo', 'estricto')
            ->assertJsonPath('data.operaciones_diarias.regla_fecha.habilitaciones.0.desde', now()->subDays(3)->toDateString());
    }
}

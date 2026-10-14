<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\DocumentoConductor;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $persona = Persona::factory()->create();
        $user = User::factory()->create(['id_persona' => $persona->id]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'celular' => '77712345',
                'direccion' => 'Av. Siempre Viva 123',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('77712345', $persona->fresh()->celular);
        $this->assertSame('Av. Siempre Viva 123', $persona->fresh()->direccion);
    }

    public function test_el_usuario_puede_cambiar_su_correo_confirmando_su_contrasena(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => '  nuevo@example.com ',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile')
            ->assertSessionHas('success', 'Perfil actualizado. Desde ahora inicia sesión con nuevo@example.com.');

        $this->assertSame('nuevo@example.com', $user->fresh()->email);
    }

    public function test_despues_de_cambiar_el_correo_inicia_sesion_con_el_nuevo(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'nuevo@example.com',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        auth()->logout();

        $this->post('/login', ['email' => 'original@example.com', 'password' => 'password']);
        $this->assertGuest();

        $this->post('/login', ['email' => 'nuevo@example.com', 'password' => 'password']);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_cambiar_el_correo_exige_la_contrasena_actual(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'nuevo@example.com',
            ])
            ->assertSessionHasErrors(['current_password' => 'Ingresa tu contraseña actual para cambiar el correo electrónico.'])
            ->assertRedirect('/profile');

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_cambiar_el_correo_rechaza_una_contrasena_incorrecta(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com', 'name' => 'Nombre Original']);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Nombre Nuevo',
                'email' => 'nuevo@example.com',
                'current_password' => 'incorrecta',
            ])
            ->assertSessionHasErrors(['current_password' => 'La contraseña actual no es correcta.']);

        // Nada del formulario se guarda si la validación falla.
        $this->assertSame('original@example.com', $user->fresh()->email);
        $this->assertSame('Nombre Original', $user->fresh()->name);
    }

    public function test_no_se_puede_usar_el_correo_de_otro_usuario(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'ocupado@example.com',
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors(['email' => 'Este correo electrónico ya está registrado por otro usuario.']);

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_rechaza_un_correo_con_formato_invalido_o_vacio(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        foreach (['no-es-un-correo', ''] as $email) {
            $this->actingAs($user)
                ->patch('/profile', [
                    'name' => $user->name,
                    'email' => $email,
                    'current_password' => 'password',
                ])
                ->assertSessionHasErrors('email');
        }

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    public function test_guardar_el_perfil_con_el_mismo_correo_no_pide_contrasena(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        // Mismo correo, aunque cambien mayúsculas: no es un cambio de cuenta.
        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Otro Nombre',
                'email' => 'Original@Example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Perfil actualizado exitosamente.');

        $this->assertSame('Otro Nombre', $user->fresh()->name);
    }

    public function test_la_contrasena_nunca_se_guarda_ni_cambia_al_editar_el_perfil(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);
        $hashAnterior = $user->password;

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'nuevo@example.com',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertSame($hashAnterior, $user->fresh()->password);
    }

    public function test_el_celular_y_la_direccion_no_se_actualizan_si_el_usuario_no_tiene_persona_vinculada(): void
    {
        $user = User::factory()->create(['id_persona' => null]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'celular' => '77712345',
                'direccion' => 'Av. Siempre Viva 123',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->persona);
    }

    public function test_profile_photo_can_be_uploaded_and_replaces_the_previous_one(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['foto' => 'usuarios/anterior.jpg']);
        Storage::disk('public')->put('usuarios/anterior.jpg', 'contenido');

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'foto' => UploadedFile::fake()->image('foto.jpg'),
            ]);

        $response->assertSessionHasNoErrors();

        $user->refresh();
        Storage::disk('public')->assertMissing('usuarios/anterior.jpg');
        Storage::disk('public')->assertExists($user->foto);
    }

    public function test_no_existe_ruta_de_eliminar_cuenta_desde_el_perfil(): void
    {
        $this->assertFalse(Route::has('profile.destroy'));

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', ['password' => 'password']);

        // /profile existe (GET, PATCH), pero ya no acepta DELETE: 405, no 404.
        $response->assertMethodNotAllowed();
        $this->assertNotNull($user->fresh());
    }

    public function test_el_perfil_muestra_los_datos_de_la_persona_y_sus_roles(): void
    {
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);

        $persona = Persona::factory()->create([
            'ci' => '6543210',
            'nombres' => 'Marta',
            'paterno' => 'Choque',
            'materno' => 'Lima',
            'fecha_nacimiento' => '1990-05-20',
            'estado_persona' => 'ACTIVO',
        ]);
        $conductor = Conductor::factory()->create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
        $user = User::factory()->create(['id_persona' => $persona->id]);
        $user->assignRole(['conductor', 'jefe-area']);

        $vehiculo = Vehiculo::factory()->create(['codigo' => 'V-01', 'nro_placa' => '1234-ABC']);
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => '2026-01-15',
            'estado_asignacion' => 'ACTIVO',
        ]);

        DocumentoConductor::create([
            'id_conductor' => $conductor->id,
            'tipo_documento' => 'LICENCIA_DE_CONDUCIR',
            'numero_documento' => 'LIC-999',
            'categoria' => 'C',
            'fecha_vencimiento' => now()->subDay()->format('Y-m-d'),
        ]);

        $area = Area::factory()->create(['nombre_area' => 'Mina Norte']);
        $area->encargados()->attach($persona->id, [
            'tipo_encargo' => 'TITULAR',
            'fecha_inicio' => '2026-02-01',
            'estado_encargo' => 'ACTIVO',
        ]);

        $vehiculoDelArea = Vehiculo::factory()->create(['nro_placa' => '5555-XYZ']);
        $area->vehiculos()->attach($vehiculoDelArea->id, [
            'fecha_asignacion' => '2026-03-01',
            'estado_asignacion' => 'ACTIVO',
        ]);
        $vehiculoAnterior = Vehiculo::factory()->create(['nro_placa' => '7777-OLD']);
        $area->vehiculos()->attach($vehiculoAnterior->id, [
            'fecha_asignacion' => '2025-06-01',
            'fecha_culminacion' => '2025-12-31',
            'estado_asignacion' => 'CULMINADO',
        ]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                // Vehículos a cargo: sólo los vigentes; el historial trae ambos.
                ->has('ficha.areas.0.vehiculos', 1)
                ->where('ficha.areas.0.vehiculos.0.nro_placa', '5555-XYZ')
                ->has('ficha.areas.0.historial', 2)
                ->where('ficha.areas.0.historial.1.nro_placa', '7777-OLD')
                ->where('ficha.areas.0.historial.1.estado_asignacion', 'CULMINADO')
                ->where('ficha.areas.0.id', $area->id)
                ->where('ficha.roles', fn ($roles) => collect($roles)->sort()->values()->all() === ['conductor', 'jefe-area'])
                ->where('ficha.persona.ci', '6543210')
                ->where('ficha.persona.nombre_completo', 'Marta Choque Lima')
                ->where('ficha.persona.fecha_nacimiento', '20/05/1990')
                ->where('ficha.persona.estado_persona', 'ACTIVO')
                ->where('ficha.conductor.estado', 'ACTIVO')
                ->where('ficha.conductor.licencia.numero', 'LIC-999')
                ->where('ficha.conductor.licencia.vencida', true)
                ->has('ficha.conductor.vehiculos', 1)
                ->where('ficha.conductor.vehiculos.0.nro_placa', '1234-ABC')
                ->where('ficha.conductor.vehiculos.0.desde', '15/01/2026')
                ->has('ficha.areas', 1)
                ->where('ficha.areas.0.nombre_area', 'Mina Norte')
                ->where('ficha.areas.0.tipo_encargo', 'TITULAR')
                ->where('ficha.areas.0.desde', '01/02/2026')
                // El prop del formulario no cambia de forma: no arrastra las
                // relaciones de la ficha.
                ->missing('usuario.persona.encargado_areas')
                ->missing('usuario.persona.conductor.asignaciones_activas')
                ->where('usuario.persona.id', $persona->id)
            );
    }

    public function test_el_perfil_de_una_cuenta_sin_persona_no_falla(): void
    {
        $user = User::factory()->create(['id_persona' => null]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.persona', null)
                ->where('ficha.conductor', null)
                ->where('ficha.areas', [])
                ->where('ficha.roles', [])
            );
    }
}

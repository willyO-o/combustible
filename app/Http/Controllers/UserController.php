<?php

namespace App\Http\Controllers;

use App\Actions\Personas\UpdatePersonaAction;
use App\Http\Requests\UserPasswordRequest;
use App\Http\Requests\UserRequest;
use App\Models\Area;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::with('persona.conductor', 'persona.encargadoAreas');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'like', '%'.$request->email.'%');
        }
        if ($request->filled('estado_usuario')) {
            $query->where('estado_usuario', $request->estado_usuario);
        }

        $usuarios = $query->orderBy('name')->paginate(10)->withQueryString();

        return Inertia::render('Usuarios/Index', [
            // Inertia::scroll() no cambia la forma del paginador (sigue
            // trayendo data/total/from/to/links tal cual): sólo agrega la
            // metadata de merge que usa <InfiniteScroll> en el listado de
            // tarjetas (mobile). Ver .ai/rules/pages.md.
            'usuarios' => Inertia::scroll($usuarios),
            'filters' => $request->only(['name', 'email', 'estado_usuario']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Usuarios/Form', [
            'areas' => Area::where('estado_area', 'ACTIVO')->orderBy('nombre_area')->get(['id', 'nombre_area']),
            'roles' => $this->rolesAsignables(),
        ]);
    }

    public function store(UserRequest $request, UpdatePersonaAction $action): RedirectResponse
    {
        // validar por permiso no por rol
        if (! $request->user()->can('crear-usuario')) {
            abort(403);
        }

        $persona = Persona::findOrFail($request->id_persona);

        $persona = $action->execute($persona, $this->datosPersonaParaAction($persona, $request));

        // UpdatePersonaAction solo asigna, como máximo, el rol de conductor o
        // jefe de área (ver tipoDesdeRoles). Aquí se sincroniza el conjunto
        // completo de roles seleccionado, que puede incluir varios a la vez.
        $persona->user->syncRoles($request->validated('roles', []));

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario para {$persona->nombres} creado exitosamente.");
    }

    public function edit(User $usuario): Response
    {
        $usuario->load(['persona.conductor.asignacionesActivas', 'persona.encargadoAreas']);

        $persona = $usuario->persona;

        return Inertia::render('Usuarios/Form', [
            'usuario' => $usuario,
            'areas' => Area::where('estado_area', 'ACTIVO')->orderBy('nombre_area')->get(['id', 'nombre_area']),
            'roles' => $this->rolesAsignables(),
            'rolesAsignados' => $usuario->getRoleNames(),
            'vehiculoActual' => $persona?->conductor?->asignacionesActivas->first(),
            'areaActual' => $persona?->encargadoAreas->first(),
        ]);
    }

    public function update(UserRequest $request, User $usuario, UpdatePersonaAction $action): RedirectResponse
    {
        if (! $request->user()->can('editar-usuario')) {
            abort(403);
        }

        $persona = $usuario->persona;

        if ($persona) {
            $action->execute($persona, $this->datosPersonaParaAction($persona, $request));

            $usuario->syncRoles($request->validated('roles', []));
        } else {
            // Cuentas de sistema sin persona vinculada (ej. administradores sembrados
            // directamente): solo se gestionan sus datos propios de usuario.
            $usuario->update([
                'email' => $request->email,
                'estado_usuario' => $request->estado_usuario,
            ]);
        }

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$usuario->name} actualizado exitosamente.");
    }

    public function cambiarEstado(Request $request, User $usuario): RedirectResponse
    {
        if (! $request->user()->can('cambiar-estado-usuario')) {
            abort(403);
        }

        $request->validate([
            'estado_usuario' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ]);

        if ($usuario->id === auth()->id() && $request->estado_usuario === 'INACTIVO') {
            return redirect()->route('usuarios.index')
                ->with('error', 'No puedes inactivar tu propio usuario.');
        }

        $usuario->update(['estado_usuario' => $request->estado_usuario]);

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$usuario->name} marcado como {$request->estado_usuario}.");
    }

    public function searchPersonasSinUsuario(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $personas = Persona::whereDoesntHave('user')
            ->where(function ($query) use ($q) {
                $query->where('ci', 'like', "%{$q}%")
                    ->orWhere('nombres', 'like', "%{$q}%")
                    ->orWhere('paterno', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'label' => "{$p->ci} — {$p->nombre_completo}",
            ]);

        return response()->json($personas);
    }

    public function editPassword(User $usuario): Response
    {
        return Inertia::render('Usuarios/ChangePassword', [
            'usuario' => $usuario->only('id', 'name', 'email'),
        ]);
    }

    public function updatePassword(UserPasswordRequest $request, User $usuario): RedirectResponse
    {
        $usuario->update([
            'password' => Hash::make($request->new_password),
        ]);

        return redirect()->route('usuarios.index')
            ->with('success', "Contraseña de {$usuario->name} actualizada exitosamente.");
    }

    /**
     * Construye el array que UpdatePersonaAction espera, partiendo de los
     * datos ya guardados de la persona (que este formulario no vuelve a
     * pedir) y superponiendo los campos de usuario/tipo que sí envía.
     *
     * @return array<string, mixed>
     */
    private function datosPersonaParaAction(Persona $persona, UserRequest $request): array
    {
        return array_merge([
            'ci' => $persona->ci,
            'nombres' => $persona->nombres,
            'paterno' => $persona->paterno,
            'materno' => $persona->materno,
            'celular' => $persona->celular,
            'direccion' => $persona->direccion,
            'fecha_nacimiento' => $persona->fecha_nacimiento?->format('Y-m-d'),
            'estado_persona' => $persona->estado_persona,
        ], $request->validated(), [
            'crear_usuario' => true,
            'tipo' => $this->tipoDesdeRoles($request->validated('roles', [])),
        ]);
    }

    /**
     * UpdatePersonaAction (compartida con PersonaController) sigue decidiendo
     * qué registros de dominio gestionar (conductor + asignación, o jefe de
     * área + encargo) a partir de un único "tipo". Con múltiples roles, se
     * deriva ese tipo dando prioridad a conductor y luego a jefe-area —
     * igual que Persona::tipo_actual — sin que eso limite los demás roles,
     * que se sincronizan aparte con el conjunto completo seleccionado.
     *
     * @param  array<int, string>  $roles
     */
    private function tipoDesdeRoles(array $roles): string
    {
        return match (true) {
            in_array('conductor', $roles, true) => 'conductor',
            in_array('jefe-area', $roles, true) => 'jefe-area',
            default => 'personal',
        };
    }

    /**
     * Roles que pueden asignarse a un usuario desde este módulo: todos los
     * existentes (y los que se creen a futuro desde el módulo de Roles),
     * salvo los ocultos (super-admin, ver config/acl.php).
     */
    private function rolesAsignables(): Collection
    {
        return Role::whereNotIn('name', config('acl.roles_ocultos'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}

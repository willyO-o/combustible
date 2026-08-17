<?php

namespace App\Http\Controllers;

use App\Actions\Personas\UpdatePersonaAction;
use App\Http\Requests\UserPasswordRequest;
use App\Http\Requests\UserRequest;
use App\Models\Area;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
            'usuarios' => $usuarios,
            'filters' => $request->only(['name', 'email', 'estado_usuario']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Usuarios/Create', [
            'areas' => Area::where('estado_area', 'ACTIVO')->orderBy('nombre_area')->get(['id', 'nombre_area']),
        ]);
    }

    public function store(UserRequest $request, UpdatePersonaAction $action): RedirectResponse
    {
        if (! auth()->user()->hasRole('administrador')) {
            abort(403);
        }

        $persona = Persona::findOrFail($request->id_persona);

        $action->execute($persona, $this->datosPersonaParaAction($persona, $request));

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario para {$persona->nombres} creado exitosamente.");
    }

    public function edit(User $usuario): Response
    {
        $usuario->load(['persona.conductor.asignacionesActivas', 'persona.encargadoAreas']);

        $persona = $usuario->persona;

        return Inertia::render('Usuarios/Edit', [
            'usuario' => $usuario,
            'areas' => Area::where('estado_area', 'ACTIVO')->orderBy('nombre_area')->get(['id', 'nombre_area']),
            'vehiculoActual' => $persona?->conductor?->asignacionesActivas->first(),
            'areaActual' => $persona?->encargadoAreas->first(),
        ]);
    }

    public function update(UserRequest $request, User $usuario, UpdatePersonaAction $action): RedirectResponse
    {
        if (! auth()->user()->hasRole('administrador')) {
            abort(403);
        }

        $persona = $usuario->persona;

        if ($persona) {
            $action->execute($persona, $this->datosPersonaParaAction($persona, $request));
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
        if (! auth()->user()->hasRole('administrador')) {
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
        ]);
    }
}

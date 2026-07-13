<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserPasswordRequest;
use App\Http\Requests\UserRequest;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::with('roles');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }
        if ($request->filled('id_rol')) {
            $query->whereHas('roles', fn($q) => $q->where('rol.id', $request->id_rol));
        }

        $usuarios = $query->orderBy('name')->paginate(10)->withQueryString();

        return Inertia::render('Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles'    => Rol::where('estado_rol', 'ACTIVO')->orderBy('rol')->get(['id', 'rol']),
            'filters'  => $request->only(['name', 'email', 'id_rol']),
            'flash'    => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Usuarios/Create', [
            'roles' => Rol::where('estado_rol', 'ACTIVO')->orderBy('rol')->get(['id', 'rol']),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $usuario = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($request->filled('roles')) {
            $usuario->roles()->sync($request->roles);
        }

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$usuario->name} creado exitosamente.");
    }

    public function edit(User $usuario): Response
    {
        $usuario->load('roles');

        return Inertia::render('Usuarios/Edit', [
            'usuario'     => $usuario,
            'roles'       => Rol::where('estado_rol', 'ACTIVO')->orderBy('rol')->get(['id', 'rol']),
            'rolesActual' => $usuario->roles->pluck('id')->toArray(),
        ]);
    }

    public function update(UserRequest $request, User $usuario): RedirectResponse
    {
        $usuario->update([
            'name'  => $request->name,
            'email' => $request->email,
        ]);

        $usuario->roles()->sync($request->roles ?? []);

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$usuario->name} actualizado exitosamente.");
    }

    public function destroy(User $usuario): RedirectResponse
    {
        if ($usuario->id === auth()->id()) {
            return redirect()->route('usuarios.index')
                ->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $usuario->roles()->detach();
        $usuario->delete();

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario eliminado exitosamente.");
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
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'usuario' => $request->user()->load('persona'),
        ]);
    }

    /**
     * Update the user's profile information (foto, nombre, celular, dirección).
     * No gestiona el correo ni el nombre de la persona, ni permite eliminar la
     * cuenta: eso se hace desde el módulo de Personas/Usuarios (administradores).
     *
     * `email` no es editable desde el perfil (ProfileUpdateRequest ya no lo
     * valida, así que nunca llega en $data), pero igual se omite
     * explícitamente aquí por seguridad ante cualquier cambio futuro en las
     * reglas de validación.
     *
     * `celular`/`direccion` se guardan en persona a través de la relación
     * users.id_persona -> persona (no existen columnas propias en users): si
     * el usuario no tiene una persona vinculada (ej. cuentas de sistema),
     * esos campos simplemente no se actualizan.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $usuario = $request->user();

        $data = $request->validated();
        unset($data['email']);

        $datosPersona = [
            'celular' => $data['celular'] ?? null,
            'direccion' => $data['direccion'] ?? null,
        ];
        unset($data['celular'], $data['direccion']);

        if ($request->hasFile('foto')) {
            if ($usuario->foto) {
                Storage::disk('public')->delete($usuario->foto);
            }
            $data['foto'] = $request->file('foto')->store('usuarios', 'public');
        } else {
            unset($data['foto']);
        }

        $usuario->fill($data)->save();

        if ($usuario->persona) {
            $usuario->persona->update($datosPersona);
        }

        return redirect()->route('profile.edit')
            ->with('success', 'Perfil actualizado exitosamente.');
    }
}

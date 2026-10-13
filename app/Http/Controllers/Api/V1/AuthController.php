<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Inicia sesión y retorna el token JWT.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'remember' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        // Con remember: TTL extendido (config JWT_REMEMBER_TTL). Sin remember: TTL estándar.
        if ($request->boolean('remember')) {
            auth('api')->factory()->setTTL(config('jwt.remember_ttl'));
        }

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Retorna los datos del usuario autenticado.
     */
    public function me(): JsonResponse
    {
        $user = auth('api')->user();
        $user->load('persona');

        return response()->json([
            'success' => true,
            'data' => $this->userData($user),
        ]);
    }

    /**
     * Actualiza los datos editables del perfil del usuario autenticado.
     *
     * Solo se pueden actualizar `name` (puede usarse como apodo), `email`,
     * `celular`, `direccion` y `foto`; la contraseña no se gestiona aquí.
     * `email` es la credencial de acceso: cambiarlo exige `current_password`
     * (mismo criterio que el perfil web, ver User::cambiaraElCorreoA()); el
     * token JWT sigue siendo válido porque identifica al usuario por su id.
     * `celular`/`direccion` se guardan en la persona vinculada
     * al usuario (`users.id_persona` -> `persona`): si el usuario no tiene
     * una persona vinculada, esos dos campos simplemente no se actualizan
     * (no es un error).
     *
     * Al incluir `foto`, la petición debe enviarse como `multipart/form-data`
     * (no `application/json`). Como PHP no procesa el cuerpo de una petición
     * `PUT`/`PATCH` con `multipart/form-data`, debe enviarse como `POST` con
     * un campo `_method=PUT` (o `PATCH`) para que Laravel la trate como tal
     * — igual que el resto de peticiones de esta API que suben archivos.
     */
    public function updateMe(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $entrada = $request->all();
        if (is_string($entrada['email'] ?? null)) {
            $entrada['email'] = trim($entrada['email']);
        }

        // Antes de guardar: después, el usuario ya tiene el correo nuevo.
        $cambiaElCorreo = $user->cambiaraElCorreoA($entrada['email'] ?? null);

        $validator = Validator::make($entrada, [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => [Rule::requiredIf($cambiaElCorreo), 'nullable', 'current_password:api'],
            'celular' => ['sometimes', 'nullable', 'string', 'max:20'],
            'direccion' => ['sometimes', 'nullable', 'string', 'max:250'],
            // nullable: `foto: null` (JSON) o vacía (multipart) se ignora y
            // conserva la actual — sólo un archivo nuevo la reemplaza (hasFile()).
            'foto' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
        ], [
            'email.unique' => 'Este correo electrónico ya está registrado por otro usuario.',
            'current_password.required' => 'Ingresa tu contraseña actual para cambiar el correo electrónico.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ], [
            'email' => 'correo electrónico',
            'current_password' => 'contraseña actual',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // current_password sólo valida, nunca se guarda.
        $datosUsuario = array_intersect_key($data, array_flip(['name', 'email']));

        if ($request->hasFile('foto')) {
            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
            }
            $datosUsuario['foto'] = $request->file('foto')->store('usuarios', 'public');
        }

        if ($datosUsuario !== []) {
            $user->update($datosUsuario);
        }

        $datosPersona = array_intersect_key($data, array_flip(['celular', 'direccion']));

        if ($user->persona && $datosPersona !== []) {
            $user->persona->update($datosPersona);
        }

        $user->load('persona');

        return response()->json([
            'success' => true,
            'message' => $cambiaElCorreo
                ? 'Perfil actualizado. Desde ahora inicia sesión con '.$user->email.'.'
                : 'Perfil actualizado exitosamente.',
            'data' => $this->userData($user),
        ]);
    }

    /**
     * Cierra la sesión e invalida el token.
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    /**
     * Refresca el token JWT.
     */
    public function refresh(): JsonResponse
    {
        try {
            $newToken = JWTAuth::parseToken()->refresh();
        } catch (TokenExpiredException) {
            return response()->json([
                'success' => false,
                'message' => 'El token ha expirado y no puede ser refrescado.',
            ], 401);
        } catch (TokenInvalidException) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido.',
            ], 401);
        } catch (JWTException) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el token.',
            ], 401);
        }

        return $this->respondWithToken($newToken);
    }

    /**
     * Formatea la respuesta con el token JWT.
     */
    private function respondWithToken(string $token): JsonResponse
    {
        $userData = auth('api')->user();

        $userData->load('persona');

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->userData($userData),
        ]);
    }

    private function userData($user)
    {

        return [
            'name' => $user->name,
            'email' => $user->email,
            'nombre_completo' => $user->persona->nombre_completo ?? null,
            'nombres' => $user->persona->nombres ?? null,
            'paterno' => $user->persona->paterno ?? null,
            'materno' => $user->persona->materno ?? null,
            'ci' => $user->persona->ci ?? null,
            'f_nacimiento_formatted' => $user->persona->f_nacimiento_formatted ?? null,
            'foto_url' => $user->foto_url ?? null,
            'celular' => $user->persona->celular ?? null,
            'direccion' => $user->persona->direccion ?? null,
            'roles' => $user->roles->pluck('name'), // Retorna solo los nombres de los roles
            'permisos' => $user->getAllPermissions()->pluck('name'), // Retorna solo los nombres de los permisos
        ];
    }
}

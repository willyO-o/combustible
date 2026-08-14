<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class AuthController extends Controller
{
    /**
     * Inicia sesión y retorna el token JWT.
     *
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
            'remember' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos.',
                'errors'  => $validator->errors(),
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
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = auth('api')->user();
        $user->load('persona');

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    /**
     * Cierra la sesión e invalida el token.
     *
     * @return JsonResponse
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
     *
     * @return JsonResponse
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
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => [
                'name'     => $userData->name,
                'email'    => $userData->email,
                'nombre_completo'  => $userData->persona->nombre_completo ?? null,
                'nombres'  => $userData->persona->nombres ?? null,
                'paterno'  => $userData->persona->paterno ?? null,
                'materno'  => $userData->persona->materno ?? null,
                'ci'       => $userData->persona->ci ?? null,
                'f_nacimiento_formatted' => $userData->persona->f_nacimiento_formatted ?? null,
                'foto_url' => $userData->persona->foto_url ?? null,
                'celular' => $userData->persona->celular ?? null,
                'roles'    => $userData->roles->pluck('name'), // Retorna solo los nombres de los roles
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * Autenticación de la API mediante tokens de Laravel Sanctum.
 */
class AuthController extends Controller
{
    /**
     * Inicia sesión y devuelve un token de acceso.
     *
     * Responde 401 con el mismo mensaje si el email no existe o si la
     * clave es incorrecta, para no revelar qué emails están registrados.
     */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $usuario = User::where('email', $datos['email'])->first();

        if (! $usuario || ! Hash::check($datos['password'], $usuario->password)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        return response()->json([
            'token' => $usuario->createToken('api')->plainTextToken,
        ]);
    }

    /**
     * Cierra sesión revocando únicamente el token con el que se hizo
     * la petición (otras sesiones del mismo usuario siguen activas).
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
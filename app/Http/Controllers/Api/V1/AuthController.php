<?php
/*
 * ============================================================
 * BUAT APA : REST API: login/register (token Sanctum), logout, dan profil. Token menjalankan hak akses PERAN yang sama dengan web (dijaga Policy di server).
 * ============================================================
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // POST /api/v1/auth/login  {email, password, device_name?}
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Pesan sengaja umum: tidak membocorkan apakah email terdaftar
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'These credentials do not match our records.'], 401);
        }

        return $this->tokenResponse($user, $data['device_name'] ?? 'api');
    }

    // POST /api/v1/auth/register  -> selalu menjadi customer (role tidak bisa dikirim)
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->tokenResponse($user->refresh(), 'api', 201);
    }

    // GET /api/v1/auth/me
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    // POST /api/v1/auth/logout  -> mencabut token yang sedang dipakai
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function tokenResponse(User $user, string $device, int $status = 200): JsonResponse
    {
        // Satu perangkat = satu token aktif (login ulang mengganti yang lama)
        $user->tokens()->where('name', $device)->delete();

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $user->createToken($device)->plainTextToken,
            'user' => new UserResource($user),
        ], $status);
    }
}

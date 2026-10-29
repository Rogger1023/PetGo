<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        // Cadastro público sempre cria um cliente.
        $user->role = 'customer';
        $user->save();

        // Gera um token de acesso para o novo usuário.
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user->only([
                'id',
                'name',
                'email',
                'role',
            ]),
            'token' => $token,
        ], 201);
    }
}

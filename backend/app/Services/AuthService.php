<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // Todo usuário cadastrado publicamente será um cliente.
        $user->role = 'customer';
        $user->save();

        return $this->generateAuthData($user);
    }

    public function login(array $credentials): ?array
    {
        $user = User::where('email', $credentials['email'])->first();

        // Verifica se o usuário existe e se a senha está correta.
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        return $this->generateAuthData($user);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    private function generateAuthData(User $user): array
    {
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'token' => $token,
        ];
    }
}

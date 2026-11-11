<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly CartMergeService $cartMergeService
    ) {
    }

    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->role = 'customer';
        $user->save();

        return $this->generateAuthData($user);
    }

    public function login(
        array $credentials,
        ?string $guestCartToken = null
    ): ?array {
        $user = User::where(
            'email',
            $credentials['email']
        )->first();

        if (
            !$user ||
            !Hash::check($credentials['password'], $user->password)
        ) {
            return null;
        }

        if ($guestCartToken !== null) {
            $this->cartMergeService->merge(
                $user,
                $guestCartToken
            );
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

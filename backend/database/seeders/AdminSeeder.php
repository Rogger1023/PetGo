<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) getenv('PETGO_ADMIN_NAME'));
        $email = trim((string) getenv('PETGO_ADMIN_EMAIL'));
        $password = (string) getenv('PETGO_ADMIN_PASSWORD');

        if ($name === '' || strlen($name) > 255) {
            throw new RuntimeException('PETGO_ADMIN_NAME deve ter entre 1 e 255 caracteres.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new RuntimeException('PETGO_ADMIN_EMAIL deve ser um e-mail valido (maximo de 255 caracteres).');
        }

        if (strlen($password) < 12) {
            throw new RuntimeException('PETGO_ADMIN_PASSWORD deve conter pelo menos 12 caracteres.');
        }

        if (User::where('email', $email)->exists()) {
            throw new RuntimeException('Ja existe uma conta com este e-mail. Nenhuma permissao foi alterada.');
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $user->role = 'admin';
        $user->save();

        $this->command?->info('Administrador criado com sucesso.');
    }
}

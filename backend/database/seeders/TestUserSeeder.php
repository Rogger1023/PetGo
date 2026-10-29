<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'O TestUserSeeder e exclusivo para desenvolvimento.'
            );
        }

        $users = [
            [
                'name' => 'Administrador de Teste',
                'email' => 'admin@petgo.test',
                'password' => 'PetGoLocal#2026',
                'role' => 'admin',
            ],
            [
                'name' => 'Cliente de Teste',
                'email' => 'cliente@petgo.test',
                'password' => 'PetGoLocal#2026',
                'role' => 'customer',
            ],
        ];

        foreach ($users as $user) {
            $model = User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => $user['password'],
                ]
            );

            $model->role = $user['role'];
            $model->save();
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'Os seeders de desenvolvimento nao devem ser executados em producao.'
            );
        }

        $this->call([
            CategorySeeder::class,
            ProductSeeder::class,
            TestUserSeeder::class,
        ]);
    }
}

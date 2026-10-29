<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Alimentação',
                'slug' => 'alimentacao',
                'description' => 'Rações, petiscos e alimentos para animais.',
            ],
            [
                'name' => 'Brinquedos',
                'slug' => 'brinquedos',
                'description' => 'Brinquedos para entretenimento dos animais.',
            ],
            [
                'name' => 'Higiene',
                'slug' => 'higiene',
                'description' => 'Produtos de higiene e cuidados para pets.',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                ]
            );
        }
    }
}

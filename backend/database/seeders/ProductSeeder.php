<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'alimentacao' => Category::where(
                'slug',
                'alimentacao'
            )->firstOrFail(),

            'brinquedos' => Category::where(
                'slug',
                'brinquedos'
            )->firstOrFail(),

            'higiene' => Category::where(
                'slug',
                'higiene'
            )->firstOrFail(),
        ];

        $products = [
            [
                'category_id' => $categories['alimentacao']->id,
                'name' => 'Racao Premium para Caes 1kg',
                'slug' => 'racao-premium-caes-1kg',
                'description' => 'Racao premium para alimentacao diaria de caes.',
                'image_path' => null,
                'price_cents' => 7990,
                'stock' => 25,
                'is_active' => true,
            ],
            [
                'category_id' => $categories['alimentacao']->id,
                'name' => 'Petisco Natural para Caes',
                'slug' => 'petisco-natural-caes',
                'description' => 'Petisco para recompensar os caes.',
                'image_path' => null,
                'price_cents' => 2490,
                'stock' => 0,
                'is_active' => true,
            ],
            [
                'category_id' => $categories['brinquedos']->id,
                'name' => 'Bola Interativa para Gatos',
                'slug' => 'bola-interativa-gatos',
                'description' => 'Brinquedo para estimular a atividade dos gatos.',
                'image_path' => null,
                'price_cents' => 1990,
                'stock' => 15,
                'is_active' => true,
            ],
            [
                'category_id' => $categories['higiene']->id,
                'name' => 'Shampoo Neutro para Pets',
                'slug' => 'shampoo-neutro-pets',
                'description' => 'Produto para higiene e banho de animais.',
                'image_path' => null,
                'price_cents' => 3290,
                'stock' => 12,
                'is_active' => false,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['slug' => $product['slug']],
                $product
            );
        }
    }
}

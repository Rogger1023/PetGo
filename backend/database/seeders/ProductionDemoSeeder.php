<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductionDemoSeeder extends Seeder
{
    /**
     * Popula o catálogo demonstrativo sem sobrescrever registros existentes.
     * Não cria usuários ou credenciais de desenvolvimento.
     */
    public function run(): void
    {
        $categories = [
            'alimentacao' => [
                'name' => 'Alimentação',
                'description' => 'Rações, petiscos e alimentos para pets.',
            ],
            'brinquedos' => [
                'name' => 'Brinquedos',
                'description' => 'Brinquedos para diversão e enriquecimento ambiental.',
            ],
            'higiene' => [
                'name' => 'Higiene',
                'description' => 'Produtos para higiene e cuidados com os pets.',
            ],
        ];

        $categoryIds = [];

        foreach ($categories as $slug => $attributes) {
            $category = Category::firstOrCreate(
                ['slug' => $slug],
                $attributes
            );

            $categoryIds[$slug] = $category->id;
        }

        $products = [
            [
                'alimentacao',
                'Ração Premium para Cães 3 kg',
                'demo-racao-premium-caes-3kg',
                'Alimento completo para cães adultos.',
                8990,
                25,
            ],
            [
                'alimentacao',
                'Ração para Gatos 1,5 kg',
                'demo-racao-gatos-15kg',
                'Ração para alimentação diária de gatos adultos.',
                6790,
                18,
            ],
            [
                'alimentacao',
                'Ração para Filhotes 2 kg',
                'demo-racao-filhotes-2kg',
                'Alimento desenvolvido para cães filhotes.',
                7590,
                21,
            ],
            [
                'alimentacao',
                'Sachê para Gatos Sabor Salmão',
                'demo-sache-gatos-salmao',
                'Alimento úmido sabor salmão.',
                850,
                70,
            ],
            [
                'alimentacao',
                'Biscoito Dental para Cães',
                'demo-biscoito-dental-caes',
                'Petisco para complementar a rotina de cuidados.',
                2990,
                28,
            ],
            [
                'alimentacao',
                'Petisco Natural de Frango',
                'demo-petisco-natural-frango',
                'Petisco de frango para cães.',
                2490,
                35,
            ],
            [
                'alimentacao',
                'Ração Sênior para Cães 3 kg',
                'demo-racao-senior-caes-3kg',
                'Alimento para cães em fase sênior.',
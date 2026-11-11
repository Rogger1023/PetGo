<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductionDemoSeeder extends Seeder
{
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
                9490,
                13,
            ],
            [
                'alimentacao',
                'Patê para Cães 300 g',
                'demo-pate-caes-300g',
                'Alimento úmido para cães adultos.',
                1290,
                0,
            ],

            [
                'brinquedos',
                'Bola Interativa para Cães',
                'demo-bola-interativa-caes',
                'Bola para brincadeiras e estímulo físico.',
                3590,
                40,
            ],
            [
                'brinquedos',
                'Corda com Nós',
                'demo-corda-com-nos',
                'Brinquedo de corda para interação com cães.',
                2190,
                33,
            ],
            [
                'brinquedos',
                'Pelúcia Raposa',
                'demo-pelucia-raposa',
                'Brinquedo de pelúcia para pets.',
                4890,
                20,
            ],
            [
                'brinquedos',
                'Arranhador de Papelão',
                'demo-arranhador-papelao',
                'Arranhador compacto para gatos.',
                7990,
                12,
            ],
            [
                'brinquedos',
                'Varinha com Penas',
                'demo-varinha-com-penas',
                'Brinquedo interativo para gatos.',
                1890,
                55,
            ],
            [
                'brinquedos',
                'Mordedor de Borracha',
                'demo-mordedor-borracha',
                'Brinquedo de borracha para cães.',
                2790,
                38,
            ],
            [
                'brinquedos',
                'Disco Frisbee Pet',
                'demo-disco-frisbee-pet',
                'Disco para brincadeiras ao ar livre.',
                3290,
                0,
            ],
            [
                'brinquedos',
                'Túnel Dobrável para Gatos',
                'demo-tunel-gatos',
                'Túnel para brincadeiras e exploração.',
                11990,
                9,
            ],

            [
                'higiene',
                'Shampoo Neutro Pet 500 ml',
                'demo-shampoo-neutro-500ml',
                'Shampoo para a higiene de cães e gatos.',
                3490,
                42,
            ],
            [
                'higiene',
                'Condicionador Pet 500 ml',
                'demo-condicionador-pet-500ml',
                'Condicionador para cuidados com a pelagem.',
                3790,
                25,
            ],
            [
                'higiene',
                'Tapete Higiênico 30 Unidades',
                'demo-tapete-higienico-30',
                'Pacote com 30 tapetes higiênicos.',
                6990,
                34,
            ],
            [
                'higiene',
                'Areia Sanitária para Gatos 4 kg',
                'demo-areia-sanitaria-4kg',
                'Areia sanitária para uso em caixas de gatos.',
                4290,
                45,
            ],
            [
                'higiene',
                'Escova Desembaraçadora',
                'demo-escova-desembaracadora',
                'Escova para cuidados com a pelagem.',
                3990,
                15,
            ],
            [
                'higiene',
                'Lenços Umedecidos Pet 50 Unidades',
                'demo-lencos-umedecidos-50',
                'Lenços para limpeza rápida de pets.',
                2290,
                50,
            ],
            [
                'higiene',
                'Cortador de Unhas Pet',
                'demo-cortador-unhas',
                'Acessório para cuidados com as unhas.',
                2490,
                27,
            ],
            [
                'higiene',
                'Spray Higienizador Pet 500 ml',
                'demo-spray-higienizador-500ml',
                'Produto para cuidados de higiene.',
                2990,
                0,
            ],
        ];

        foreach ($products as [
            $categorySlug,
            $name,
            $slug,
            $description,
            $priceCents,
            $stock
        ]) {
            Product::firstOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $categoryIds[$categorySlug],
                    'name' => $name,
                    'description' => $description,
                    'price_cents' => $priceCents,
                    'stock' => $stock,
                    'is_active' => true,
                ]
            );
        }
    }
}

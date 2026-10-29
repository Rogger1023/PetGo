<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_endpoint_returns_only_active_products(): void
    {
        $category = Category::factory()->create();

        Product::factory()->for($category)->create([
            'name' => 'Coleira para cachorro',
            'slug' => 'coleira-para-cachorro',
            'price_cents' => 2590,
            'stock' => 3,
            'is_active' => true,
        ]);

        Product::factory()->for($category)->inactive()->create([
            'name' => 'Produto inativo',
            'slug' => 'produto-inativo',
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'image_path',
                        'price_cents',
                        'in_stock',
                        'category' => [
                            'id',
                            'name',
                            'slug',
                            'description',
                        ],
                    ],
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Coleira para cachorro')
            ->assertJsonPath('data.0.price_cents', 2590)
            ->assertJsonPath('data.0.in_stock', true)
            ->assertJsonPath('data.0.category.name', $category->name)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissing([
                'name' => 'Produto inativo',
            ]);
    }
}

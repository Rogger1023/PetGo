<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function createCategory(string $name, string $slug): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'description' => 'Categoria criada para testes.',
        ]);
    }

    private function createProduct(
        Category $category,
        string $name,
        string $slug,
        bool $isActive = true
    ): Product {
        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'description' => 'Produto criado para testes.',
            'price_cents' => 1990,
            'stock' => 10,
            'image_path' => null,
            'is_active' => $isActive,
        ]);
    }

    public function test_search_filters_products_by_name(): void
    {
        $category = $this->createCategory('Alimentação', 'alimentacao');

        $this->createProduct(
            $category,
            'Racao Premium',
            'racao-premium'
        );

        $this->createProduct(
            $category,
            'Petisco Natural',
            'petisco-natural'
        );

        $this->createProduct(
            $category,
            'Racao Inativa',
            'racao-inativa',
            false
        );

        $response = $this->getJson('/api/products?search=Racao');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Racao Premium')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_search_and_category_filters_work_together(): void
    {
        $toys = $this->createCategory('Brinquedos', 'brinquedos');
        $food = $this->createCategory('Alimentação', 'alimentacao');

        $this->createProduct($toys, 'Bola de Corda', 'bola-corda');
        $this->createProduct($toys, 'Pelucia Pet', 'pelucia-pet');
        $this->createProduct($food, 'Bola Comestivel', 'bola-comestivel');

        $response = $this->getJson('/api/products?' . http_build_query([
            'search' => 'Bola',
            'category_id' => $toys->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bola de Corda')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_pagination_is_applied_after_category_filter(): void
    {
        $toys = $this->createCategory('Brinquedos', 'brinquedos');
        $food = $this->createCategory('Alimentação', 'alimentacao');

        for ($i = 1; $i <= 13; $i++) {
            $this->createProduct(
                $toys,
                "Brinquedo {$i}",
                "brinquedo-{$i}"
            );
        }

        $this->createProduct($food, 'Racao', 'racao');

        $filters = http_build_query([
            'category_id' => $toys->id,
        ]);

        $this->getJson("/api/products?{$filters}")
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson("/api/products?{$filters}&page=2")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_invalid_category_is_rejected(): void
    {
        $this->getJson('/api/products?category_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
    }
}

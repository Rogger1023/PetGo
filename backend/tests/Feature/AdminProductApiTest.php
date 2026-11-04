<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function productData(Category $category): array
    {
        return [
            'category_id' => $category->id,
            'name' => 'Ração Premium',
            'slug' => 'racao-premium',
            'description' => 'Ração para cães adultos',
            'price_cents' => 8990,
            'stock' => 15,
            'is_active' => true,
        ];
    }

    private function authenticateAdmin(): User
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_guest_cannot_create_product(): void
    {
        $this->postJson('/api/admin/products', [])
            ->assertUnauthorized();
    }

    public function test_customer_cannot_manage_products(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        Sanctum::actingAs($customer);

        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();

        $this->postJson(
            '/api/admin/products',
            $this->productData($category)
        )->assertForbidden();

        $this->patchJson(
            "/api/admin/products/{$product->id}",
            ['name' => 'Nome alterado']
        )->assertForbidden();

        $this->deleteJson(
            "/api/admin/products/{$product->id}"
        )->assertForbidden();
    }

    public function test_admin_can_create_product(): void
    {
        $this->authenticateAdmin();

        $category = Category::factory()->create();
        $data = $this->productData($category);

        $this->postJson('/api/admin/products', $data)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ração Premium')
            ->assertJsonPath('data.price_cents', 8990);

        $this->assertDatabaseHas('products', [
            'slug' => 'racao-premium',
            'category_id' => $category->id,
            'stock' => 15,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_create_product_with_invalid_data(): void
    {
        $this->authenticateAdmin();

        $this->postJson('/api/admin/products', [
            'name' => '',
            'slug' => 'slug invalido!',
            'price_cents' => -100,
            'stock' => -5,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id',
                'name',
                'slug',
                'price_cents',
                'stock',
            ]);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_public_can_view_active_product(): void
    {
        $category = Category::factory()->create();

        $product = Product::factory()->for($category)->create([
            'name' => 'Brinquedo para gatos',
            'is_active' => true,
        ]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Brinquedo para gatos')
            ->assertJsonPath('data.category.id', $category->id);
    }

    public function test_public_cannot_view_inactive_product(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->getJson("/api/products/{$product->id}")
            ->assertNotFound();
    }

    public function test_admin_can_update_product(): void
    {
        $this->authenticateAdmin();

        $product = Product::factory()->create([
            'name' => 'Nome antigo',
            'price_cents' => 5000,
        ]);

        $this->patchJson("/api/admin/products/{$product->id}", [
            'name' => 'Nome atualizado',
            'price_cents' => 7500,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nome atualizado')
            ->assertJsonPath('data.price_cents', 7500);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Nome atualizado',
            'price_cents' => 7500,
        ]);
    }

    public function test_admin_can_keep_same_slug_when_updating(): void
    {
        $this->authenticateAdmin();

        $product = Product::factory()->create([
            'slug' => 'produto-original',
        ]);

        $this->patchJson("/api/admin/products/{$product->id}", [
            'slug' => 'produto-original',
            'name' => 'Produto atualizado',
        ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'produto-original');
    }

    public function test_admin_cannot_duplicate_another_product_slug(): void
    {
        $this->authenticateAdmin();

        Product::factory()->create([
            'slug' => 'slug-existente',
        ]);

        $product = Product::factory()->create([
            'slug' => 'outro-produto',
        ]);

        $this->patchJson("/api/admin/products/{$product->id}", [
            'slug' => 'slug-existente',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_admin_can_deactivate_product(): void
    {
        $this->authenticateAdmin();

        $product = Product::factory()->create([
            'is_active' => true,
        ]);

        $this->deleteJson("/api/admin/products/{$product->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);

        $this->getJson("/api/products/{$product->id}")
            ->assertNotFound();
    }
}

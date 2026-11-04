<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function categoryData(): array
    {
        return [
            'name' => 'Acessórios',
            'slug' => 'acessorios',
            'description' => 'Acessórios para animais.',
        ];
    }

    private function authenticateAdmin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);
    }

    public function test_guest_can_list_categories(): void
    {
        Category::factory()->count(3)->create();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_guest_can_view_category(): void
    {
        $category = Category::factory()->create();

        $this->getJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', $category->name);
    }

    public function test_guest_cannot_create_category(): void
    {
        $this->postJson(
            '/api/admin/categories',
            $this->categoryData()
        )->assertUnauthorized();
    }

    public function test_customer_cannot_manage_categories(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        Sanctum::actingAs($customer);

        $category = Category::factory()->create();

        $this->postJson(
            '/api/admin/categories',
            $this->categoryData()
        )->assertForbidden();

        $this->patchJson(
            "/api/admin/categories/{$category->id}",
            ['name' => 'Alterado']
        )->assertForbidden();

        $this->deleteJson(
            "/api/admin/categories/{$category->id}"
        )->assertForbidden();
    }

    public function test_admin_can_create_category(): void
    {
        $this->authenticateAdmin();

        $this->postJson(
            '/api/admin/categories',
            $this->categoryData()
        )
            ->assertCreated()
            ->assertJsonPath('data.name', 'Acessórios')
            ->assertJsonPath('data.slug', 'acessorios');

        $this->assertDatabaseHas('categories', [
            'name' => 'Acessórios',
            'slug' => 'acessorios',
        ]);
    }

    public function test_admin_cannot_create_invalid_category(): void
    {
        $this->authenticateAdmin();

        $this->postJson('/api/admin/categories', [
            'name' => '',
            'slug' => 'slug inválido!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'slug',
            ]);

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_admin_can_update_category(): void
    {
        $this->authenticateAdmin();

        $category = Category::factory()->create();

        $this->patchJson(
            "/api/admin/categories/{$category->id}",
            ['name' => 'Produtos para gatos']
        )
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Produtos para gatos'
            );

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Produtos para gatos',
        ]);
    }

    public function test_admin_can_keep_same_slug(): void
    {
        $this->authenticateAdmin();

        $category = Category::factory()->create([
            'slug' => 'brinquedos',
        ]);

        $this->patchJson(
            "/api/admin/categories/{$category->id}",
            ['slug' => 'brinquedos']
        )
            ->assertOk()
            ->assertJsonPath('data.slug', 'brinquedos');
    }

    public function test_admin_cannot_duplicate_category_slug(): void
    {
        $this->authenticateAdmin();

        Category::factory()->create([
            'slug' => 'higiene',
        ]);

        $category = Category::factory()->create([
            'slug' => 'brinquedos',
        ]);

        $this->patchJson(
            "/api/admin/categories/{$category->id}",
            ['slug' => 'higiene']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_admin_can_delete_empty_category(): void
    {
        $this->authenticateAdmin();

        $category = Category::factory()->create();

        $this->deleteJson(
            "/api/admin/categories/{$category->id}"
        )->assertNoContent();

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_admin_cannot_delete_category_with_products(): void
    {
        $this->authenticateAdmin();

        $category = Category::factory()->create();

        Product::factory()
            ->for($category)
            ->inactive()
            ->create();

        $this->deleteJson(
            "/api/admin/categories/{$category->id}"
        )->assertStatus(409);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }
}

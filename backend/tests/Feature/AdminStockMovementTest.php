<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStockMovementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function product(int $stock = 10): Product
    {
        return Product::factory()->create([
            'stock' => $stock,
        ]);
    }

    private function adjustStock(
        Product $product,
        int $stock,
        User $admin
    ): void {
        app(ProductService::class)->updateProduct(
            $product,
            ['stock' => $stock],
            $admin
        );
    }

    public function test_guest_cannot_view_stock_history(): void
    {
        $product = $this->product();

        $this->getJson(
            "/api/admin/products/{$product->id}/stock-movements"
        )->assertUnauthorized();
    }

    public function test_customer_cannot_view_stock_history(): void
    {
        $customer = $this->user('customer');
        $product = $this->product();

        Sanctum::actingAs($customer);

        $this->getJson(
            "/api/admin/products/{$product->id}/stock-movements"
        )->assertForbidden();
    }

    public function test_admin_can_view_stock_history(): void
    {
        $admin = $this->user('admin');
        $product = $this->product(10);

        $this->adjustStock($product, 15, $admin);

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/admin/products/{$product->id}/stock-movements"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonPath('data.0.actor_user_id', $admin->id)
            ->assertJsonPath('data.0.source', 'admin_product_update')
            ->assertJsonPath('data.0.quantity_delta', 5)
            ->assertJsonPath('data.0.stock_before', 10)
            ->assertJsonPath('data.0.stock_after', 15)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'product_id',
                        'actor_user_id',
                        'order_id',
                        'source',
                        'quantity_delta',
                        'stock_before',
                        'stock_after',
                        'reason',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_nonexistent_product_returns_404(): void
    {
        $admin = $this->user('admin');

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/admin/products/999999/stock-movements'
        )->assertNotFound();
    }

    public function test_product_without_history_returns_empty_list(): void
    {
        $admin = $this->user('admin');
        $product = $this->product();

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/admin/products/{$product->id}/stock-movements"
        )
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_history_is_filtered_by_product(): void
    {
        $admin = $this->user('admin');

        $productA = $this->product(10);
        $productB = $this->product(20);

        $this->adjustStock($productA, 15, $admin);
        $this->adjustStock($productB, 30, $admin);

        $movementA = $productA->stockMovements()
            ->firstOrFail();

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/admin/products/{$productA->id}/stock-movements"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $movementA->id)
            ->assertJsonPath('data.0.product_id', $productA->id)
            ->assertJsonPath('data.0.quantity_delta', 5);
    }

    public function test_history_is_paginated_newest_first(): void
    {
        $admin = $this->user('admin');
        $product = $this->product(0);

        $movementIds = [];

        // Gera 23 ajustes reais pelo ProductService.
        for ($stock = 1; $stock <= 23; $stock++) {
            $this->adjustStock($product, $stock, $admin);

            $movementIds[] = $product->stockMovements()
                ->orderByDesc('id')
                ->firstOrFail()
                ->id;
        }

        Sanctum::actingAs($admin);

        // Primeira página: 20 registros mais recentes.
        $this->getJson(
            "/api/admin/products/{$product->id}/stock-movements"
        )
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 23)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.0.id', $movementIds[22]);

        // Segunda página: 3 registros restantes.
        $this->getJson(
            "/api/admin/products/{$product->id}/stock-movements?page=2"
        )
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 23)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.0.id', $movementIds[2]);
    }
}

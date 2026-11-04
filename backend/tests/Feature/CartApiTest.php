<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateUser(): User
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_guest_cannot_access_cart(): void
    {
        $this->getJson('/api/cart')
            ->assertUnauthorized();

        $this->postJson('/api/cart/items', [])
            ->assertUnauthorized();

        $this->patchJson('/api/cart/items/1', [
            'quantity' => 2,
        ])->assertUnauthorized();

        $this->deleteJson('/api/cart/items/1')
            ->assertUnauthorized();
    }

    public function test_user_can_get_empty_cart(): void
    {
        $user = $this->authenticateUser();

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonCount(0, 'data.items');

        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_user_can_add_product(): void
    {
        $this->authenticateUser();

        $product = Product::factory()->create([
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_adding_same_product_increases_quantity(): void
    {
        $this->authenticateUser();

        $product = Product::factory()->create([
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 5);

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_user_cannot_add_more_than_available_stock(): void
    {
        $this->authenticateUser();

        $product = Product::factory()->create([
            'stock' => 3,
            'is_active' => true,
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 5,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_user_cannot_exceed_stock_by_adding_again(): void
    {
        $this->authenticateUser();

        $product = Product::factory()->create([
            'stock' => 4,
            'is_active' => true,
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk();

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
    }

    public function test_user_cannot_add_inactive_product(): void
    {
        $this->authenticateUser();

        $product = Product::factory()->inactive()->create();

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_rejects_invalid_quantity(): void
    {
        $this->authenticateUser();

        $product = Product::factory()->create();

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_user_can_update_item_quantity(): void
    {
        $this->authenticateUser();

        $product = Product::factory()->create([
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $item = Cart::query()
            ->firstOrFail()
            ->items()
            ->firstOrFail();

        $this->patchJson("/api/cart/items/{$item->id}", [
            'quantity' => 5,
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 5);

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 5,
        ]);
    }

    public function test_user_cannot_update_item_beyond_stock(): void
    {
        $this->authenticateUser();

        $product = Product::factory()->create([
            'stock' => 3,
            'is_active' => true,
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $item = Cart::query()
            ->firstOrFail()
            ->items()
            ->firstOrFail();

        $this->patchJson("/api/cart/items/{$item->id}", [
            'quantity' => 5,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 2,
        ]);
    }

    public function test_user_can_remove_item(): void
    {
        $this->authenticateUser();

        $product = Product::factory()->create([
            'stock' => 10,
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $item = Cart::query()
            ->firstOrFail()
            ->items()
            ->firstOrFail();

        $this->deleteJson("/api/cart/items/{$item->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->assertDatabaseMissing('cart_items', [
            'id' => $item->id,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
        ]);
    }

    public function test_user_cannot_modify_another_users_cart(): void
    {
        $owner = User::factory()->create();

        $otherUser = User::factory()->create();

        $product = Product::factory()->create([
            'stock' => 10,
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $item = $owner->carts()
            ->where('status', 'active')
            ->firstOrFail()
            ->items()
            ->firstOrFail();

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->patchJson("/api/cart/items/{$item->id}", [
            'quantity' => 5,
        ])->assertNotFound();

        $this->deleteJson("/api/cart/items/{$item->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 2,
        ]);
    }

    public function test_user_cannot_update_inactive_product(): void
    {
        $this->authenticateUser();

        $product = Product::factory()->create([
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $item = Cart::query()
            ->firstOrFail()
            ->items()
            ->firstOrFail();

        // Simula o administrador desativando o produto
        $product->update(['is_active' => false]);

        $this->patchJson("/api/cart/items/{$item->id}", [
            'quantity' => 3,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 2,
        ]);
    }

    public function test_user_cannot_update_when_stock_decreases(): void
    {
        $this->authenticateUser();

        $product = Product::factory()->create([
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk();

        $item = Cart::query()
            ->firstOrFail()
            ->items()
            ->firstOrFail();

        // Simula uma alteração no estoque
        $product->update(['stock' => 2]);

        $this->patchJson("/api/cart/items/{$item->id}", [
            'quantity' => 4,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => 3,
        ]);
    }

    public function test_get_cart_does_not_create_multiple_carts(): void
    {
        $user = $this->authenticateUser();

        $this->getJson('/api/cart')->assertOk();
        $this->getJson('/api/cart')->assertOk();
        $this->getJson('/api/cart')->assertOk();

        $this->assertDatabaseCount('carts', 1);

        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }
}

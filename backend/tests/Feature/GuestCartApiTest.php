<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_cart(): void
    {
        $response = $this->postJson('/api/guest/cart');

        $response->assertStatus(201)
            ->assertJsonStructure([
                'cart' => [
                    'id',
                    'status',
                    'items',
                ],
                'guest_cart_token',
            ]);

        $token = $response->json('guest_cart_token');

        $this->assertIsString($token);
        $this->assertMatchesRegularExpression(
            '/\A[a-zA-Z0-9]{64}\z/D',
            $token
        );

        $cart = Cart::query()->firstOrFail();

        $this->assertNull($cart->user_id);
        $this->assertSame('active', $cart->status);
        $this->assertSame(
            hash('sha256', $token),
            $cart->guest_token_hash
        );

        $this->assertNotSame(
            $token,
            $cart->guest_token_hash
        );
    }

    public function test_guest_can_view_cart_with_valid_token(): void
    {
        $createResponse = $this->postJson('/api/guest/cart');

        $token = $createResponse->json('guest_cart_token');

        $response = $this->withHeader(
            'X-Guest-Cart-Token',
            $token
        )->getJson('/api/guest/cart');

        $response->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonCount(0, 'data.items');
    }

    public function test_guest_cannot_view_cart_without_token(): void
    {
        $response = $this->getJson('/api/guest/cart');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Token do carrinho obrigatório.',
            ]);
    }

    public function test_guest_cannot_view_cart_with_unknown_token(): void
    {
        $this->postJson('/api/guest/cart');

        $response = $this->withHeader(
            'X-Guest-Cart-Token',
            str_repeat('A', 64)
        )->getJson('/api/guest/cart');

        $response->assertNotFound();
    }
    
    public function test_guest_can_add_product_to_cart(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
        ]);

        $token = $this->postJson('/api/guest/cart')
            ->json('guest_cart_token');

        $response = $this->withHeader(
            'X-Guest-Cart-Token',
            $token
        )->postJson('/api/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_adding_same_product_increases_quantity(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
        ]);

        $token = $this->postJson('/api/guest/cart')
            ->json('guest_cart_token');

        $this->withHeader(
            'X-Guest-Cart-Token',
            $token
        )->postJson('/api/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->withHeader(
            'X-Guest-Cart-Token',
            $token
        )->postJson('/api/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk()
        ->assertJsonPath('data.items.0.quantity', 5);

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_guest_cannot_add_more_than_available_stock(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'stock' => 3,
        ]);

        $token = $this->postJson('/api/guest/cart')
            ->json('guest_cart_token');

        $this->withHeader(
            'X-Guest-Cart-Token',
            $token
        )->postJson('/api/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->withHeader(
            'X-Guest-Cart-Token',
            $token
        )->postJson('/api/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertStatus(422)
        ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_guest_cannot_add_inactive_product(): void
    {
        $product = Product::factory()->create([
            'is_active' => false,
            'stock' => 10,
        ]);

        $token = $this->postJson('/api/guest/cart')
            ->json('guest_cart_token');

        $this->withHeader(
            'X-Guest-Cart-Token',
            $token
        )->postJson('/api/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(422)
        ->assertJsonValidationErrors('product_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_guest_can_update_item_quantity(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
        ]);

        $token = $this->postJson('/api/guest/cart')
            ->json('guest_cart_token');

        $addResponse = $this->withHeader(
            'X-Guest-Cart-Token',
            $token
        )->postJson('/api/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $addResponse->assertOk();

        $itemId = $addResponse->json('data.items.0.id');

        $response = $this->withHeader(
            'X-Guest-Cart-Token',
            $token
        )->patchJson("/api/guest/cart/items/{$itemId}", [
            'quantity' => 4,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.items.0.quantity', 4);

        $this->assertDatabaseHas('cart_items', [
            'id' => $itemId,
            'quantity' => 4,
        ]);
    }

    public function test_guest_can_remove_item_without_deleting_cart(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
        ]);

        $token = $this->postJson('/api/guest/cart')
            ->json('guest_cart_token');

        $addResponse = $this->withHeader(
            'X-Guest-Cart-Token',
            $token
        )->postJson('/api/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $addResponse->assertOk();

        $cartId = $addResponse->json('data.id');
        $itemId = $addResponse->json('data.items.0.id');

        $response = $this->withHeader(
            'X-Guest-Cart-Token',
            $token
        )->deleteJson("/api/guest/cart/items/{$itemId}");

        $response->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->assertDatabaseMissing('cart_items', [
            'id' => $itemId,
        ]);

        $this->assertDatabaseHas('carts', [
            'id' => $cartId,
            'status' => 'active',
        ]);
    }

    public function test_guest_cannot_remove_another_guests_item(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
        ]);

        $tokenA = $this->postJson('/api/guest/cart')
            ->json('guest_cart_token');

        $tokenB = $this->postJson('/api/guest/cart')
            ->json('guest_cart_token');

        $addResponse = $this->withHeader(
            'X-Guest-Cart-Token',
            $tokenA
        )->postJson('/api/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $addResponse->assertOk();

        $itemId = $addResponse->json('data.items.0.id');

        $response = $this->withHeader(
            'X-Guest-Cart-Token',
            $tokenB
        )->deleteJson("/api/guest/cart/items/{$itemId}");

        $response->assertNotFound();

        $this->assertDatabaseHas('cart_items', [
            'id' => $itemId,
            'quantity' => 2,
        ]);
    }

    public function test_guest_cannot_use_malformed_token(): void
    {
        $this->postJson('/api/guest/cart');

        $response = $this->withHeader(
            'X-Guest-Cart-Token',
            'token-invalido'
        )->getJson('/api/guest/cart');

        $response->assertStatus(422)
            ->assertJsonValidationErrors('guest_cart_token');
    }

}

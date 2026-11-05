<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartMergeTest extends TestCase
{
    use RefreshDatabase;

    private function createGuestCart(): array
    {
        $response = $this->postJson('/api/guest/cart');

        $response->assertCreated();

        return [
            'token' => $response->json('guest_cart_token'),
            'cart_id' => $response->json('cart.id'),
        ];
    }

    public function test_login_without_guest_cart_still_works(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);
    }

    public function test_login_merges_guest_cart_items(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $product = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
        ]);

        $guest = $this->createGuestCart();

        $this->withHeader(
            'X-Guest-Cart-Token',
            $guest['token']
        )->postJson('/api/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->withHeader(
            'X-Guest-Cart-Token',
            $guest['token']
        )->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        $userCart = $user->carts()
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $userCart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('carts', [
            'id' => $guest['cart_id'],
            'status' => 'merged',
            'guest_token_hash' => null,
        ]);

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_login_sums_quantities_of_existing_products(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $product = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
        ]);

        $cartService = app(CartService::class);

        $cartService->addItem(
            $user,
            $product->id,
            3
        );

        $guest = $this->createGuestCart();

        $this->withHeader(
            'X-Guest-Cart-Token',
            $guest['token']
        )->postJson('/api/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->withHeader(
            'X-Guest-Cart-Token',
            $guest['token']
        )->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        $userCart = $user->carts()
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $userCart->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseCount('cart_items', 1);
    }
    
    public function test_merge_fails_when_combined_quantity_exceeds_stock(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $product = Product::factory()->create([
            'is_active' => true,
            'stock' => 3,
        ]);

        app(CartService::class)->addItem($user, $product->id, 2);

        $guest = $this->createGuestCart();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/guest/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])->assertOk();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cart');

        $userCart = $user->carts()->where('status', 'active')->firstOrFail();

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $userCart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('carts', [
            'id' => $guest['cart_id'],
            'status' => 'active',
            'guest_token_hash' => hash('sha256', $guest['token']),
        ]);
    }

    public function test_merge_fails_for_inactive_product(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $product = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
        ]);

        $guest = $this->createGuestCart();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/guest/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])->assertOk();

        $product->update(['is_active' => false]);

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cart');

        $this->assertDatabaseHas('carts', [
            'id' => $guest['cart_id'],
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $guest['cart_id'],
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    public function test_merge_rejects_malformed_guest_token(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->withHeader('X-Guest-Cart-Token', 'token-invalido')
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('guest_cart_token');
    }

    public function test_merge_rejects_unknown_guest_token(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->withHeader(
            'X-Guest-Cart-Token',
            str_repeat('A', 64)
        )->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertNotFound();
    }

    public function test_invalid_credentials_do_not_merge_cart(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $guest = $this->createGuestCart();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'senha-incorreta',
            ])
            ->assertUnauthorized();

        $this->assertDatabaseHas('carts', [
            'id' => $guest['cart_id'],
            'status' => 'active',
            'guest_token_hash' => hash('sha256', $guest['token']),
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_merged_guest_token_cannot_be_reused(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $guest = $this->createGuestCart();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])->assertOk();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->getJson('/api/guest/cart')
            ->assertNotFound();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])->assertNotFound();
    }

    public function test_merge_preserves_different_products(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $productA = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
        ]);

        $productB = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
        ]);

        app(CartService::class)->addItem($user, $productA->id, 2);

        $guest = $this->createGuestCart();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/guest/cart/items', [
                'product_id' => $productB->id,
                'quantity' => 3,
            ])->assertOk();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])->assertOk();

        $cart = $user->carts()->where('status', 'active')->firstOrFail();

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $productA->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $productB->id,
            'quantity' => 3,
        ]);

        $this->assertSame(2, $cart->items()->count());
    }

    public function test_empty_guest_cart_can_be_merged(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $guest = $this->createGuestCart();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])->assertOk();

        $this->assertDatabaseHas('carts', [
            'id' => $guest['cart_id'],
            'status' => 'merged',
            'guest_token_hash' => null,
        ]);

        $this->assertSame(
            1,
            $user->carts()->where('status', 'active')->count()
        );
    }

    public function test_failed_merge_does_not_partially_transfer_items(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $productA = Product::factory()->create([
            'is_active' => true,
            'stock' => 10,
        ]);

        $productB = Product::factory()->create([
            'is_active' => true,
            'stock' => 2,
        ]);

        $guest = $this->createGuestCart();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/guest/cart/items', [
                'product_id' => $productA->id,
                'quantity' => 2,
            ])->assertOk();

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/guest/cart/items', [
                'product_id' => $productB->id,
                'quantity' => 2,
            ])->assertOk();

        $productB->update(['stock' => 1]);

        $this->withHeader('X-Guest-Cart-Token', $guest['token'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'password123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cart');

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $guest['cart_id'],
            'product_id' => $productA->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $guest['cart_id'],
            'product_id' => $productB->id,
            'quantity' => 2,
        ]);

        $this->assertSame(
            0,
            \App\Models\CartItem::query()
                ->whereHas('cart', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->count()
        );
    }

}

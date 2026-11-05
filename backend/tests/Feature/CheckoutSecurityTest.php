<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\AddressService;
use App\Services\CartMergeService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\GuestCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cart.max_quantity_per_item', 20);
    }

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->role = 'customer';
        $user->save();

        return $user;
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'price_cents' => 2500,
            'stock' => 100,
            'is_active' => true,
        ]);
    }

    private function address(User $user): int
    {
        return app(AddressService::class)->create(
            $user,
            [
                'recipient_name' => 'Cliente PetGo',
                'phone' => '41999999999',
                'postal_code' => '85010-000',
                'street' => 'Rua Original',
                'number' => '100',
                'complement' => null,
                'neighborhood' => 'Centro',
                'city' => 'Guarapuava',
                'state' => 'PR',
                'reference' => null,
            ]
        )->id;
    }

    private function cartQuantity(
        int $cartId,
        int $productId
    ): int {
        return (int) \App\Models\CartItem::query()
            ->where('cart_id', $cartId)
            ->where('product_id', $productId)
            ->value('quantity');
    }

    public function test_customer_can_add_exactly_twenty_units(): void
    {
        $user = $this->customer();
        $product = $this->product();

        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 20,
        ])->assertSuccessful();

        $cart = $user->carts()
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertSame(
            20,
            $this->cartQuantity($cart->id, $product->id)
        );
    }

    public function test_api_rejects_more_than_twenty_units(): void
    {
        $user = $this->customer();
        $product = $this->product();

        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 21,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_customer_cannot_exceed_limit_by_adding_twice(): void
    {
        $user = $this->customer();
        $product = $this->product();

        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 15,
        ])->assertSuccessful();

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 10,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $cart = $user->carts()
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertSame(
            15,
            $this->cartQuantity($cart->id, $product->id)
        );
    }

    public function test_service_rejects_updating_above_limit(): void
    {
        $user = $this->customer();
        $product = $this->product();

        $service = app(CartService::class);

        $cart = $service->addItem(
            $user,
            $product->id,
            5
        );

        $item = $cart->items()->firstOrFail();

        try {
            $service->updateItem(
                $user,
                $item->id,
                21
            );

            $this->fail(
                'O serviço deveria rejeitar 21 unidades.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'quantity',
                $exception->errors()
            );
        }

        $this->assertSame(
            5,
            (int) $item->fresh()->quantity
        );
    }

    public function test_service_rejects_negative_quantity(): void
    {
        $user = $this->customer();
        $product = $this->product();

        try {
            app(CartService::class)->addItem(
                $user,
                $product->id,
                -2
            );

            $this->fail(
                'O serviço deveria rejeitar quantidade negativa.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'quantity',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_guest_cart_respects_accumulated_limit(): void
    {
        $product = $this->product();

        $result = app(GuestCartService::class)->create();
        $guestCart = $result['cart'];

        $service = app(CartService::class);

        $service->addItemToCart(
            $guestCart,
            $product->id,
            15
        );

        try {
            $service->addItemToCart(
                $guestCart,
                $product->id,
                10
            );

            $this->fail(
                'O carrinho visitante ultrapassou o limite.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'quantity',
                $exception->errors()
            );
        }

        $this->assertSame(
            15,
            $this->cartQuantity(
                $guestCart->id,
                $product->id
            )
        );
    }

    public function test_merge_allows_exactly_twenty_units(): void
    {
        $user = $this->customer();
        $product = $this->product();

        $userCart = app(CartService::class)->addItem(
            $user,
            $product->id,
            10
        );

        $guest = app(GuestCartService::class)->create();

        app(CartService::class)->addItemToCart(
            $guest['cart'],
            $product->id,
            10
        );

        app(CartMergeService::class)->merge(
            $user,
            $guest['token']
        );

        $this->assertSame(
            20,
            $this->cartQuantity(
                $userCart->id,
                $product->id
            )
        );

        $this->assertSame(
            'merged',
            $guest['cart']->fresh()->status
        );
    }

    public function test_failed_merge_preserves_both_carts(): void
    {
        $user = $this->customer();
        $product = $this->product();

        $userCart = app(CartService::class)->addItem(
            $user,
            $product->id,
            10
        );

        $guest = app(GuestCartService::class)->create();
        $guestCart = $guest['cart'];

        app(CartService::class)->addItemToCart(
            $guestCart,
            $product->id,
            15
        );

        try {
            app(CartMergeService::class)->merge(
                $user,
                $guest['token']
            );

            $this->fail(
                'A mesclagem deveria rejeitar 25 unidades.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'cart',
                $exception->errors()
            );
        }

        $this->assertSame(
            10,
            $this->cartQuantity(
                $userCart->id,
                $product->id
            )
        );

        $this->assertSame(
            15,
            $this->cartQuantity(
                $guestCart->id,
                $product->id
            )
        );

        $this->assertSame(
            'active',
            $guestCart->fresh()->status
        );

        $this->assertNotNull(
            $guestCart->fresh()->guest_token_hash
        );
    }

    public function test_checkout_rejects_legacy_cart_above_limit(): void
    {
        $user = $this->customer();
        $product = $this->product();

        $addressId = $this->address($user);

        $cart = app(CartService::class)->getCart($user);

        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 21,
        ]);

        try {
            app(CheckoutService::class)->checkout(
                $user,
                $addressId,
                (string) Str::uuid()
            );

            $this->fail(
                'O checkout deveria rejeitar 21 unidades.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'cart',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount('orders', 0);

        $this->assertSame(
            100,
            (int) $product->fresh()->stock
        );

        $this->assertSame(
            'active',
            $cart->fresh()->status
        );
    }

    public function test_checkout_ignores_forged_price(): void
    {
        $user = $this->customer();
        $product = $this->product();

        $addressId = $this->address($user);

        app(CartService::class)->addItem(
            $user,
            $product->id,
            2
        );

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout', [
            'address_id' => $addressId,
            'checkout_key' => (string) Str::uuid(),
            'total_cents' => 1,
            'subtotal_cents' => 1,
        ])->assertCreated();

        $order = \App\Models\Order::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame(
            5000,
            (int) $order->subtotal_cents
        );

        $this->assertSame(
            5000,
            (int) $order->total_cents
        );

        $this->assertSame(
            98,
            (int) $product->fresh()->stock
        );
    }

    public function test_checkout_rate_limit_blocks_eleventh_request(): void
    {
        $user = $this->customer();

        Sanctum::actingAs($user);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/checkout', [])
                ->assertUnprocessable();
        }

        $this->postJson('/api/checkout', [])
            ->assertStatus(429);
    }
}

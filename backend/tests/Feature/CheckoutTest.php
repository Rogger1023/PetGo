<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function createAddress(User $user): Address
    {
        return $user->addresses()->create([
            'recipient_name' => 'Cliente Teste',
            'phone' => '41999999999',
            'postal_code' => '85000-000',
            'street' => 'Rua das Flores',
            'number' => '123',
            'complement' => 'Casa',
            'neighborhood' => 'Centro',
            'city' => 'Guarapuava',
            'state' => 'PR',
            'reference' => 'Próximo à praça',
        ]);
    }

    private function createProduct(
        int $price = 1500,
        int $stock = 10
    ): Product {
        return Product::factory()->create([
            'price_cents' => $price,
            'stock' => $stock,
            'is_active' => true,
        ]);
    }

    private function addToCart(
        User $user,
        Product $product,
        int $quantity
    ): void {
        app(CartService::class)->addItem(
            $user,
            $product->id,
            $quantity
        );
    }

    private function checkoutData(
        Address $address,
        ?string $key = null
    ): array {
        return [
            'address_id' => $address->id,
            'checkout_key' => $key ?? (string) Str::uuid(),
        ];
    }

    public function test_checkout_creates_order_items_and_payment(): void
    {
        $user = User::factory()->create();
        $address = $this->createAddress($user);
        $product = $this->createProduct(2500, 10);

        $this->addToCart($user, $product, 2);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/checkout',
            $this->checkoutData($address)
        )->assertCreated();

        $order = Order::query()->firstOrFail();

        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame(5000, $order->subtotal_cents);
        $this->assertSame(0, $order->shipping_cents);
        $this->assertSame(5000, $order->total_cents);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price_cents' => 2500,
            'line_total_cents' => 5000,
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $this->assertSame(8, $product->fresh()->stock);

        $this->assertSame(
            'checked_out',
            $user->carts()->firstOrFail()->status
        );
    }

    public function test_checkout_calculates_prices_on_backend(): void
    {
        $user = User::factory()->create();
        $address = $this->createAddress($user);

        $productA = $this->createProduct(1250, 10);
        $productB = $this->createProduct(3200, 10);

        $this->addToCart($user, $productA, 2);
        $this->addToCart($user, $productB, 1);

        Sanctum::actingAs($user);

        $data = $this->checkoutData($address);

        // Campos extras não devem controlar o valor do pedido.
        $data['total_cents'] = 1;
        $data['shipping_cents'] = 999999;

        $this->postJson('/api/checkout', $data)
            ->assertCreated();

        $order = Order::query()->firstOrFail();

        // (1250 * 2) + 3200 = 5700.
        $this->assertSame(5700, $order->subtotal_cents);
        $this->assertSame(0, $order->shipping_cents);
        $this->assertSame(5700, $order->total_cents);

        $this->assertSame(2, $order->items()->count());
    }

    public function test_checkout_rejects_empty_cart(): void
    {
        $user = User::factory()->create();
        $address = $this->createAddress($user);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/checkout',
            $this->checkoutData($address)
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_rejects_insufficient_stock(): void
    {
        $user = User::factory()->create();
        $address = $this->createAddress($user);
        $product = $this->createProduct(1000, 5);

        $this->addToCart($user, $product, 4);

        // Estoque alterado depois de adicionar ao carrinho.
        $product->update(['stock' => 2]);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/checkout',
            $this->checkoutData($address)
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertSame(
            'active',
            $user->carts()->firstOrFail()->status
        );
    }

    public function test_checkout_rejects_inactive_product(): void
    {
        $user = User::factory()->create();
        $address = $this->createAddress($user);
        $product = $this->createProduct();

        $this->addToCart($user, $product, 1);

        $product->update(['is_active' => false]);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/checkout',
            $this->checkoutData($address)
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_checkout_rejects_another_users_address(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $address = $this->createAddress($otherUser);

        $product = $this->createProduct();
        $this->addToCart($user, $product, 1);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/checkout',
            $this->checkoutData($address)
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address_id');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_requires_authentication(): void
    {
        $this->postJson('/api/checkout', [
            'address_id' => 1,
            'checkout_key' => (string) Str::uuid(),
        ])->assertUnauthorized();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_is_idempotent(): void
    {
        $user = User::factory()->create();
        $address = $this->createAddress($user);
        $product = $this->createProduct(1000, 10);

        $this->addToCart($user, $product, 2);

        Sanctum::actingAs($user);

        $data = $this->checkoutData($address);

        $this->postJson('/api/checkout', $data)
            ->assertCreated();

        $firstOrder = Order::query()->firstOrFail();

        // Repete a mesma operação com a mesma chave.
        $this->postJson('/api/checkout', $data)
            ->assertCreated();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payments', 1);

        $this->assertSame(
            $firstOrder->id,
            Order::query()->firstOrFail()->id
        );

        // Estoque deve ser descontado somente uma vez.
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_checkout_key_cannot_be_used_by_another_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $addressA = $this->createAddress($userA);
        $addressB = $this->createAddress($userB);

        $product = $this->createProduct(1000, 10);

        $this->addToCart($userA, $product, 1);
        $this->addToCart($userB, $product, 1);

        $key = (string) Str::uuid();

        Sanctum::actingAs($userA);

        $this->postJson('/api/checkout', [
            'address_id' => $addressA->id,
            'checkout_key' => $key,
        ])->assertCreated();

        Sanctum::actingAs($userB);

        $this->postJson('/api/checkout', [
            'address_id' => $addressB->id,
            'checkout_key' => $key,
        ])->assertStatus(409);

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_failed_checkout_rolls_back_all_changes(): void
    {
        $user = User::factory()->create();
        $address = $this->createAddress($user);

        $productA = $this->createProduct(1000, 10);
        $productB = $this->createProduct(2000, 5);

        $this->addToCart($user, $productA, 2);
        $this->addToCart($user, $productB, 4);

        // Segundo produto fica sem estoque suficiente.
        $productB->update(['stock' => 1]);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/checkout',
            $this->checkoutData($address)
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payments', 0);

        $this->assertSame(10, $productA->fresh()->stock);
        $this->assertSame(1, $productB->fresh()->stock);

        $this->assertSame(
            'active',
            $user->carts()->firstOrFail()->status
        );
    }

    public function test_order_preserves_product_and_address_snapshots(): void
    {
        $user = User::factory()->create();
        $address = $this->createAddress($user);
        $product = $this->createProduct(3500, 10);

        $originalProductName = $product->name;

        $this->addToCart($user, $product, 1);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/checkout',
            $this->checkoutData($address)
        )->assertCreated();

        $order = Order::query()->firstOrFail();

        $product->update(['name' => 'Nome atualizado']);
        $address->update(['street' => 'Outra rua']);

        $this->assertSame(
            $originalProductName,
            $order->items()->firstOrFail()->product_name
        );

        $this->assertSame(
            'Rua das Flores',
            $order->fresh()->address_snapshot['street']
        );

        $this->assertSame(
            3500,
            $order->items()->firstOrFail()->unit_price_cents
        );
    }

    public function test_checkout_closes_cart_and_allows_new_cart(): void
    {
        $user = User::factory()->create();
        $address = $this->createAddress($user);
        $product = $this->createProduct();

        $this->addToCart($user, $product, 1);

        $oldCart = $user->carts()
            ->where('status', 'active')
            ->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/checkout',
            $this->checkoutData($address)
        )->assertCreated();

        $this->assertSame(
            'checked_out',
            $oldCart->fresh()->status
        );

        $newCart = app(CartService::class)->getCart($user);

        $this->assertNotEquals($oldCart->id, $newCart->id);
        $this->assertSame('active', $newCart->status);
        $this->assertSame(0, $newCart->items()->count());
    }
}

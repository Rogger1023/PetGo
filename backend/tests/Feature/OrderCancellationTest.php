<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderCancellationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(
        int $stock = 10,
        int $price = 2500
    ): Product {
        return Product::factory()->create([
            'stock' => $stock,
            'price_cents' => $price,
            'is_active' => true,
        ]);
    }

    private function createOrder(
        User $user,
        array $products
    ): Order {
        $address = $user->addresses()->create([
            'recipient_name' => 'Cliente Teste',
            'phone' => '41999999999',
            'postal_code' => '85000-000',
            'street' => 'Rua Teste',
            'number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Guarapuava',
            'state' => 'PR',
        ]);

        $cartService = app(CartService::class);

        foreach ($products as [$product, $quantity]) {
            $cartService->addItem(
                $user,
                $product->id,
                $quantity
            );
        }

        return app(CheckoutService::class)->checkout(
            $user,
            $address->id,
            (string) Str::uuid()
        );
    }

    public function test_customer_cancels_pending_order_and_restores_stock(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(10);

        $order = $this->createOrder($user, [
            [$product, 3],
        ]);

        $this->assertSame(7, $product->fresh()->stock);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.payment.status', 'cancelled');

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('cancelled', $order->payment->fresh()->status);
    }

    public function test_repeated_cancellation_does_not_restore_stock_twice(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(10);

        $order = $this->createOrder($user, [
            [$product, 2],
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk();

        $this->assertSame(10, $product->fresh()->stock);

        // Repete a solicitação.
        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_cancellation_requires_authentication(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $order = $this->createOrder($user, [
            [$product, 2],
        ]);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertUnauthorized();

        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_customer_cannot_cancel_another_users_order(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $product = $this->createProduct();

        $order = $this->createOrder($owner, [
            [$product, 2],
        ]);

        Sanctum::actingAs($otherUser);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertNotFound();

        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_cannot_cancel_nonexistent_order(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/orders/999999/cancel')
            ->assertNotFound();
    }

    public function test_cannot_cancel_order_after_payment_is_paid(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $order = $this->createOrder($user, [
            [$product, 2],
        ]);

        $payment = $order->payment;
        $payment->status = 'paid';
        $payment->paid_at = now();
        $payment->save();

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(409);

        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_cannot_cancel_when_payment_has_paid_at(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $order = $this->createOrder($user, [
            [$product, 1],
        ]);

        // Mesmo com status pending, paid_at preenchido
        // deve impedir o cancelamento.
        $payment = $order->payment;
        $payment->paid_at = now();
        $payment->save();

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(409);

        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_cannot_cancel_order_in_another_status(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $order = $this->createOrder($user, [
            [$product, 2],
        ]);

        $order->status = 'shipped';
        $order->save();

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(409);

        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_cannot_cancel_order_without_payment(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $order = $this->createOrder($user, [
            [$product, 2],
        ]);

        $order->payment()->delete();

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(409);

        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_cancellation_restores_multiple_products(): void
    {
        $user = User::factory()->create();

        $productA = $this->createProduct(10, 1000);
        $productB = $this->createProduct(20, 2000);

        $order = $this->createOrder($user, [
            [$productA, 3],
            [$productB, 5],
        ]);

        $this->assertSame(7, $productA->fresh()->stock);
        $this->assertSame(15, $productB->fresh()->stock);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk();

        $this->assertSame(10, $productA->fresh()->stock);
        $this->assertSame(20, $productB->fresh()->stock);

        $this->assertSame(2, $order->items()->count());
    }

    public function test_missing_product_reference_does_not_block_cancellation(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(10);

        $order = $this->createOrder($user, [
            [$product, 2],
        ]);

        // Simula um item cujo produto não está mais vinculado
        // ao catálogo, como permite a migration order_items.
        $item = $order->items()->firstOrFail();
        $item->product()->dissociate();
        $item->save();

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // Não há referência para devolver o estoque.
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame('cancelled', $order->payment->fresh()->status);
    }

    public function test_cancellation_does_not_reopen_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct();

        $order = $this->createOrder($user, [
            [$product, 1],
        ]);

        $cart = $user->carts()
            ->where('status', 'checked_out')
            ->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk();

        $this->assertSame('checked_out', $cart->fresh()->status);

        $this->assertSame(
            0,
            $user->carts()->where('status', 'active')->count()
        );
    }

    public function test_failed_stock_restoration_rolls_back_everything(): void
    {
        $user = User::factory()->create();

        $productA = $this->createProduct(10);
        $productB = $this->createProduct(10);

        $order = $this->createOrder($user, [
            [$productA, 2],
            [$productB, 3],
        ]);

        $this->assertSame(8, $productA->fresh()->stock);
        $this->assertSame(7, $productB->fresh()->stock);

        // Provoca uma falha no SQLite ao tentar restaurar
        // o estoque do segundo produto.
        DB::unprepared("
            CREATE TEMP TRIGGER fail_stock_restoration
            BEFORE UPDATE OF stock ON products
            WHEN NEW.id = {$productB->id}
                 AND NEW.stock > OLD.stock
            BEGIN
                SELECT RAISE(ABORT, 'forced stock error');
            END;
        ");

        $exceptionCaught = false;

        try {
            app(OrderCancellationService::class)->cancel(
                $user,
                $order->id
            );
        } catch (QueryException $exception) {
            $exceptionCaught = true;
        } finally {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS fail_stock_restoration'
            );
        }

        $this->assertTrue(
            $exceptionCaught,
            'O cancelamento deveria falhar ao restaurar o estoque.'
        );

        // Nenhuma atualização de estoque pode permanecer.
        $this->assertSame(8, $productA->fresh()->stock);
        $this->assertSame(7, $productB->fresh()->stock);

        // O pedido e o pagamento devem permanecer pendentes.
        $this->assertSame(
            'pending_payment',
            $order->fresh()->status
        );

        $this->assertSame(
            'pending',
            $order->payment->fresh()->status
        );
    }
}

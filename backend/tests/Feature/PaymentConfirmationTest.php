<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderCancellationService;
use App\Services\PaymentConfirmationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $admin->role = 'admin';
        $admin->save();

        return $admin;
    }

    private function createCustomer(): User
    {
        $user = User::factory()->create();

        $user->role = 'customer';
        $user->save();

        return $user;
    }

    private function createOrder(User $user): Order
    {
        $order = new Order();

        $order->user()->associate($user);
        $order->status = 'pending_payment';
        $order->subtotal_cents = 5000;
        $order->shipping_cents = 0;
        $order->total_cents = 5000;
        $order->checkout_key = (string) Str::uuid();

        $order->address_snapshot = [
            'recipient_name' => 'Cliente Teste',
            'phone' => '41999999999',
            'postal_code' => '85000-000',
            'street' => 'Rua Teste',
            'number' => '100',
            'neighborhood' => 'Centro',
            'city' => 'Guarapuava',
            'state' => 'PR',
        ];

        $order->save();

        $item = new OrderItem();
        $item->order()->associate($order);
        $item->product_name = 'Produto PetGo';
        $item->quantity = 2;
        $item->unit_price_cents = 2500;
        $item->line_total_cents = 5000;
        $item->save();

        $payment = new Payment();
        $payment->order()->associate($order);
        $payment->status = 'pending';
        $payment->save();

        return $order;
    }

    private function createOrderThroughCheckout(
        User $user
    ): array {
        $product = Product::factory()->create([
            'price_cents' => 2500,
            'stock' => 10,
            'is_active' => true,
        ]);

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

        app(CartService::class)->addItem(
            $user,
            $product->id,
            2
        );

        $order = app(CheckoutService::class)->checkout(
            $user,
            $address->id,
            (string) Str::uuid()
        );

        return [$order, $product];
    }

    public function test_admin_confirms_pending_payment(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        [$order, $product] =
            $this->createOrderThroughCheckout($customer);

        $this->assertSame(8, $product->fresh()->stock);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath(
                'data.user.email',
                $customer->email
            );

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );

        $payment = $order->payment->fresh();

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);

        // A confirmação não deve descontar estoque novamente.
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_confirmation_is_idempotent(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )->assertOk();

        $firstPaidAt = $order->payment
            ->fresh()
            ->paid_at
            ->toDateTimeString();

        // Repete exatamente a mesma confirmação.
        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.payment.status', 'paid');

        $secondPaidAt = $order->payment
            ->fresh()
            ->paid_at
            ->toDateTimeString();

        $this->assertSame($firstPaidAt, $secondPaidAt);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_cannot_confirm_cancelled_order(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        [$order, $product] =
            $this->createOrderThroughCheckout($customer);

        app(OrderCancellationService::class)->cancel(
            $customer,
            $order->id
        );

        $this->assertSame(10, $product->fresh()->stock);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )->assertStatus(409);

        $this->assertSame(
            'cancelled',
            $order->fresh()->status
        );

        $this->assertSame(
            'cancelled',
            $order->payment->fresh()->status
        );

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_cannot_confirm_shipped_order(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        $order->status = 'shipped';
        $order->save();

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )->assertStatus(409);

        $this->assertSame('shipped', $order->fresh()->status);

        $this->assertSame(
            'pending',
            $order->payment->fresh()->status
        );
    }

    public function test_cannot_confirm_order_without_payment(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        $order->payment()->delete();

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )->assertStatus(409);

        $this->assertSame(
            'pending_payment',
            $order->fresh()->status
        );

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cannot_confirm_when_payment_is_already_paid(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        // Simula um estado inconsistente.
        $payment = $order->payment;
        $payment->status = 'paid';
        $payment->paid_at = now();
        $payment->save();

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )->assertStatus(409);

        $this->assertSame(
            'pending_payment',
            $order->fresh()->status
        );

        $this->assertSame(
            'paid',
            $payment->fresh()->status
        );
    }

    public function test_cannot_confirm_when_paid_at_is_already_set(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        $payment = $order->payment;
        $payment->paid_at = now();
        $payment->save();

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )->assertStatus(409);

        $this->assertSame(
            'pending_payment',
            $order->fresh()->status
        );

        $this->assertSame(
            'pending',
            $payment->fresh()->status
        );

        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_cannot_confirm_inconsistent_processing_order(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        // Pedido em processamento, mas pagamento pendente.
        $order->status = 'processing';
        $order->save();

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )->assertStatus(409);

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );

        $this->assertSame(
            'pending',
            $order->payment->fresh()->status
        );
    }

    public function test_cannot_confirm_failed_payment(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        $payment = $order->payment;
        $payment->status = 'failed';
        $payment->save();

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )->assertStatus(409);

        $this->assertSame(
            'pending_payment',
            $order->fresh()->status
        );

        $this->assertSame(
            'failed',
            $payment->fresh()->status
        );
    }

    public function test_cannot_confirm_nonexistent_order(): void
    {
        $admin = $this->createAdmin();

        Sanctum::actingAs($admin);

        $this->postJson(
            '/api/admin/orders/999999/confirm-payment'
        )->assertNotFound();
    }

    public function test_customer_cannot_confirm_payment(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        Sanctum::actingAs($customer);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )->assertForbidden();

        $this->assertSame(
            'pending_payment',
            $order->fresh()->status
        );

        $this->assertSame(
            'pending',
            $order->payment->fresh()->status
        );
    }

    public function test_confirmation_requires_authentication(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )->assertUnauthorized();

        $this->assertSame(
            'pending_payment',
            $order->fresh()->status
        );

        $this->assertSame(
            'pending',
            $order->payment->fresh()->status
        );
    }

    public function test_confirmation_does_not_modify_other_orders(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $orderA = $this->createOrder($customer);
        $orderB = $this->createOrder($customer);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/orders/{$orderA->id}/confirm-payment"
        )->assertOk();

        $this->assertSame(
            'processing',
            $orderA->fresh()->status
        );

        $this->assertSame(
            'paid',
            $orderA->payment->fresh()->status
        );

        $this->assertSame(
            'pending_payment',
            $orderB->fresh()->status
        );

        $this->assertSame(
            'pending',
            $orderB->payment->fresh()->status
        );

        $this->assertNull(
            $orderB->payment->fresh()->paid_at
        );
    }

    public function test_failed_order_update_rolls_back_payment(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        // Simula uma falha ao atualizar o pedido depois
        // de o pagamento ter sido marcado como paid.
        DB::unprepared("
            CREATE TEMP TRIGGER fail_order_confirmation
            BEFORE UPDATE OF status ON orders
            WHEN NEW.id = {$order->id}
                 AND NEW.status = 'processing'
            BEGIN
                SELECT RAISE(
                    ABORT,
                    'forced order update error'
                );
            END;
        ");

        $exceptionCaught = false;

        try {
            app(PaymentConfirmationService::class)->confirm(
                $order->id
            );
        } catch (QueryException $exception) {
            $exceptionCaught = true;
        } finally {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS fail_order_confirmation'
            );
        }

        $this->assertTrue(
            $exceptionCaught,
            'A confirmação deveria falhar ao atualizar o pedido.'
        );

        // O pedido não pode ficar em processamento.
        $this->assertSame(
            'pending_payment',
            $order->fresh()->status
        );

        // O pagamento deve voltar ao estado anterior.
        $payment = $order->payment->fresh();

        $this->assertSame('pending', $payment->status);
        $this->assertNull($payment->paid_at);
    }
}

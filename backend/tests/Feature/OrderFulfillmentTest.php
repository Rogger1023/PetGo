<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderFulfillmentService;
use App\Services\PaymentConfirmationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $user->role = 'admin';
        $user->save();

        return $user;
    }

    private function createCustomer(): User
    {
        $user = User::factory()->create();
        $user->role = 'customer';
        $user->save();

        return $user;
    }

    private function createOrder(
        User $user,
        string $status = 'processing',
        string $paymentStatus = 'paid',
        bool $hasPaidAt = true
    ): Order {
        $order = new Order();

        $order->user()->associate($user);
        $order->status = $status;
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
        $payment->status = $paymentStatus;
        $payment->paid_at = $hasPaidAt ? now() : null;
        $payment->save();

        return $order;
    }

    private function endpoint(Order $order): string
    {
        return "/api/admin/orders/{$order->id}/status";
    }

    public function test_admin_marks_processing_order_as_shipped(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        $paidAt = $order->payment
            ->paid_at
            ->toDateTimeString();

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.user.email', $customer->email);

        $this->assertSame('shipped', $order->fresh()->status);

        $this->assertSame(
            $paidAt,
            $order->payment->fresh()->paid_at->toDateTimeString()
        );
    }

    public function test_admin_marks_shipped_order_as_delivered(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            $customer,
            'shipped'
        );

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'delivered',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $this->assertSame(
            'delivered',
            $order->fresh()->status
        );
    }

    public function test_admin_completes_full_fulfillment_flow(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])->assertOk();

        $this->patchJson($this->endpoint($order), [
            'status' => 'delivered',
        ])->assertOk();

        $this->assertSame(
            'delivered',
            $order->fresh()->status
        );

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);

        $this->assertSame(
            'paid',
            $order->payment->fresh()->status
        );
    }

    public function test_repeated_shipped_update_is_idempotent(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            $customer,
            'shipped'
        );

        // Define uma data antiga para detectar atualizações.
        DB::table('orders')
            ->where('id', $order->id)
            ->update([
                'updated_at' => '2026-01-01 12:00:00',
            ]);

        $updatedAt = $order->fresh()
            ->updated_at
            ->toDateTimeString();

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped');

        $this->assertSame(
            $updatedAt,
            $order->fresh()->updated_at->toDateTimeString()
        );
    }

    public function test_repeated_delivered_update_is_idempotent(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            $customer,
            'delivered'
        );

        DB::table('orders')
            ->where('id', $order->id)
            ->update([
                'updated_at' => '2026-01-01 12:00:00',
            ]);

        $updatedAt = $order->fresh()
            ->updated_at
            ->toDateTimeString();

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'delivered',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $this->assertSame(
            $updatedAt,
            $order->fresh()->updated_at->toDateTimeString()
        );
    }

    public function test_cannot_skip_from_processing_to_delivered(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'delivered',
        ])->assertStatus(409);

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );
    }

    public function test_cannot_move_delivered_order_back_to_shipped(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            $customer,
            'delivered'
        );

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])->assertStatus(409);

        $this->assertSame(
            'delivered',
            $order->fresh()->status
        );
    }

    public function test_invalid_target_status_returns_422(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        Sanctum::actingAs($admin);

        foreach (['processing', 'cancelled', 'invalid'] as $status) {
            $this->patchJson($this->endpoint($order), [
                'status' => $status,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        }

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );
    }

    public function test_missing_status_returns_422(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        Sanctum::actingAs($admin);

        $this->patchJson(
            $this->endpoint($order),
            []
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_fulfillment_requires_authentication(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])->assertUnauthorized();

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );
    }

    public function test_customer_cannot_update_order_status(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        Sanctum::actingAs($customer);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])->assertForbidden();

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );
    }

    public function test_nonexistent_order_returns_404(): void
    {
        $admin = $this->createAdmin();

        Sanctum::actingAs($admin);

        $this->patchJson(
            '/api/admin/orders/999999/status',
            ['status' => 'shipped']
        )->assertNotFound();
    }

    public function test_cannot_advance_pending_payment_order(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            $customer,
            'pending_payment',
            'pending',
            false
        );

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])->assertStatus(409);

        $this->assertSame(
            'pending_payment',
            $order->fresh()->status
        );

        $this->assertSame(
            'pending',
            $order->payment->fresh()->status
        );
    }

    public function test_processing_order_requires_paid_payment(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            $customer,
            'processing',
            'pending',
            false
        );

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])->assertStatus(409);

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );
    }

    public function test_cannot_advance_without_paid_at(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            $customer,
            'processing',
            'paid',
            false
        );

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])->assertStatus(409);

        $this->assertNull(
            $order->payment->fresh()->paid_at
        );
    }

    public function test_cannot_advance_order_without_payment(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        $order->payment()->delete();

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])->assertStatus(409);

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cancelled_order_cannot_advance(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $order = $this->createOrder(
            $customer,
            'cancelled'
        );

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])->assertStatus(409);

        $this->assertSame(
            'cancelled',
            $order->fresh()->status
        );
    }

    public function test_fulfillment_does_not_modify_other_orders(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $orderA = $this->createOrder($customer);
        $orderB = $this->createOrder($customer);

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($orderA), [
            'status' => 'shipped',
        ])->assertOk();

        $this->assertSame(
            'shipped',
            $orderA->fresh()->status
        );

        $this->assertSame(
            'processing',
            $orderB->fresh()->status
        );

        $this->assertSame(
            'paid',
            $orderB->payment->fresh()->status
        );
    }

    public function test_fulfillment_does_not_change_stock(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $product = Product::factory()->create([
            'stock' => 10,
            'price_cents' => 2500,
            'is_active' => true,
        ]);

        $address = $customer->addresses()->create([
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
            $customer,
            $product->id,
            2
        );

        $order = app(CheckoutService::class)->checkout(
            $customer,
            $address->id,
            (string) Str::uuid()
        );

        app(PaymentConfirmationService::class)->confirm(
            $order->id
        );

        $this->assertSame(8, $product->fresh()->stock);

        Sanctum::actingAs($admin);

        $this->patchJson($this->endpoint($order), [
            'status' => 'shipped',
        ])->assertOk();

        $this->patchJson($this->endpoint($order), [
            'status' => 'delivered',
        ])->assertOk();

        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('paid', $order->payment->fresh()->status);
    }

    public function test_failed_status_update_rolls_back(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createOrder($customer);

        // Provoca erro no banco durante a atualização.
        DB::unprepared("
            CREATE TEMP TRIGGER fail_fulfillment_update
            BEFORE UPDATE OF status ON orders
            WHEN NEW.id = {$order->id}
                 AND NEW.status = 'shipped'
            BEGIN
                SELECT RAISE(
                    ABORT,
                    'forced fulfillment error'
                );
            END;
        ");

        $exceptionCaught = false;

        try {
            app(OrderFulfillmentService::class)->advance(
                $order->id,
                'shipped'
            );
        } catch (QueryException $exception) {
            $exceptionCaught = true;
        } finally {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS fail_fulfillment_update'
            );
        }

        $this->assertTrue(
            $exceptionCaught,
            'A atualização deveria falhar no banco.'
        );

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );

        $this->assertSame(
            'paid',
            $order->payment->fresh()->status
        );

        $this->assertNotNull(
            $order->payment->fresh()->paid_at
        );
    }
}

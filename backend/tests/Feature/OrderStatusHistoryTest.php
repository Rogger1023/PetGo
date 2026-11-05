<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderCancellationService;
use App\Services\OrderFulfillmentService;
use App\Services\PaymentConfirmationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class OrderStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $admin->role = 'admin';
        $admin->save();

        return $admin;
    }

    private function createCartContext(): array
    {
        $customer = User::factory()->create();

        $product = Product::factory()->create([
            'price_cents' => 2500,
            'stock' => 10,
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

        return [$customer, $product, $address];
    }

    private function checkoutOrder(): array
    {
        [$customer, $product, $address] =
            $this->createCartContext();

        $order = app(CheckoutService::class)->checkout(
            $customer,
            $address->id,
            (string) Str::uuid()
        );

        return [$customer, $order, $product];
    }

    public function test_checkout_creates_initial_history(): void
    {
        [$customer, $order] = $this->checkoutOrder();

        $this->assertDatabaseHas(
            'order_status_histories',
            [
                'order_id' => $order->id,
                'actor_user_id' => $customer->id,
                'from_status' => null,
                'to_status' => 'pending_payment',
                'source' => 'checkout',
            ]
        );

        $this->assertSame(
            1,
            $order->statusHistories()->count()
        );
    }

    public function test_history_has_order_and_actor_relationships(): void
    {
        [$customer, $order] = $this->checkoutOrder();

        $history = $order->statusHistories()
            ->firstOrFail();

        $this->assertSame(
            $order->id,
            $history->order->id
        );

        $this->assertSame(
            $customer->id,
            $history->actor->id
        );

        $this->assertNotNull($history->created_at);
    }

    public function test_payment_confirmation_records_admin(): void
    {
        $admin = $this->createAdmin();

        [, $order] = $this->checkoutOrder();

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/admin/orders/{$order->id}/confirm-payment"
        )->assertOk();

        $this->assertDatabaseHas(
            'order_status_histories',
            [
                'order_id' => $order->id,
                'actor_user_id' => $admin->id,
                'from_status' => 'pending_payment',
                'to_status' => 'processing',
                'source' => 'payment_confirmation',
            ]
        );

        $this->assertSame(
            2,
            $order->statusHistories()->count()
        );
    }

    public function test_repeated_payment_does_not_duplicate_history(): void
    {
        $admin = $this->createAdmin();

        [, $order] = $this->checkoutOrder();

        Sanctum::actingAs($admin);

        $endpoint =
            "/api/admin/orders/{$order->id}/confirm-payment";

        $this->postJson($endpoint)->assertOk();
        $this->postJson($endpoint)->assertOk();

        $this->assertSame(
            2,
            $order->statusHistories()->count()
        );

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );
    }

    public function test_cancellation_records_customer_and_restores_stock(): void
    {
        [$customer, $order, $product] =
            $this->checkoutOrder();

        $this->assertSame(
            8,
            $product->fresh()->stock
        );

        Sanctum::actingAs($customer);

        $this->postJson(
            "/api/orders/{$order->id}/cancel"
        )->assertOk();

        $this->assertDatabaseHas(
            'order_status_histories',
            [
                'order_id' => $order->id,
                'actor_user_id' => $customer->id,
                'from_status' => 'pending_payment',
                'to_status' => 'cancelled',
                'source' => 'order_cancellation',
            ]
        );

        $this->assertSame(
            2,
            $order->statusHistories()->count()
        );

        $this->assertSame(
            10,
            $product->fresh()->stock
        );
    }

    public function test_repeated_cancellation_does_not_duplicate_history(): void
    {
        [$customer, $order, $product] =
            $this->checkoutOrder();

        Sanctum::actingAs($customer);

        $endpoint = "/api/orders/{$order->id}/cancel";

        $this->postJson($endpoint)->assertOk();
        $this->postJson($endpoint)->assertOk();

        $this->assertSame(
            2,
            $order->statusHistories()->count()
        );

        $this->assertSame(
            10,
            $product->fresh()->stock
        );
    }

    public function test_shipping_and_delivery_record_admin(): void
    {
        $admin = $this->createAdmin();

        [, $order] = $this->checkoutOrder();

        app(PaymentConfirmationService::class)->confirm(
            $order->id,
            $admin
        );

        Sanctum::actingAs($admin);

        $endpoint = "/api/admin/orders/{$order->id}/status";

        $this->patchJson($endpoint, [
            'status' => 'shipped',
        ])->assertOk();

        $this->patchJson($endpoint, [
            'status' => 'delivered',
        ])->assertOk();

        $histories = $order->statusHistories()
            ->orderBy('id')
            ->get();

        $this->assertSame(
            [
                'pending_payment',
                'processing',
                'shipped',
                'delivered',
            ],
            $histories->pluck('to_status')->all()
        );

        $this->assertSame(
            'processing',
            $histories[2]->from_status
        );

        $this->assertSame(
            'shipped',
            $histories[3]->from_status
        );

        $this->assertSame(
            $admin->id,
            $histories[2]->actor_user_id
        );

        $this->assertSame(
            $admin->id,
            $histories[3]->actor_user_id
        );

        $this->assertSame(
            'fulfillment',
            $histories[2]->source
        );

        $this->assertSame(
            'fulfillment',
            $histories[3]->source
        );
    }

    public function test_repeated_fulfillment_does_not_duplicate_history(): void
    {
        $admin = $this->createAdmin();

        [, $order] = $this->checkoutOrder();

        app(PaymentConfirmationService::class)->confirm(
            $order->id,
            $admin
        );

        $service = app(OrderFulfillmentService::class);

        $service->advance($order->id, 'shipped', $admin);
        $service->advance($order->id, 'shipped', $admin);

        $service->advance($order->id, 'delivered', $admin);
        $service->advance($order->id, 'delivered', $admin);

        $this->assertSame(
            4,
            $order->statusHistories()->count()
        );

        $this->assertSame(
            'delivered',
            $order->fresh()->status
        );
    }

    public function test_invalid_transition_does_not_create_history(): void
    {
        $admin = $this->createAdmin();

        [, $order] = $this->checkoutOrder();

        app(PaymentConfirmationService::class)->confirm(
            $order->id,
            $admin
        );

        $rejected = false;

        try {
            app(OrderFulfillmentService::class)->advance(
                $order->id,
                'delivered',
                $admin
            );
        } catch (ConflictHttpException $exception) {
            $rejected = true;
        }

        $this->assertTrue($rejected);

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );

        $this->assertSame(
            2,
            $order->statusHistories()->count()
        );
    }

    public function test_cancelled_order_cannot_generate_payment_history(): void
    {
        $admin = $this->createAdmin();

        [$customer, $order] = $this->checkoutOrder();

        app(OrderCancellationService::class)->cancel(
            $customer,
            $order->id
        );

        $rejected = false;

        try {
            app(PaymentConfirmationService::class)->confirm(
                $order->id,
                $admin
            );
        } catch (ConflictHttpException $exception) {
            $rejected = true;
        }

        $this->assertTrue($rejected);

        $this->assertSame(
            2,
            $order->statusHistories()->count()
        );

        $this->assertSame(
            'cancelled',
            $order->fresh()->status
        );
    }

    public function test_confirmation_without_actor_has_null_actor(): void
    {
        [, $order] = $this->checkoutOrder();

        app(PaymentConfirmationService::class)->confirm(
            $order->id
        );

        $history = $order->statusHistories()
            ->where('source', 'payment_confirmation')
            ->firstOrFail();

        $this->assertNull(
            $history->actor_user_id
        );

        $this->assertSame(
            'processing',
            $history->to_status
        );
    }

    public function test_failed_fulfillment_history_rolls_back_status(): void
    {
        $admin = $this->createAdmin();

        [, $order, $product] = $this->checkoutOrder();

        app(PaymentConfirmationService::class)->confirm(
            $order->id,
            $admin
        );

        // Trigger temporária: falha ao gravar histórico logístico.
        DB::unprepared("
            CREATE TEMP TRIGGER fail_fulfillment_audit
            BEFORE INSERT ON order_status_histories
            WHEN NEW.source = 'fulfillment'
            BEGIN
                SELECT RAISE(
                    ABORT,
                    'forced fulfillment audit error'
                );
            END;
        ");

        $exceptionCaught = false;

        try {
            app(OrderFulfillmentService::class)->advance(
                $order->id,
                'shipped',
                $admin
            );
        } catch (QueryException $exception) {
            $exceptionCaught = true;
        } finally {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS fail_fulfillment_audit'
            );
        }

        $this->assertTrue($exceptionCaught);

        $this->assertSame(
            'processing',
            $order->fresh()->status
        );

        $this->assertSame(
            2,
            $order->statusHistories()->count()
        );

        $this->assertSame(
            8,
            $product->fresh()->stock
        );
    }

    public function test_failed_checkout_history_rolls_back_order(): void
    {
        [$customer, $product, $address] =
            $this->createCartContext();

        DB::unprepared("
            CREATE TEMP TRIGGER fail_checkout_audit
            BEFORE INSERT ON order_status_histories
            WHEN NEW.source = 'checkout'
            BEGIN
                SELECT RAISE(
                    ABORT,
                    'forced checkout audit error'
                );
            END;
        ");

        $exceptionCaught = false;

        try {
            app(CheckoutService::class)->checkout(
                $customer,
                $address->id,
                (string) Str::uuid()
            );
        } catch (QueryException $exception) {
            $exceptionCaught = true;
        } finally {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS fail_checkout_audit'
            );
        }

        $this->assertTrue($exceptionCaught);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('order_status_histories', 0);

        // O estoque deve permanecer intacto.
        $this->assertSame(
            10,
            $product->fresh()->stock
        );
    }
}

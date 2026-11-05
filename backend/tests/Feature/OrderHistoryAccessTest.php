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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderHistoryAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(): User
    {
        $customer = User::factory()->create();

        $customer->role = 'customer';
        $customer->save();

        return $customer;
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $admin->role = 'admin';
        $admin->save();

        return $admin;
    }

    private function createOrder(): array
    {
        $customer = $this->createCustomer();

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

        $order = app(CheckoutService::class)->checkout(
            $customer,
            $address->id,
            (string) Str::uuid()
        );

        return [$customer, $order, $product];
    }

    private function createLegacyOrder(User $customer): Order
    {
        // Simula pedido criado antes da auditoria.
        $order = new Order();

        $order->user()->associate($customer);
        $order->status = 'pending_payment';
        $order->subtotal_cents = 5000;
        $order->shipping_cents = 0;
        $order->total_cents = 5000;
        $order->checkout_key = (string) Str::uuid();

        $order->address_snapshot = [
            'city' => 'Guarapuava',
            'state' => 'PR',
        ];

        $order->save();

        return $order;
    }

    private function customerEndpoint(Order $order): string
    {
        return "/api/orders/{$order->id}/history";
    }

    private function adminEndpoint(Order $order): string
    {
        return "/api/admin/orders/{$order->id}/history";
    }

    public function test_customer_can_view_own_complete_history(): void
    {
        [$customer, $order] = $this->createOrder();
        $admin = $this->createAdmin();

        app(PaymentConfirmationService::class)
            ->confirm($order->id, $admin);

        $fulfillment = app(OrderFulfillmentService::class);

        $fulfillment->advance(
            $order->id,
            'shipped',
            $admin
        );

        $fulfillment->advance(
            $order->id,
            'delivered',
            $admin
        );

        Sanctum::actingAs($customer);

        $this->getJson($this->customerEndpoint($order))
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath(
                'data.0.to_status',
                'pending_payment'
            )
            ->assertJsonPath(
                'data.1.to_status',
                'processing'
            )
            ->assertJsonPath(
                'data.2.to_status',
                'shipped'
            )
            ->assertJsonPath(
                'data.3.to_status',
                'delivered'
            );
    }

    public function test_customer_does_not_see_internal_audit_fields(): void
    {
        [$customer, $order] = $this->createOrder();
        $admin = $this->createAdmin();

        app(PaymentConfirmationService::class)
            ->confirm($order->id, $admin);

        Sanctum::actingAs($customer);

        $this->getJson($this->customerEndpoint($order))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.source')
            ->assertJsonMissingPath('data.0.actor')
            ->assertJsonMissingPath('data.1.source')
            ->assertJsonMissingPath('data.1.actor')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'from_status',
                        'to_status',
                        'created_at',
                    ],
                ],
            ]);
    }

    public function test_customer_can_view_cancelled_order_history(): void
    {
        [$customer, $order] = $this->createOrder();

        app(OrderCancellationService::class)->cancel(
            $customer,
            $order->id
        );

        Sanctum::actingAs($customer);

        $this->getJson($this->customerEndpoint($order))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.0.to_status',
                'pending_payment'
            )
            ->assertJsonPath(
                'data.1.to_status',
                'cancelled'
            )
            ->assertJsonPath(
                'data.1.from_status',
                'pending_payment'
            );
    }

    public function test_customer_cannot_view_another_users_history(): void
    {
        [$owner, $order] = $this->createOrder();

        $anotherCustomer = $this->createCustomer();

        Sanctum::actingAs($anotherCustomer);

        $this->getJson($this->customerEndpoint($order))
            ->assertNotFound();

        $this->assertNotEquals(
            $owner->id,
            $anotherCustomer->id
        );
    }

    public function test_customer_history_requires_authentication(): void
    {
        [, $order] = $this->createOrder();

        $this->getJson($this->customerEndpoint($order))
            ->assertUnauthorized();
    }

    public function test_customer_history_of_nonexistent_order_returns_404(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $this->getJson(
            '/api/orders/999999/history'
        )->assertNotFound();
    }

    public function test_legacy_order_returns_empty_history(): void
    {
        $customer = $this->createCustomer();
        $order = $this->createLegacyOrder($customer);

        Sanctum::actingAs($customer);

        $this->getJson($this->customerEndpoint($order))
            ->assertOk()
            ->assertExactJson([
                'data' => [],
            ]);

        $this->assertSame(
            0,
            $order->statusHistories()->count()
        );
    }

    public function test_admin_can_view_complete_audit_information(): void
    {
        [$customer, $order] = $this->createOrder();
        $admin = $this->createAdmin();

        app(PaymentConfirmationService::class)
            ->confirm($order->id, $admin);

        app(OrderFulfillmentService::class)
            ->advance($order->id, 'shipped', $admin);

        Sanctum::actingAs($admin);

        $this->getJson($this->adminEndpoint($order))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath(
                'data.0.source',
                'checkout'
            )
            ->assertJsonPath(
                'data.0.actor.id',
                $customer->id
            )
            ->assertJsonPath(
                'data.1.source',
                'payment_confirmation'
            )
            ->assertJsonPath(
                'data.1.actor.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.1.actor.name',
                $admin->name
            )
            ->assertJsonPath(
                'data.2.source',
                'fulfillment'
            )
            ->assertJsonPath(
                'data.2.actor.id',
                $admin->id
            );
    }

    public function test_admin_can_view_history_without_actor(): void
    {
        [, $order] = $this->createOrder();
        $admin = $this->createAdmin();

        // Chamada direta sem responsável informado.
        app(PaymentConfirmationService::class)
            ->confirm($order->id);

        Sanctum::actingAs($admin);

        $this->getJson($this->adminEndpoint($order))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'data.1.source',
                'payment_confirmation'
            )
            ->assertJsonPath(
                'data.1.actor',
                null
            );
    }

    public function test_admin_history_requires_authentication(): void
    {
        [, $order] = $this->createOrder();

        $this->getJson($this->adminEndpoint($order))
            ->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_history(): void
    {
        [$customer, $order] = $this->createOrder();

        Sanctum::actingAs($customer);

        $this->getJson($this->adminEndpoint($order))
            ->assertForbidden();
    }

    public function test_admin_history_of_nonexistent_order_returns_404(): void
    {
        $admin = $this->createAdmin();

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/admin/orders/999999/history'
        )->assertNotFound();
    }

    public function test_admin_can_view_empty_legacy_history(): void
    {
        $customer = $this->createCustomer();
        $admin = $this->createAdmin();

        $order = $this->createLegacyOrder($customer);

        Sanctum::actingAs($admin);

        $this->getJson($this->adminEndpoint($order))
            ->assertOk()
            ->assertExactJson([
                'data' => [],
            ]);
    }
}

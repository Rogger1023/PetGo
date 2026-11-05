<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(
        User $user,
        int $totalCents = 2500
    ): Order {
        $order = new Order();

        $order->user()->associate($user);
        $order->status = 'pending_payment';
        $order->shipping_cents = 0;
        $order->subtotal_cents = $totalCents;
        $order->total_cents = $totalCents;
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
        $item->product_name = 'Ração PetGo';
        $item->quantity = 1;
        $item->unit_price_cents = $totalCents;
        $item->line_total_cents = $totalCents;
        $item->save();

        $payment = new Payment();
        $payment->order()->associate($order);
        $payment->status = 'pending';
        $payment->save();

        return $order;
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();

        $admin->role = 'admin';
        $admin->save();

        return $admin;
    }

    public function test_customer_lists_only_own_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $first = $this->createOrder($user, 1000);
        $second = $this->createOrder($user, 2000);
        $this->createOrder($otherUser, 3000);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))
            ->pluck('id')
            ->all();

        $this->assertSame(
            [$second->id, $first->id],
            $ids
        );
    }

    public function test_customer_can_show_own_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user, 3500);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.total_cents', 3500)
            ->assertJsonPath(
                'data.status',
                'pending_payment'
            )
            ->assertJsonPath(
                'data.items.0.product_name',
                'Ração PetGo'
            )
            ->assertJsonPath(
                'data.payment.status',
                'pending'
            )
            ->assertJsonPath(
                'data.address.city',
                'Guarapuava'
            );
    }

    public function test_customer_cannot_access_another_users_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $order = $this->createOrder($otherUser);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertNotFound();
    }

    public function test_customer_gets_404_for_nonexistent_order(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/orders/999999')
            ->assertNotFound();
    }

    public function test_order_routes_require_authentication(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user);

        $this->getJson('/api/orders')
            ->assertUnauthorized();

        $this->getJson("/api/orders/{$order->id}")
            ->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_order_routes(): void
    {
        $user = User::factory()->create();
        $user->role = 'customer';
        $user->save();

        $order = $this->createOrder($user);

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/orders')
            ->assertForbidden();

        $this->getJson("/api/admin/orders/{$order->id}")
            ->assertForbidden();
    }

    public function test_admin_order_routes_require_authentication(): void
    {
        $this->getJson('/api/admin/orders')
            ->assertUnauthorized();

        $this->getJson('/api/admin/orders/999999')
            ->assertUnauthorized();
    }

    public function test_admin_can_list_all_orders(): void
    {
        $admin = $this->createAdmin();

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $orderA = $this->createOrder($userA, 1500);
        $orderB = $this->createOrder($userB, 2500);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $orderB->id)
            ->assertJsonPath('data.1.id', $orderA->id)
            ->assertJsonPath(
                'data.0.user.email',
                $userB->email
            )
            ->assertJsonPath(
                'data.1.user.email',
                $userA->email
            );

        $this->assertArrayNotHasKey(
            'password',
            $response->json('data.0.user')
        );
    }

    public function test_admin_can_show_any_order(): void
    {
        $admin = $this->createAdmin();
        $customer = User::factory()->create();

        $order = $this->createOrder($customer, 4500);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath(
                'data.user.email',
                $customer->email
            )
            ->assertJsonPath('data.total_cents', 4500)
            ->assertJsonPath(
                'data.payment.status',
                'pending'
            )
            ->assertJsonCount(1, 'data.items');
    }

    public function test_admin_gets_404_for_nonexistent_order(): void
    {
        $admin = $this->createAdmin();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/orders/999999')
            ->assertNotFound();
    }

    public function test_customer_orders_are_paginated(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            $this->createOrder($user);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/orders?page=1')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/orders?page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_admin_orders_are_paginated(): void
    {
        $admin = $this->createAdmin();
        $customer = User::factory()->create();

        for ($i = 0; $i < 16; $i++) {
            $this->createOrder($customer);
        }

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/orders?page=1')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.current_page', 1);

        $this->getJson('/api/admin/orders?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_customer_response_does_not_expose_internal_data(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.checkout_key')
            ->assertJsonMissingPath('data.user')
            ->assertJsonMissingPath('data.user_id');
    }
}

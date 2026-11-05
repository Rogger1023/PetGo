<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AddressService;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(): User
    {
        $user = User::factory()->create();

        $user->role = 'customer';
        $user->save();

        return $user;
    }

    private function addressData(array $overrides = []): array
    {
        return array_replace([
            'recipient_name' => 'Cliente PetGo',
            'phone' => '41999999999',
            'postal_code' => '85010-000',
            'street' => 'Rua das Flores',
            'number' => '125',
            'complement' => null,
            'neighborhood' => 'Centro',
            'city' => 'Guarapuava',
            'state' => 'PR',
            'reference' => null,
        ], $overrides);
    }

    private function createAddress(
        User $user,
        array $overrides = []
    ): Address {
        return app(AddressService::class)->create(
            $user,
            $this->addressData($overrides)
        );
    }

    private function endpoint(Address $address): string
    {
        return "/api/addresses/{$address->id}";
    }

    private function checkoutWithAddress(
        User $user,
        Address $address
    ): Order {
        $product = Product::factory()->create([
            'price_cents' => 2500,
            'stock' => 10,
            'is_active' => true,
        ]);

        app(CartService::class)->addItem(
            $user,
            $product->id,
            1
        );

        return app(CheckoutService::class)->checkout(
            $user,
            $address->id,
            (string) Str::uuid()
        );
    }

    public function test_customer_with_no_addresses_gets_empty_list(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $this->getJson('/api/addresses')
            ->assertOk()
            ->assertExactJson([
                'data' => [],
            ]);
    }

    public function test_customer_lists_only_own_addresses_default_first(): void
    {
        $customer = $this->createCustomer();

        $first = $this->createAddress($customer);
        $second = $this->createAddress($customer, [
            'street' => 'Rua do Trabalho',
        ]);

        $anotherCustomer = $this->createCustomer();

        $this->createAddress($anotherCustomer);

        app(AddressService::class)->update(
            $customer,
            $second->id,
            ['is_default' => true]
        );

        Sanctum::actingAs($customer);

        $this->getJson('/api/addresses')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.is_default', true)
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('data.1.is_default', false);
    }

    public function test_first_address_is_always_default(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $response = $this->postJson(
            '/api/addresses',
            $this->addressData([
                'is_default' => false,
            ])
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $addressId = $response->json('data.id');

        $this->assertDatabaseHas('addresses', [
            'id' => $addressId,
            'user_id' => $customer->id,
            'is_default' => 1,
        ]);
    }

    public function test_second_address_is_not_default_by_default(): void
    {
        $customer = $this->createCustomer();

        $first = $this->createAddress($customer);

        Sanctum::actingAs($customer);

        $response = $this->postJson(
            '/api/addresses',
            $this->addressData([
                'street' => 'Rua Secundária',
            ])
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.is_default', false);

        $this->assertTrue($first->fresh()->is_default);

        $this->assertSame(
            1,
            $customer->addresses()
                ->where('is_default', true)
                ->count()
        );
    }

    public function test_creating_new_default_demotes_previous_default(): void
    {
        $customer = $this->createCustomer();

        $first = $this->createAddress($customer);

        Sanctum::actingAs($customer);

        $response = $this->postJson(
            '/api/addresses',
            $this->addressData([
                'street' => 'Novo Endereço Principal',
                'is_default' => true,
            ])
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);

        $this->assertSame(
            1,
            $customer->addresses()
                ->where('is_default', true)
                ->count()
        );
    }

    public function test_customer_can_view_own_address(): void
    {
        $customer = $this->createCustomer();

        $address = $this->createAddress($customer);

        Sanctum::actingAs($customer);

        $this->getJson($this->endpoint($address))
            ->assertOk()
            ->assertJsonPath('data.id', $address->id)
            ->assertJsonPath(
                'data.street',
                'Rua das Flores'
            )
            ->assertJsonPath('data.is_default', true)
            ->assertJsonMissingPath('data.user_id');
    }

    public function test_customer_cannot_view_foreign_address(): void
    {
        $owner = $this->createCustomer();

        $address = $this->createAddress($owner);

        $intruder = $this->createCustomer();

        Sanctum::actingAs($intruder);

        $this->getJson($this->endpoint($address))
            ->assertNotFound();
    }

    public function test_customer_can_update_own_address(): void
    {
        $customer = $this->createCustomer();

        $address = $this->createAddress($customer);

        Sanctum::actingAs($customer);

        $this->putJson(
            $this->endpoint($address),
            $this->addressData([
                'street' => 'Rua Atualizada',
                'number' => '500',
                'complement' => 'Apartamento 10',
                'reference' => 'Próximo ao parque',
            ])
        )
            ->assertOk()
            ->assertJsonPath(
                'data.street',
                'Rua Atualizada'
            )
            ->assertJsonPath('data.number', '500')
            ->assertJsonPath(
                'data.complement',
                'Apartamento 10'
            )
            ->assertJsonPath('data.is_default', true);

        $this->assertSame(
            'Rua Atualizada',
            $address->fresh()->street
        );
    }

    public function test_customer_cannot_update_foreign_address(): void
    {
        $owner = $this->createCustomer();

        $address = $this->createAddress($owner);

        $intruder = $this->createCustomer();

        Sanctum::actingAs($intruder);

        $this->putJson(
            $this->endpoint($address),
            $this->addressData([
                'street' => 'Alteração Indevida',
            ])
        )->assertNotFound();

        $this->assertSame(
            'Rua das Flores',
            $address->fresh()->street
        );
    }

    public function test_customer_can_change_default_on_update(): void
    {
        $customer = $this->createCustomer();

        $first = $this->createAddress($customer);

        $second = $this->createAddress($customer, [
            'street' => 'Rua Secundária',
        ]);

        Sanctum::actingAs($customer);

        $this->putJson(
            $this->endpoint($second),
            $this->addressData([
                'street' => 'Rua Secundária',
                'is_default' => true,
            ])
        )
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);

        $this->assertSame(
            1,
            $customer->addresses()
                ->where('is_default', true)
                ->count()
        );
    }

    public function test_customer_cannot_unset_current_default(): void
    {
        $customer = $this->createCustomer();

        $address = $this->createAddress($customer);

        Sanctum::actingAs($customer);

        $this->putJson(
            $this->endpoint($address),
            $this->addressData([
                'is_default' => false,
            ])
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_default');

        $this->assertTrue(
            $address->fresh()->is_default
        );
    }

    public function test_customer_can_delete_secondary_address(): void
    {
        $customer = $this->createCustomer();

        $primary = $this->createAddress($customer);

        $secondary = $this->createAddress($customer, [
            'street' => 'Rua Secundária',
        ]);

        Sanctum::actingAs($customer);

        $this->deleteJson($this->endpoint($secondary))
            ->assertNoContent();

        $this->assertDatabaseMissing('addresses', [
            'id' => $secondary->id,
        ]);

        $this->assertTrue($primary->fresh()->is_default);
    }

    public function test_deleting_default_promotes_next_address(): void
    {
        $customer = $this->createCustomer();

        $first = $this->createAddress($customer);

        $second = $this->createAddress($customer, [
            'street' => 'Rua Dois',
        ]);

        $third = $this->createAddress($customer, [
            'street' => 'Rua Três',
        ]);

        Sanctum::actingAs($customer);

        $this->deleteJson($this->endpoint($first))
            ->assertNoContent();

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($third->fresh()->is_default);

        $this->assertSame(
            1,
            $customer->addresses()
                ->where('is_default', true)
                ->count()
        );
    }

    public function test_deleting_last_address_leaves_no_default(): void
    {
        $customer = $this->createCustomer();

        $address = $this->createAddress($customer);

        Sanctum::actingAs($customer);

        $this->deleteJson($this->endpoint($address))
            ->assertNoContent();

        $this->assertSame(
            0,
            $customer->addresses()->count()
        );
    }

    public function test_customer_cannot_delete_foreign_address(): void
    {
        $owner = $this->createCustomer();

        $address = $this->createAddress($owner);

        $intruder = $this->createCustomer();

        Sanctum::actingAs($intruder);

        $this->deleteJson($this->endpoint($address))
            ->assertNotFound();

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_all_address_endpoints_require_authentication(): void
    {
        $customer = $this->createCustomer();

        $address = $this->createAddress($customer);

        $this->getJson('/api/addresses')
            ->assertUnauthorized();

        $this->postJson(
            '/api/addresses',
            $this->addressData()
        )->assertUnauthorized();

        $this->getJson($this->endpoint($address))
            ->assertUnauthorized();

        $this->putJson(
            $this->endpoint($address),
            $this->addressData()
        )->assertUnauthorized();

        $this->deleteJson($this->endpoint($address))
            ->assertUnauthorized();
    }

    public function test_store_requires_mandatory_fields(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $this->postJson('/api/addresses', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'recipient_name',
                'phone',
                'postal_code',
                'street',
                'number',
                'neighborhood',
                'city',
                'state',
            ]);

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_store_rejects_invalid_formats(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $this->postJson(
            '/api/addresses',
            $this->addressData([
                'phone' => '123',
                'postal_code' => '123',
                'state' => 'pr',
                'is_default' => 'invalid',
            ])
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'phone',
                'postal_code',
                'state',
                'is_default',
            ]);

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_store_rejects_oversized_fields(): void
    {
        $customer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $this->postJson(
            '/api/addresses',
            $this->addressData([
                'recipient_name' => str_repeat('A', 121),
                'street' => str_repeat('B', 181),
                'reference' => str_repeat('C', 256),
            ])
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'recipient_name',
                'street',
                'reference',
            ]);
    }

    public function test_put_requires_complete_address_data(): void
    {
        $customer = $this->createCustomer();

        $address = $this->createAddress($customer);

        Sanctum::actingAs($customer);

        $this->putJson(
            $this->endpoint($address),
            ['street' => 'Nova Rua']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'recipient_name',
                'phone',
                'postal_code',
                'number',
                'neighborhood',
                'city',
                'state',
            ]);

        $this->assertSame(
            'Rua das Flores',
            $address->fresh()->street
        );
    }

    public function test_store_ignores_submitted_user_id(): void
    {
        $customer = $this->createCustomer();
        $anotherCustomer = $this->createCustomer();

        Sanctum::actingAs($customer);

        $response = $this->postJson(
            '/api/addresses',
            array_merge(
                $this->addressData(),
                ['user_id' => $anotherCustomer->id]
            )
        );

        $response->assertCreated();

        $this->assertDatabaseHas('addresses', [
            'id' => $response->json('data.id'),
            'user_id' => $customer->id,
        ]);

        $this->assertSame(
            0,
            $anotherCustomer->addresses()->count()
        );
    }

    public function test_update_cannot_change_address_owner(): void
    {
        $customer = $this->createCustomer();
        $anotherCustomer = $this->createCustomer();

        $address = $this->createAddress($customer);

        Sanctum::actingAs($customer);

        $this->putJson(
            $this->endpoint($address),
            array_merge(
                $this->addressData(),
                ['user_id' => $anotherCustomer->id]
            )
        )->assertOk();

        $this->assertSame(
            $customer->id,
            $address->fresh()->user_id
        );
    }

    public function test_each_customer_has_independent_default_address(): void
    {
        $customerA = $this->createCustomer();
        $customerB = $this->createCustomer();

        $addressA = $this->createAddress($customerA);
        $addressB = $this->createAddress($customerB);

        $newAddressA = $this->createAddress(
            $customerA,
            [
                'street' => 'Novo Principal',
                'is_default' => true,
            ]
        );

        $this->assertFalse($addressA->fresh()->is_default);
        $this->assertTrue($newAddressA->fresh()->is_default);

        // Alterar o cliente A não pode afetar o cliente B.
        $this->assertTrue($addressB->fresh()->is_default);

        $this->assertSame(
            1,
            $customerB->addresses()
                ->where('is_default', true)
                ->count()
        );
    }

    public function test_updating_address_preserves_order_snapshot(): void
    {
        $customer = $this->createCustomer();

        $address = $this->createAddress($customer, [
            'street' => 'Rua Original',
            'number' => '100',
        ]);

        $order = $this->checkoutWithAddress(
            $customer,
            $address
        );

        Sanctum::actingAs($customer);

        $this->putJson(
            $this->endpoint($address),
            $this->addressData([
                'street' => 'Rua Nova',
                'number' => '900',
            ])
        )->assertOk();

        $this->assertSame(
            'Rua Nova',
            $address->fresh()->street
        );

        $snapshot = $order->fresh()->address_snapshot;

        $this->assertSame(
            'Rua Original',
            $snapshot['street']
        );

        $this->assertSame(
            '100',
            $snapshot['number']
        );
    }

    public function test_deleting_address_preserves_order_snapshot(): void
    {
        $customer = $this->createCustomer();

        $address = $this->createAddress($customer, [
            'street' => 'Rua do Pedido',
            'number' => '55',
        ]);

        $order = $this->checkoutWithAddress(
            $customer,
            $address
        );

        Sanctum::actingAs($customer);

        $this->deleteJson($this->endpoint($address))
            ->assertNoContent();

        $this->assertDatabaseMissing('addresses', [
            'id' => $address->id,
        ]);

        $snapshot = $order->fresh()->address_snapshot;

        $this->assertSame(
            'Rua do Pedido',
            $snapshot['street']
        );

        $this->assertSame(
            '55',
            $snapshot['number']
        );

        $this->assertSame(
            'pending_payment',
            $order->fresh()->status
        );
    }
}

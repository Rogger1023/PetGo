<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AddressService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderCancellationService;
use App\Services\ProductService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'customer'): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function product(int $stock = 50): Product
    {
        return Product::factory()->create([
            'stock' => $stock,
            'price_cents' => 2500,
            'is_active' => true,
        ]);
    }

    private function productData(
        int $categoryId,
        int $stock
    ): array {
        return [
            'category_id' => $categoryId,
            'name' => 'Produto de Auditoria',
            'slug' => 'auditoria-' . Str::random(12),
            'description' => 'Produto para testes',
            'price_cents' => 2500,
            'stock' => $stock,
            'is_active' => true,
        ];
    }

    private function address(User $user): int
    {
        return app(AddressService::class)->create(
            $user,
            [
                'recipient_name' => 'Cliente PetGo',
                'phone' => '41999999999',
                'postal_code' => '85010-000',
                'street' => 'Rua de Teste',
                'number' => '100',
                'complement' => null,
                'neighborhood' => 'Centro',
                'city' => 'Guarapuava',
                'state' => 'PR',
                'reference' => null,
            ]
        )->id;
    }

    private function checkout(
        User $user,
        Product $product,
        int $quantity = 3,
        ?string $key = null
    ): Order {
        $addressId = $this->address($user);

        app(CartService::class)->addItem(
            $user,
            $product->id,
            $quantity
        );

        return app(CheckoutService::class)->checkout(
            $user,
            $addressId,
            $key ?? (string) Str::uuid()
        );
    }

    public function test_product_creation_records_initial_stock(): void
    {
        $admin = $this->user('admin');
        $reference = $this->product(0);

        $product = app(ProductService::class)->createProduct(
            $this->productData($reference->category_id, 30),
            $admin
        );

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'actor_user_id' => $admin->id,
            'order_id' => null,
            'source' => 'product_creation',
            'quantity_delta' => 30,
            'stock_before' => 0,
            'stock_after' => 30,
        ]);

        $this->assertSame(
            1,
            $product->stockMovements()->count()
        );
    }

    public function test_zero_initial_stock_creates_no_movement(): void
    {
        $admin = $this->user('admin');
        $reference = $this->product(0);

        $product = app(ProductService::class)->createProduct(
            $this->productData($reference->category_id, 0),
            $admin
        );

        $this->assertSame(
            0,
            $product->stockMovements()->count()
        );

        $this->assertSame(
            0,
            (int) $product->stock
        );
    }

    public function test_admin_stock_increase_records_positive_delta(): void
    {
        $admin = $this->user('admin');
        $product = $this->product(50);

        app(ProductService::class)->updateProduct(
            $product,
            ['stock' => 70],
            $admin
        );

        $this->assertSame(
            70,
            (int) $product->fresh()->stock
        );

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'actor_user_id' => $admin->id,
            'source' => 'admin_product_update',
            'quantity_delta' => 20,
            'stock_before' => 50,
            'stock_after' => 70,
        ]);
    }

    public function test_admin_stock_reduction_records_negative_delta(): void
    {
        $admin = $this->user('admin');
        $product = $this->product(50);

        app(ProductService::class)->updateProduct(
            $product,
            ['stock' => 42],
            $admin
        );

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'source' => 'admin_product_update',
            'quantity_delta' => -8,
            'stock_before' => 50,
            'stock_after' => 42,
        ]);
    }

    public function test_product_update_without_stock_change_creates_no_movement(): void
    {
        $admin = $this->user('admin');
        $product = $this->product(50);

        app(ProductService::class)->updateProduct(
            $product,
            ['name' => 'Produto Renomeado'],
            $admin
        );

        $this->assertSame(
            'Produto Renomeado',
            $product->fresh()->name
        );

        $this->assertSame(
            0,
            $product->stockMovements()->count()
        );
    }

    public function test_admin_api_stock_update_records_authenticated_actor(): void
    {
        $admin = $this->user('admin');
        $product = $this->product(50);

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/products/{$product->id}",
            ['stock' => 65]
        )->assertOk();

        $movement = $product->stockMovements()
            ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $movement->actor_user_id
        );

        $this->assertSame(
            15,
            $movement->quantity_delta
        );

        $this->assertSame(
            'admin_product_update',
            $movement->source
        );
    }

    public function test_customer_cannot_adjust_admin_stock(): void
    {
        $customer = $this->user();
        $product = $this->product(50);

        Sanctum::actingAs($customer);

        $this->patchJson(
            "/api/admin/products/{$product->id}",
            ['stock' => 90]
        )->assertForbidden();

        $this->assertSame(
            50,
            (int) $product->fresh()->stock
        );

        $this->assertSame(
            0,
            $product->stockMovements()->count()
        );
    }

    public function test_checkout_records_stock_exit_with_order(): void
    {
        $customer = $this->user();
        $product = $this->product(50);

        $order = $this->checkout(
            $customer,
            $product,
            3
        );

        $this->assertSame(
            47,
            (int) $product->fresh()->stock
        );

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'actor_user_id' => $customer->id,
            'order_id' => $order->id,
            'source' => 'checkout',
            'quantity_delta' => -3,
            'stock_before' => 50,
            'stock_after' => 47,
        ]);
    }

    public function test_repeated_checkout_does_not_duplicate_movement(): void
    {
        $customer = $this->user();
        $product = $this->product(50);

        $addressId = $this->address($customer);

        app(CartService::class)->addItem(
            $customer,
            $product->id,
            3
        );

        $key = (string) Str::uuid();

        $service = app(CheckoutService::class);

        $firstOrder = $service->checkout(
            $customer,
            $addressId,
            $key
        );

        $secondOrder = $service->checkout(
            $customer,
            $addressId,
            $key
        );

        $this->assertSame(
            $firstOrder->id,
            $secondOrder->id
        );

        $this->assertSame(
            1,
            StockMovement::query()
                ->where('order_id', $firstOrder->id)
                ->where('source', 'checkout')
                ->count()
        );

        $this->assertSame(
            47,
            (int) $product->fresh()->stock
        );
    }

    public function test_cancellation_restores_stock_only_once(): void
    {
        $customer = $this->user();
        $product = $this->product(50);

        $order = $this->checkout(
            $customer,
            $product,
            3
        );

        $service = app(OrderCancellationService::class);

        $service->cancel($customer, $order->id);

        // Segunda tentativa não pode devolver estoque novamente.
        $service->cancel($customer, $order->id);

        $this->assertSame(
            50,
            (int) $product->fresh()->stock
        );

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'actor_user_id' => $customer->id,
            'order_id' => $order->id,
            'source' => 'order_cancellation',
            'quantity_delta' => 3,
            'stock_before' => 47,
            'stock_after' => 50,
        ]);

        $this->assertSame(
            2,
            StockMovement::query()
                ->where('order_id', $order->id)
                ->count()
        );
    }

    public function test_admin_update_rolls_back_when_audit_fails(): void
    {
        $admin = $this->user('admin');
        $product = $this->product(50);

        // Simula uma falha do banco ao registrar auditoria.
        DB::unprepared("
            CREATE TRIGGER block_stock_movement
            BEFORE INSERT ON stock_movements
            BEGIN
                SELECT RAISE(ABORT, 'Audit unavailable');
            END;
        ");

        try {
            app(ProductService::class)->updateProduct(
                $product,
                ['stock' => 80],
                $admin
            );

            $this->fail(
                'A atualização deveria falhar.'
            );
        } catch (QueryException $exception) {
            $this->assertNotEmpty(
                $exception->getMessage()
            );
        }

        // Como a transação falhou, estoque permanece original.
        $this->assertSame(
            50,
            (int) $product->fresh()->stock
        );

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_checkout_rolls_back_when_audit_fails(): void
    {
        $customer = $this->user();
        $product = $this->product(50);
        $addressId = $this->address($customer);

        app(CartService::class)->addItem(
            $customer,
            $product->id,
            3
        );

        DB::unprepared("
            CREATE TRIGGER block_stock_movement
            BEFORE INSERT ON stock_movements
            BEGIN
                SELECT RAISE(ABORT, 'Audit unavailable');
            END;
        ");

        try {
            app(CheckoutService::class)->checkout(
                $customer,
                $addressId,
                (string) Str::uuid()
            );

            $this->fail(
                'O checkout deveria falhar.'
            );
        } catch (QueryException $exception) {
            $this->assertNotEmpty(
                $exception->getMessage()
            );
        }

        $this->assertSame(
            50,
            (int) $product->fresh()->stock
        );

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('stock_movements', 0);

        $this->assertSame(
            'active',
            $customer->carts()
                ->firstOrFail()
                ->fresh()
                ->status
        );
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'admin'])
            ->get('/api/test/admin', function () {
                return response()->json([
                    'message' => 'Acesso permitido.',
                ]);
            });
    }

    public function test_admin_can_access_protected_route(): void
    {
        $admin = User::factory()->create();

        $admin->role = 'admin';
        $admin->save();

        Sanctum::actingAs($admin);

        $this->getJson('/api/test/admin')
            ->assertOk()
            ->assertJson([
                'message' => 'Acesso permitido.',
            ]);
    }

    public function test_customer_cannot_access_admin_route(): void
    {
        $customer = User::factory()->create();

        Sanctum::actingAs($customer);

        $this->getJson('/api/test/admin')
            ->assertForbidden();
    }

    public function test_guest_cannot_access_admin_route(): void
    {
        $this->getJson('/api/test/admin')
            ->assertUnauthorized();
    }

    public function test_unknown_role_cannot_access_admin_route(): void
    {
        $user = User::factory()->create();

        $user->role = 'manager';
        $user->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/test/admin')
            ->assertForbidden();
    }

    public function test_public_route_remains_accessible(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson([
                'app' => 'PetGo',
                'status' => 'ok',
            ]);
    }

    public function test_is_admin_only_recognizes_admin_role(): void
    {
        $user = new User();

        $user->role = 'admin';
        $this->assertTrue($user->isAdmin());

        $user->role = 'customer';
        $this->assertFalse($user->isAdmin());

        $user->role = 'manager';
        $this->assertFalse($user->isAdmin());
    }
}

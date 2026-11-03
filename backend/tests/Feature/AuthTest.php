<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_customer_and_returns_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'password' => 'SenhaSegura123!',
            'password_confirmation' => 'SenhaSegura123!',
            'role' => 'admin',
            'guest_cart_token' => 'carrinho-visitante',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'cliente@example.com')
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ]);

        $this->assertIsString($response->json('token'));
        $this->assertArrayNotHasKey('password', $response->json('user'));

        $user = User::where('email', 'cliente@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('SenhaSegura123!', $user->password));
        $this->assertSame('customer', $user->role);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'SenhaSegura123!',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'cliente@example.com',
            'password' => 'SenhaSegura123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['user', 'token']);

        $this->assertIsString($response->json('token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_invalid_credentials_return_401(): void
    {
        User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'SenhaSegura123!',
        ]);

        $this->postJson('/api/login', [
            'email' => 'cliente@example.com',
            'password' => 'senha-incorreta',
        ])->assertUnauthorized()
          ->assertJsonPath('message', 'Credenciais inválidas.');

        $this->postJson('/api/login', [
            'email' => 'inexistente@example.com',
            'password' => 'SenhaSegura123!',
        ])->assertUnauthorized()
          ->assertJsonPath('message', 'Credenciais inválidas.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_duplicate_email_returns_422(): void
    {
        User::factory()->create([
            'email' => 'cliente@example.com',
        ]);

        $this->postJson('/api/register', [
            'name' => 'Outro Cliente',
            'email' => 'cliente@example.com',
            'password' => 'SenhaSegura123!',
            'password_confirmation' => 'SenhaSegura123!',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['email']);
    }

    public function test_invalid_registration_data_returns_422(): void
    {
        $this->postJson('/api/register', [
            'name' => '',
            'email' => 'email-invalido',
            'password' => '123',
            'password_confirmation' => 'diferente',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors([
              'name',
              'email',
              'password',
          ]);
    }

    public function test_login_without_password_returns_422(): void
    {
        $this->postJson('/api/login', [
            'email' => 'cliente@example.com',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['password']);
    }
}

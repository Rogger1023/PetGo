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
    public function test_logout_revokes_current_token(): void
    {
        // Cria um usuário para o teste.
        $user = User::factory()->create();

        // Gera um token Sanctum.
        $token = $user->createToken('auth-token')->plainTextToken;

        // Verifica que o token existe no banco.
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Executa o logout.
        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout realizado com sucesso.');

        // Confirma que o token foi revogado.
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Limpa os guards em memória para simular
        // uma nova requisição com autenticação independente.
        app('auth')->forgetGuards();

        // Tenta reutilizar o token revogado.
        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_logout_without_authentication_returns_401(): void
    {
        // Tenta realizar logout sem enviar um token.
        $response = $this->postJson('/api/logout');

        // Verifica se o acesso foi negado.
        $response->assertUnauthorized();

        // Confirma que nenhum token foi criado.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
    public function test_logout_revokes_only_current_token(): void
    {
        // Cria um usuário para o teste.
        $user = User::factory()->create();

        // Simula autenticação em dois dispositivos.
        $tokenComputador = $user->createToken('computador');
        $tokenCelular = $user->createToken('celular');

        // Confirma que existem dois tokens.
        $this->assertDatabaseCount('personal_access_tokens', 2);

        // Realiza logout utilizando o token do computador.
        $this->withToken($tokenComputador->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk();

        // Verifica se o token do computador foi removido.
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenComputador->accessToken->id,
        ]);

        // Verifica se o token do celular continua armazenado.
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenCelular->accessToken->id,
        ]);

        // Deve restar somente um token.
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Limpa o estado de autenticação entre requisições.
        app('auth')->forgetGuards();

        // O token do computador não pode mais acessar a API.
        $this->withToken($tokenComputador->plainTextToken)
            ->getJson('/api/me')
            ->assertUnauthorized();

        // Limpa novamente o estado de autenticação.
        app('auth')->forgetGuards();

        // O token do celular deve continuar funcionando.
        $this->withToken($tokenCelular->plainTextToken)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }
}


# PetGo — API de Autenticação

## Visão geral

A autenticação utiliza Laravel Sanctum com tokens Bearer.

A lógica de negócio está concentrada no AuthService, enquanto
o AuthController gerencia as respostas HTTP.

## POST /api/register

Realiza o cadastro de um novo cliente.

### Corpo da requisição

{
  "name": "Cliente Teste",
  "email": "cliente@example.com",
  "password": "SenhaSegura123!",
  "password_confirmation": "SenhaSegura123!"
}

O campo guest_cart_token é opcional e está preparado para
uma futura integração com o carrinho de visitante.

### Resposta de sucesso — 201

{
  "user": {
    "id": 1,
    "name": "Cliente Teste",
    "email": "cliente@example.com",
    "role": "customer"
  },
  "token": "TOKEN_GERADO_PELO_SANCTUM"
}

## POST /api/login

Autentica um usuário já cadastrado.

### Corpo da requisição

{
  "email": "cliente@example.com",
  "password": "SenhaSegura123!"
}

### Resposta de sucesso — 200

Retorna os dados públicos do usuário e um token Sanctum.

### Credenciais inválidas — 401

{
  "message": "Credenciais inválidas."
}

## Validações — 422

O Laravel retorna erros de validação para situações como:

- E-mail duplicado no cadastro.
- E-mail em formato inválido.
- Senha sem confirmação correspondente.
- Campos obrigatórios ausentes.

## Segurança

- Senhas são armazenadas com hash.
- Senhas nunca são retornadas na resposta da API.
- Usuários cadastrados publicamente recebem o papel customer.
- Tokens são gerados apenas após cadastro ou login válido.
- Tokens Bearer devem ser transmitidos somente por HTTPS
  em ambientes publicados.

## Testes

Testes de autenticação implementados em:

tests/Feature/AuthTest.php

Cenários cobertos:

- Cadastro válido.
- Login válido.
- Credenciais incorretas.
- E-mail duplicado.
- Dados de cadastro inválidos.
- Login sem senha.

Validação da suíte completa:

10 testes aprovados, 74 asserções.

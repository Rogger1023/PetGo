
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


## GET /api/me

Retorna os dados do usuário autenticado.

### Autenticação

Este endpoint é protegido pelo middleware `auth:sanctum`.

É necessário enviar um token válido no cabeçalho HTTP:

```http
Authorization: Bearer TOKEN_GERADO_PELO_SANCTUM
```

### Resposta de sucesso — 200

```json
{
  "user": {
    "id": 1,
    "name": "Cliente Teste",
    "email": "cliente@example.com",
    "role": "customer"
  }
}
```

### Usuário não autenticado — 401

Caso o token esteja ausente, seja inválido ou tenha sido revogado:

```json
{
  "message": "Unauthenticated."
}
```

## POST /api/logout

Encerra a autenticação do dispositivo atual, revogando
o token utilizado na requisição.

### Autenticação

Este endpoint também utiliza o middleware `auth:sanctum`.

O cliente deve enviar:

```http
Authorization: Bearer TOKEN_GERADO_PELO_SANCTUM
```

Não é necessário enviar um corpo JSON.

### Resposta de sucesso — 200

```json
{
  "message": "Logout realizado com sucesso."
}
```

### Usuário não autenticado — 401

Requisições sem autenticação válida são rejeitadas.

```json
{
  "message": "Unauthenticated."
}
```

### Comportamento da revogação

O logout remove apenas o token utilizado na requisição.

Exemplo:

- Computador autenticado com Token A.
- Celular autenticado com Token B.
- Logout realizado no computador.
- Token A é revogado.
- Token B permanece válido.

Após o logout, o token revogado não pode mais acessar
endpoints protegidos.

Para autenticar novamente, o usuário deve realizar login
e obter um novo token Sanctum.


## Validações — 422

O Laravel retorna erros de validação para situações como:

- E-mail duplicado no cadastro.
- E-mail em formato inválido.
- Senha sem confirmação correspondente.
- Campos obrigatórios ausentes.


## Segurança

- Senhas são armazenadas utilizando hash.
- Senhas nunca são retornadas nas respostas da API.
- Usuários cadastrados publicamente recebem o papel `customer`.
- Tokens são gerados apenas após cadastro ou login válido.
- As rotas `/api/me` e `/api/logout` são protegidas pelo middleware `auth:sanctum`.
- O logout revoga apenas o token utilizado na requisição.
- Tokens revogados não podem mais acessar endpoints protegidos.
- Outros tokens pertencentes ao mesmo usuário permanecem válidos.
- Tokens Bearer devem ser transmitidos somente por HTTPS em ambientes publicados.
- Tokens de autenticação não devem ser registrados em logs ou armazenados em repositórios.

## Testes

Os testes automatizados de autenticação estão implementados em:

`backend/tests/Feature/AuthTest.php`

### Cenários cobertos

- Cadastro válido de cliente.
- Login com credenciais válidas.
- Login com credenciais incorretas.
- Tentativa de cadastro com e-mail duplicado.
- Validação de dados inválidos no cadastro.
- Login sem senha.
- Logout com token válido.
- Revogação e bloqueio de reutilização do token.
- Tentativa de logout sem autenticação.
- Logout com múltiplos tokens, preservando os demais dispositivos.

### Execução

A suíte utiliza SQLite em memória para evitar alterações no banco MySQL principal.

```bash
docker compose exec \
  -e APP_ENV=testing \
  -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=:memory: \
  -e DB_URL= \
  api ./vendor/bin/phpunit --display-warnings
```

### Último resultado validado

- 13 testes aprovados.
- 89 asserções aprovadas.
- Nenhuma falha encontrada.

Os resultados correspondem à suíte completa do PetGo,
incluindo os testes de autenticação e demais funcionalidades.

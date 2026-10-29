# PetGo — Contrato REST da API

## 1. Objetivo

Definir como o frontend e o backend se comunicam, estabelecendo
rotas, métodos HTTP, formatos de dados, permissões e códigos de resposta.

## 2. Convenções gerais

- Prefixo atual: `/api`
- Formato das requisições: JSON quando houver corpo.
- Formato esperado das respostas: JSON.
- Codificação: UTF-8.
- Identificadores: inteiros.
- Valores monetários: inteiros em centavos (`price_cents`).
- Listagens: paginadas.
- Rotas futuras devem seguir os mesmos padrões deste documento.

Ainda não utilizamos o prefixo `/api/v1`. A adoção de versionamento
será avaliada antes de disponibilizar a API para consumidores externos.

## 3. Rotas implementadas

### GET /api/health

**Objetivo:** verificar se a aplicação responde.

- Autenticação: não exigida.
- Parâmetros: nenhum.
- Sucesso: `200 OK`.

Resposta esperada:

```json
{
  "app": "PetGo",
  "status": "ok"
}
```

### GET /api/products

**Objetivo:** listar os produtos ativos da loja.

- Autenticação: não exigida.
- Parâmetros de consulta:
  - `page`: página desejada; opcional, padrão `1`.
- Ordenação: nome do produto.
- Paginação: 12 produtos por página.
- Produtos inativos: não devem aparecer.
- Sucesso: `200 OK`.

Formato da resposta: coleção paginada do Laravel, com `data`,
`links` e `meta`.

Cada produto pode conter:

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | integer | Identificador |
| `name` | string | Nome do produto |
| `slug` | string | Identificador amigável |
| `description` | string ou null | Descrição |
| `image_path` | string ou null | Caminho da imagem |
| `price_cents` | integer | Preço em centavos |
| `in_stock` | boolean | Indica se há estoque |
| `category` | object ou null | Categoria carregada |

Exemplo ilustrativo de um item:

```json
{
  "id": 1,
  "name": "Racao Premium para Caes 1kg",
  "slug": "racao-premium-caes-1kg",
  "description": null,
  "image_path": null,
  "price_cents": 7990,
  "in_stock": true,
  "category": {
    "id": 1,
    "name": "Alimentacao",
    "slug": "alimentacao",
    "description": null
  }
}
```

A resposta real da listagem inclui o item dentro de `data`,
além dos metadados e links da paginação.

## 4. Convenções dos métodos HTTP

| Método | Finalidade | Uso previsto |
|---|---|---|
| `GET` | Consultar dados | Listar ou visualizar recursos |
| `POST` | Criar ou executar uma operação | Cadastro ou criação de pedido |
| `PATCH` | Atualizar parcialmente | Alterar quantidade ou dados |
| `DELETE` | Remover um recurso | Remover item do carrinho |

As novas rotas serão documentadas antes ou durante a implementação
de cada funcionalidade. Os exemplos de uso não significam que
essas rotas já estejam implementadas.

## 5. Códigos HTTP

| Código | Significado | Quando utilizar |
|---|---|---|
| `200 OK` | Operação concluída | Consultas e atualizações com resposta |
| `201 Created` | Recurso criado | Cadastro ou criação bem-sucedida |
| `204 No Content` | Sucesso sem conteúdo | Remoção sem corpo de resposta |
| `400 Bad Request` | Requisição inválida | Quando aplicável |
| `401 Unauthorized` | Não autenticado | Token ausente ou inválido |
| `403 Forbidden` | Acesso negado | Usuário sem permissão |
| `404 Not Found` | Recurso inexistente | Produto, pedido ou recurso não encontrado |
| `409 Conflict` | Conflito de estado | Estoque insuficiente ou operação incompatível |
| `422 Unprocessable Content` | Dados inválidos | Falha de validação |
| `500 Internal Server Error` | Erro inesperado | Falha não tratada no servidor |

O código escolhido deve representar a causa real da resposta.
Por exemplo, estoque insuficiente durante a finalização de um pedido
deve ser tratado como conflito de estado, e não como erro de validação
de um campo qualquer.

## 6. Formato de erros

O formato desejado para erros gerais é:

```json
{
  "message": "Não foi possível concluir a operação.",
  "code": "OPERATION_FAILED"
}
```

Para erros de validação (`422`), os campos devem ser identificados:

```json
{
  "message": "Os dados informados são inválidos.",
  "errors": {
    "email": [
      "Informe um endereço de e-mail válido."
    ]
  }
}
```

Esses exemplos definem o padrão desejado. A padronização efetiva das
respostas de erro será implementada conforme os endpoints forem criados.

## 7. Regras de acesso

- Consulta do catálogo: pública.
- Dados pessoais, carrinho e pedidos do cliente: exigem autenticação.
- Operações administrativas: exigem autenticação e autorização adequada.
- Um cliente só pode acessar seus próprios dados e pedidos.
- A validação de permissões deve ocorrer no backend, nunca apenas no frontend.

O mecanismo de autenticação e suas rotas serão definidos na etapa
específica de autenticação.

## 8. Paginação

As listagens devem aceitar o parâmetro `page` e informar os metadados
necessários para navegar entre páginas.

O tamanho padrão atual do catálogo é de 12 produtos por página.
Outras listagens poderão definir tamanhos próprios quando implementadas.

## 9. Critérios para novas rotas

Antes de considerar um endpoint concluído, documentar:

1. Método HTTP e URI.
2. Parâmetros e corpo da requisição.
3. Formato da resposta de sucesso.
4. Regras de autenticação e autorização.
5. Possíveis códigos de erro.
6. Regras de validação e de negócio.


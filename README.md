# PetGo 🐾

O **PetGo** é um projeto educacional de e-commerce para pet shop, desenvolvido para simular uma plataforma de venda de produtos e acessórios para animais de estimação.

O projeto aplica práticas de desenvolvimento web, arquitetura de APIs REST, autenticação, segurança, bancos de dados, testes automatizados e conteinerização com Docker. **O checkout e os pagamentos são simulados**: não há integração com um gateway financeiro real.

## Tecnologias utilizadas

- **Frontend:** HTML, CSS, JavaScript puro e Nginx.
- **Backend:** PHP 8.4, Laravel 12 e FrankenPHP.
- **Autenticação:** Laravel Sanctum, com papéis `customer` e `admin`.
- **Banco de dados:** MySQL 8.4.
- **Proxy reverso:** Nginx.
- **Infraestrutura:** Docker e Docker Compose.
- **Testes:** PHPUnit, com SQLite em memória no ambiente de testes.
- **Documentação da API:** OpenAPI 3 e Swagger UI, disponibilizados pela API.

## Arquitetura

O PetGo utiliza quatro serviços Docker orquestrados pelo arquivo `compose.yaml`, localizado na raiz do projeto.

```text
              Navegador
                  |
                  | HTTP :80 (ou PROXY_PORT)
                  v
             Proxy Nginx
                  |
           +------+------+
           |             |
           v             v
      Frontend         API Laravel
      Nginx :80        FrankenPHP :80
                         |
                         v
                      MySQL :3306
```

### Responsabilidade dos containers

| Serviço | Responsabilidade |
|---|---|
| `proxy` | Receber as requisições HTTP e encaminhá-las ao serviço adequado |
| `frontend` | Servir os arquivos estáticos da interface da loja |
| `api` | Executar a aplicação Laravel e disponibilizar a documentação Swagger |
| `db` | Armazenar e gerenciar os dados no MySQL |

Os containers se comunicam pela rede interna `app_network`. Apenas o proxy publica uma porta no computador; os demais serviços ficam disponíveis na rede interna do Docker.

### Roteamento

O proxy encaminha as requisições de acordo com o caminho solicitado:

| Caminho | Destino |
|---|---|
| `/` | Frontend (`frontend:80`) |
| `/api/*` | API Laravel (`api:80`) |
| `/api/docs` | Swagger UI, disponibilizado pelo serviço `api` |
| `/api/docs/openapi.yaml` | Especificação OpenAPI da nova documentação |
| `/sanctum/*` | API Laravel (`api:80`) |

A aplicação Laravel é executada pelo FrankenPHP, utilizando `/app/public` como diretório público no container da API.

## Estrutura do projeto

```text
PetGo/
├── backend/
│   ├── Dockerfile
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   │   └── seeders/
│   ├── public/
│   │   └── api/docs/       # Swagger UI e especificação servidos pela API
│   ├── routes/
│   └── tests/
├── frontend/
│   ├── Dockerfile
│   ├── nginx.conf
│   ├── admin/
│   ├── css/
│   ├── js/
│   ├── index.html
│   └── sobre.html
├── proxy/
│   ├── Dockerfile
│   └── default.conf
├── docs/
├── scripts/
│   └── test.sh
├── .env.example
├── compose.yaml
└── README.md
```

## Configuração do ambiente

### Pré-requisitos

- Docker com Docker Compose.
- Git.
- Uma porta HTTP disponível no computador.

### 1. Configurar as variáveis de ambiente

Na raiz do projeto:

```bash
cp .env.example .env
```

Edite o `.env` com as credenciais **locais** do MySQL. O arquivo de exemplo da raiz contempla as variáveis `APP_KEY`, `COMPOSE_PROJECT_NAME`, `PROXY_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` e `DB_ROOT_PASSWORD`. A configuração do Compose também permite definir `APP_ENV` e `APP_DEBUG`.

Exemplo de valores **não sensíveis** para desenvolvimento:

```dotenv
COMPOSE_PROJECT_NAME=petgo
PROXY_PORT=80
DB_NAME=petgo
DB_USER=petgo_app
APP_ENV=local
APP_DEBUG=true
```

Defina `DB_PASSWORD` e `DB_ROOT_PASSWORD` com valores próprios no `.env` local. Nunca publique suas credenciais nem a `APP_KEY` no Git. Caso a porta 80 esteja ocupada, configure, por exemplo, `PROXY_PORT=8081`.

O repositório também possui `backend/.env.example`, com opções de configuração do Laravel. As variáveis definidas na seção `environment` do Compose prevalecem no processo do container correspondente.

### 2. Gerar a chave do Laravel

Construa a imagem da API:

```bash
docker compose build api
```

Gere uma chave:

```bash
docker compose run --rm --no-deps api php artisan key:generate --show
```

Copie a chave resultante para a variável `APP_KEY` no `.env` da raiz. **Não** inclua essa chave no repositório.

### 3. Iniciar os containers

```bash
docker compose up -d --build
```

Verifique o estado dos serviços:

```bash
docker compose ps
```

Os serviços `proxy`, `frontend`, `api` e `db` devem estar em execução.

### 4. Executar as migrations e popular o banco de desenvolvimento

Com os containers em execução e o MySQL disponível:

```bash
docker compose exec api php artisan migrate --seed
```

Esse comando **executa migrations pendentes** (incluindo as tabelas necessárias à autenticação Sanctum, quando suas migrations estão presentes) e **chama o `DatabaseSeeder`** para inserir os dados de desenvolvimento.

Os seeders registrados no projeto são:

- `CategorySeeder`: categorias iniciais.
- `ProductSeeder`: produtos iniciais.
- `TestUserSeeder`: usuários de teste.
- `CatalogDemoSeeder`: catálogo demonstrativo.

O `DatabaseSeeder` possui uma proteção que impede sua execução quando `APP_ENV=production`. Reexecuções podem manter, atualizar ou duplicar dados conforme a implementação de cada seeder.

Para executar somente os seeders novamente:

```bash
docker compose exec api php artisan db:seed
```

**Atenção:** não execute `migrate:fresh --seed` ou `docker compose down -v` se precisar preservar os dados existentes: esses comandos podem apagar tabelas ou volumes persistentes.

### 5. Acessar o PetGo

Com `PROXY_PORT=80`:

| Recurso | Endereço |
|---|---|
| Loja virtual | `http://localhost/` |
| Login e cadastro | Pela navegação da loja |
| Painel administrativo de pedidos | `http://localhost/admin/` |
| Gerenciamento de catálogo | `http://localhost/admin/catalogo.html` |
| Página Sobre nós | `http://localhost/sobre.html` |
| **Swagger UI (novo)** | **`http://localhost/api/docs`** |
| Especificação OpenAPI | `http://localhost/api/docs/openapi.yaml` |
| Saúde da API | `http://localhost/api/health` |

Se utilizar outra `PROXY_PORT`, inclua-a no endereço (por exemplo, `http://localhost:8081/api/docs`).

## Funcionalidades do sistema

### Loja e clientes

- Catálogo de produtos e categorias.
- Consulta de detalhes e imagens dos produtos.
- Cadastro, login e logout.
- Carrinho de compras para visitantes e clientes autenticados.
- Gerenciamento de endereços.
- Checkout com validações de estoque.
- Histórico e acompanhamento de pedidos.
- Pagamento simulado, sem cobrança financeira real.

### Administração

O painel administrativo permite consultar pedidos e seus históricos, confirmar pagamentos simulados, atualizar status logísticos, cadastrar e editar produtos, desativá-los, gerenciar categorias e consultar movimentações de estoque pela API.

As rotas administrativas são protegidas por autenticação Sanctum e middleware de autorização para administradores.

## Documentação da API — Swagger / OpenAPI

A documentação interativa da API está disponível no **Swagger UI**, em:

**`http://localhost/api/docs`**

A implementação atual é servida pelo **backend**, não pelo antigo `frontend/swagger.html`. O Swagger utiliza uma especificação OpenAPI e agrupa as operações da API em seções como Saúde, Autenticação, Produtos, Categorias, Carrinho, Endereços, Checkout, Pedidos e Administração.

### Como executar uma requisição

1. Inicie os containers com Docker Compose.
2. Abra `http://localhost/api/docs` no navegador.
3. Expanda a seção e a operação desejadas.
4. Clique em **Try it out**.
5. Preencha os parâmetros e/ou o corpo JSON, quando exigidos.
6. Clique em **Execute**.
7. Consulte a URL, o comando cURL, o status HTTP e o corpo da resposta.

**Exemplo de rota pública:** `GET /api/products` lista o catálogo de produtos ativos. Ela pode ser testada sem autenticação.

### Autenticação no Swagger com Laravel Sanctum

As rotas protegidas exigem um token Bearer válido.

**1. Faça login** utilizando `POST /api/login`, com uma conta existente no banco local:

```json
{
  "email": "usuario@exemplo.com",
  "password": "SENHA_DO_USUARIO"
}
```

As credenciais acima são apenas ilustrativas. Quando válidas, o backend retorna um objeto com os dados do usuário e um campo `token`:

```json
{
  "user": {
    "id": 1,
    "name": "Usuário de Teste",
    "email": "usuario@exemplo.com",
    "role": "customer"
  },
  "token": "TOKEN_GERADO_PELO_SANCTUM"
}
```

**2. Copie o valor de `token`** da resposta, sem compartilhá-lo publicamente.

**3. Clique em `Authorize`** no Swagger UI e insira o token. Para um esquema HTTP Bearer, informe somente o token, **sem** escrever `Bearer` no campo.

**4. Execute uma rota protegida**, como `GET /api/me`. O Swagger enviará automaticamente um cabeçalho equivalente a:

```http
Authorization: Bearer SEU_TOKEN
```

### Testar uma rota administrativa

Para testar `GET /api/admin/products`, utilize uma conta que possua `role=admin` e autorize seu token no Swagger. A operação aceita filtros de página, pesquisa por nome e status dos produtos. Uma conta comum não possui permissão para operar nas rotas administrativas.

A rota pública `GET /api/products` e a rota administrativa `GET /api/admin/products` são diferentes: a administrativa permite visualizar produtos ativos e inativos.

### Principais códigos HTTP

| Código | Significado |
|---|---|
| `200` | Requisição concluída com sucesso |
| `201` | Recurso criado |
| `204` | Operação concluída sem corpo de resposta |
| `401` | Token ausente, inválido ou sessão não autenticada |
| `403` | Usuário autenticado sem permissão necessária |
| `404` | Recurso não encontrado |
| `422` | Dados enviados não passaram na validação |
| `429` | Limite de requisições excedido |
| `500` | Erro interno no servidor |

### Localização e manutenção do Swagger

A documentação atual é servida pelo backend, sob o caminho público `/api/docs`. Os arquivos de interface, recursos locais e especificação OpenAPI ficam na estrutura pública da aplicação; no repositório, consulte `backend/public/api/docs/` e a documentação de implantação, quando presente, em `docs/swagger-implantacao.md`.

Quando alterar rotas ou contratos da API, atualize a especificação realmente utilizada por `/api/docs` e valide as operações no Swagger UI. **Não use o fluxo antigo de `/swagger.html` e `/openapi.yaml` do frontend como referência para a documentação atual.**

## Testes automatizados

A suíte oficial do PetGo é executada por:

```bash
bash scripts/test.sh
```

O script prepara o ambiente de testes. Os testes de backend utilizam SQLite em memória, conforme a configuração do projeto.

Última execução informada:

```text
OK (236 tests, 1012 assertions)
Testes finalizados com sucesso!
```

Esse registro é histórico e deve ser atualizado após mudanças relevantes no código.

## Comandos úteis

```bash
# Iniciar o projeto
docker compose up -d

# Reconstruir os containers
docker compose up -d --build

# Verificar o estado dos serviços
docker compose ps

# Acompanhar logs da API
docker compose logs -f api

# Executar migrations e seeders no ambiente de desenvolvimento
docker compose exec api php artisan migrate --seed

# Reexecutar somente os seeders
docker compose exec api php artisan db:seed

# Listar rotas Laravel
docker compose exec api php artisan route:list

# Executar os testes oficiais
bash scripts/test.sh

# Encerrar containers sem apagar os volumes
docker compose down
```

Evite executar `docker compose down -v` ou `php artisan migrate:fresh` sem backup, pois essas operações podem remover dados persistidos.

## Segurança e configuração

O PetGo utiliza Laravel Sanctum para autenticação, middlewares para restringir operações administrativas, validação de entrada no backend, transações de banco para operações críticas, verificações de estoque e variáveis de ambiente para configurações sensíveis.

Arquivos `.env` com credenciais reais **não devem ser versionados**. Mantenha tokens, senhas e chaves fora de commits e capturas de tela compartilhadas.

O ambiente Docker descrito neste README é de **desenvolvimento local**. Antes de expô-lo à internet, revise HTTPS, `APP_DEBUG=false`, segredos, persistência, backups e demais configurações de produção.

## Natureza acadêmica

O PetGo foi desenvolvido para demonstrar conhecimentos em desenvolvimento web, PHP, Laravel, JavaScript, APIs REST, bancos de dados, Docker e segurança. O checkout e os pagamentos são simulados; o projeto não integra um gateway financeiro real neste estágio.

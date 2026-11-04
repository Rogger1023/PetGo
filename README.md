# PetGo 🐾

O **PetGo** é um projeto educacional de e-commerce para petshop, desenvolvido com o objetivo de simular uma plataforma de venda de produtos e acessórios para animais de estimação.

O projeto tem como foco aplicar boas práticas de desenvolvimento web, arquitetura de APIs REST, autenticação, segurança, bancos de dados, testes automatizados e conteinerização com Docker.

## Tecnologias utilizadas

- **Frontend:** HTML, CSS, JavaScript e Nginx.
- **Backend:** PHP 8.4, Laravel e FrankenPHP.
- **Autenticação:** Laravel Sanctum.
- **Banco de dados:** MySQL 8.4.
- **Proxy reverso:** Nginx.
- **Infraestrutura:** Docker e Docker Compose.
- **Testes:** PHPUnit com SQLite em memória.

## Arquitetura

O PetGo utiliza quatro serviços Docker, orquestrados por um único arquivo `compose.yaml` localizado na raiz do projeto.

```text
              Navegador
                  |
                  | HTTP :80
                  v
            Proxy (Nginx)
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
| `proxy` | Receber requisições HTTP e encaminhá-las ao serviço adequado |
| `frontend` | Servir os arquivos estáticos da interface |
| `api` | Executar a aplicação Laravel utilizando FrankenPHP |
| `db` | Armazenar e gerenciar os dados no MySQL |

Os containers se comunicam pela rede interna `app_network`.

Apenas o proxy publica uma porta no computador. Os demais serviços ficam disponíveis somente na rede interna do Docker.

### Roteamento

O proxy encaminha as requisições de acordo com o caminho solicitado:

| Caminho | Destino |
|---|---|
| `/` | `frontend:80` |
| `/api/*` | `api:80` |
| `/sanctum/*` | `api:80` |

O FrankenPHP substitui o Apache e executa a aplicação Laravel utilizando `/app/public` como diretório público.

## Estrutura do projeto

```text
PetGo/
├── backend/
│   ├── Dockerfile
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── routes/
│   └── tests/
│
├── frontend/
│   ├── Dockerfile
│   ├── nginx.conf
│   └── index.html
│
├── proxy/
│   ├── Dockerfile
│   └── default.conf
│
├── docs/
├── .env.example
├── compose.yaml
└── README.md
```

## Configuração do ambiente

### Pré-requisitos

- Docker com suporte a Docker Compose.
- Git.
- Uma porta HTTP disponível no computador.

### 1. Configurar as variáveis de ambiente

Na raiz do projeto:

```bash
cp .env.example .env
```

Edite o `.env` com suas credenciais locais do MySQL.

A variável `PROXY_PORT` define a porta externa utilizada para acessar a aplicação:

```dotenv
PROXY_PORT=80
```

Caso a porta 80 esteja ocupada, utilize outra porta, como `8081`.

### 2. Gerar a chave do Laravel

Construa a imagem da API:

```bash
docker compose build api
```

Gere uma chave:

```bash
docker compose run --rm --no-deps api php artisan key:generate --show
```

Copie o valor retornado para `APP_KEY` no `.env` da raiz.

### 
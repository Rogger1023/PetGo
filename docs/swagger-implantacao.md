# Swagger UI do PetGo — implantação e verificação

## Resultado

- UI pública: `/api/docs` (200 direto, sem redirecionamento), também disponível em `/api/docs/` e `/api/docs/index.html`.
- Especificação pública: `/api/docs/openapi.yaml`.
- Assets locais: `/api/docs/assets/` (Swagger UI dist **5.32.0**, com licenças).
- 44 operações de negócio/saúde da API, correspondentes a todas as combinações método/caminho registradas em `routes/api.php`, mais `GET /sanctum/csrf-cookie`: **45 operações** em 34 caminhos.
- OpenAPI 3.0.3; `servers: [{url: /}]` e caminhos completos `/api/...`, sem duplicar o prefixo.

## Diferenças confirmadas no código enviado

O ZIP contém **backend/** e **frontend/**. Portanto, os arquivos foram colocados em `backend/public/api/docs/`, que é a pasta pública da API real. Não crie outra pasta `api/` na raiz.

A autenticação desta versão usa **tokens pessoais Bearer do Sanctum**, não login por sessão/cookies. Evidências:

- `backend/app/Services/AuthService.php`: `createToken('auth-token')->plainTextToken` em cadastro/login; logout exclui `currentAccessToken()`.
- `backend/app/Models/User.php`: `HasApiTokens` e `isAdmin()` comparando `role === 'admin'`.
- `backend/bootstrap/app.php`: não habilita `statefulApi()`.
- `backend/routes/api.php`: `auth:sanctum` nas rotas autenticadas e middleware administrativo nas rotas de administração.
- A rota `/sanctum/csrf-cookie` existe no provider do Sanctum, mas emiti-la não autentica um usuário. Não foi adicionado um fluxo de sessão que o backend não implementa.

No Swagger: execute `/api/login` ou `/api/register`, copie `token` da resposta e use **Authorize**. Informe apenas o token, sem escrever `Bearer`. Para administração, faça login com uma conta que já tenha role `admin`; o cadastro público sempre cria `customer`.

A UI preserva credenciais do mesmo host e envia `Accept: application/json`. Não persiste a autorização após recarregar a página, não chama validador remoto e não utiliza CDN. O token visitante é informado no parâmetro `X-Guest-Cart-Token` das operações correspondentes; é diferente do token de login.

## Arquivos a copiar

O ZIP de atualização contém apenas estes arquivos, com caminhos relativos à raiz PetGo:

| Arquivo | Ação | Finalidade |
| --- | --- | --- |
| `backend/Dockerfile` | Substituir | Copia o Caddyfile para `/etc/caddy/Caddyfile` na imagem da API. |
| `backend/Caddyfile` | Criar | Serve documentação como arquivos estáticos e mantém as demais requisições no `php_server`. |
| `backend/public/api/docs/index.html` | Criar | Página HTML sem Blade, com URLs absolutas dos assets locais. |
| `backend/public/api/docs/openapi.yaml` | Criar | Contrato OpenAPI auditado contra o código. |
| `backend/public/api/docs/assets/swagger-initializer.js` | Criar | Carrega exatamente `/api/docs/openapi.yaml` e configura a UI. |
| `backend/public/api/docs/assets/swagger-ui.css` | Criar | Estilos oficiais locais. |
| `backend/public/api/docs/assets/swagger-ui-bundle.js` | Criar | Bundle oficial local. |
| `backend/public/api/docs/assets/swagger-ui-bundle.js.LICENSE.txt` | Criar | Avisos das dependências do bundle. |
| `backend/public/api/docs/assets/LICENSE` | Criar | Licença do Swagger UI. |
| `backend/public/api/docs/assets/NOTICE` | Criar | Avisos do Swagger UI. |
| `docs/swagger-implantacao.md` | Criar | Este guia e os resultados das verificações. |

A única alteração em arquivo existente é no Dockerfile, após `COPY . /app`:

```dockerfile
# Serve documentation directly from public, including /api/docs without a redirect.
COPY Caddyfile /etc/caddy/Caddyfile
```

O Caddyfile é necessário porque o `php_server` padrão pode encaminhar diretórios de documentação ao front controller Laravel, e o servidor estático pode redirecionar o caminho sem barra. O bloco estático específico impede que `/api/docs`, o YAML e os assets passem pelo Laravel; arquivos de documentação inexistentes retornam erro estático. Nenhuma rota Laravel foi criada para documentação.

Não foram alterados `frontend/`, proxy, Compose, controllers, services, middleware, migrations nem as rotas da aplicação. Os arquivos antigos `frontend/swagger.html`, `frontend/openapi.yaml` e `docs/openapi.yaml` permanecem como estavam; a documentação nova usa exclusivamente `/api/docs/openapi.yaml`.

## Aplicação no Windows

Extraia o ZIP de atualização **dentro de** `C:\Users\Helpdesk\projetos\PetGo`, mesclando as pastas `backend` e `docs`. Substitua apenas o Dockerfile quando solicitado. O ZIP não tem uma pasta PetGo extra envolvendo os arquivos.

No PowerShell:

```powershell
cd C:\Users\Helpdesk\projetos\PetGo
docker compose up -d --build api
```

O Compose copia os arquivos para a imagem; por isso é preciso reconstruir a API. Não é necessário executar migrations para esta atualização. O proxy existente deve estar rodando; se o ambiente inteiro estiver parado, use `docker compose up -d --build`.

Com a porta padrão do proxy:

- UI: `http://localhost/api/docs`
- YAML: `http://localhost/api/docs/openapi.yaml`

Se você configurou `PROXY_PORT`, use essa porta nos URLs, por exemplo `http://localhost:8080/api/docs`.

## Domínios e regras documentados

| Domínio | Acesso e comportamento |
| --- | --- |
| Saúde | Público; `GET /api/health`. |
| Autenticação | Cadastro/login públicos emitem `{user, token}`; `/me` e logout exigem Sanctum. Cadastro ignora tentativa de atribuir role; token de carrinho no corpo do cadastro é validado, mas não mesclado pelo serviço. Login pode mesclar visitante via header. |
| Produtos e imagens | Leitura pública somente de ativos; filtros reais por nome/categoria; 12 por página; imagem precisa de arquivo existente. Administração lista ativos/inativos, 15 por página, cria/atualiza e desativa (DELETE não exclui fisicamente). |
| Categorias | Consulta pública, 12 por página. Administração cria/atualiza/exclui; exclusão com qualquer produto associado retorna 409. |
| Carrinho visitante | Criação pública; demais operações exigem token visitante de 64 caracteres. Limites throttle 10/min na criação e 60/min nas demais. |
| Carrinho autenticado | Qualquer usuário autenticado opera no próprio carrinho ativo. Quantidade acumulada máxima 20, produto ativo e estoque suficiente. GET pode criar o carrinho. |
| Endereços | Apenas do proprietário; recurso alheio retorna 404; sem paginação. PUT usa as mesmas regras obrigatórias do POST. Primeiro endereço é principal; promoção/exclusão seguem as regras do serviço. |
| Checkout | Usuário autenticado, endereço próprio e UUID de idempotência. Preços recalculados, frete zero, baixa de estoque e transação. Repetição válida retorna o pedido existente ainda com 201. 10/min; conflitos de chave retornam 409. |
| Pedidos do usuário | Apenas próprios, inclusive quando o usuário é administrador. Lista com 10 por página. Cancelamento somente pendente com pagamento compatível; devolve estoque uma vez. Histórico sem actor/source. |
| Administração de pedidos | Role exatamente admin; consulta qualquer pedido, 15 por página; confirmação simulada de pagamento; avanço processing → shipped → delivered com pagamento confirmado; histórico inclui actor/source. |
| Estoque | Role admin; consulta movimentos por produto, 20 por página. |
| CSRF do Sanctum | Infraestrutura pública; emite cookies, não realiza login nem substitui Bearer nesta versão. |

As respostas de Resources usam `data`, com `links`/`meta` apenas quando há paginação. Exceções sem envelope externo: autenticação `{user, token}`, `/me` `{user}`, criação de visitante `{cart, guest_cart_token}` e checkout `{message, order}`. Itens de pedido são snapshots e não incluem objeto `product`. Campos condicionais, validações, erros, status e efeitos dos serviços estão descritos no YAML.

## Verificações executadas em 09/11/2026

O ambiente de trabalho não tem Docker nem acesso ao proxy rodando no seu Windows. Para testar HTTP de verdade, foi executado um Nginx com as regras do `proxy/default.conf`, mudando **somente no ambiente de teste** os endereços dos serviços para processos locais e as portas. O backend foi executado em FrankenPHP/Caddy com o Caddyfile entregue. Não foi usado um servidor estático genérico no lugar da API. As alterações de endereços/portas do teste não integram o ZIP.

Ambiente: Nginx 1.24.0, FrankenPHP 1.13.5 / PHP 8.5.12 / Caddy 2.11.9. Dependências PHP instaladas a partir do composer.lock existente, sem atualizá-lo. Testes de negócio usaram SQLite isolado e dados descartáveis; não acessaram seu banco MySQL.

| Verificação | Resultado |
| --- | --- |
| `php artisan route:list --json` + leitura dos arquivos de rotas/código | 44 operações `/api/...` documentadas; nenhuma ausente ou inventada. HEAD implícito não foi duplicado no YAML. |
| Validação estrutural OpenAPI 3.0.3 | Passou com openapi-spec-validator. |
| `GET /api/docs` através do Nginx | 200, `text/html; charset=utf-8`, conteúdo HTML correto, sem redirecionamento e sem autenticação. |
| `GET /api/docs/` e `/api/docs/index.html` | 200 HTML. |
| `GET /api/docs/openapi.yaml` através do Nginx | 200, corpo YAML parseado e validado como OpenAPI; não retornou HTML. |
| Assets locais CSS, bundle e inicializador | Todos 200. |
| Navegador Chromium real | 45 operações renderizadas; especificação carregada com 200; nenhum erro JavaScript/HTTP e nenhuma requisição externa. |
| Try it out no navegador | `GET /api/health` → 200, JSON com app PetGo. |
| Requisições HTTP e schemas das respostas | 67 verificações aprovadas, incluindo arquivos estáticos, autenticação, restrição admin, carrinhos, endereços, checkout/idempotência, pedidos, cancelamento, pagamento, logística e histórico/estoque. |

Limite: o Compose original e o proxy do seu computador ainda precisam ser verificados após copiar e reconstruir a imagem. A validação acima reproduz as regras de encaminhamento existentes; não é uma execução do Docker Compose original nem um teste de MySQL em contêiner.

Para confirmar no seu proxy (ajuste a porta se necessário):

```powershell
curl.exe -i http://localhost/api/docs
curl.exe -i http://localhost/api/docs/openapi.yaml
curl.exe -i http://localhost/api/docs/assets/swagger-ui-bundle.js
```

Confirme status 200 em todos, HTML na primeira resposta e YAML começando com `openapi: 3.0.3` na segunda. Abra a UI, confira que não há erro ao carregar a definição e execute `GET /api/health` pelo Try it out. Para operações autenticadas, use Authorize com o token de login; operações de escrita modificam os dados do seu ambiente.

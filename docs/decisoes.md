# Decisões técnicas — Estante Livre

## Arquitetura
- Frontend independente em HTML, CSS e JavaScript.
- Comunicação com o backend por API REST, usando JSON.
- Backend em Laravel, com MVC e camada Service.
- Autenticação por tokens usando Laravel Sanctum.
- Banco de dados MySQL.

## Regras do projeto
- Não utilizar Blade no frontend.
- Não processar pagamentos reais nem dados de cartão.
- Validar regras de negócio no backend.
- Priorizar baixo custo na futura hospedagem AWS.

## Docker
- Um compose.yaml na raiz do repositório.
- Serviços separados para frontend, API e banco de dados.
- Confirmar a exigência de backend/Dockerfile.dev do briefing:
  o arquivo de desenvolvimento deve ser considerado adicional
  caso a banca exija literalmente os dois Dockerfiles do backend.

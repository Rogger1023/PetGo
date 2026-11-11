#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

usage() {
  cat <<'USAGE'
Uso: bash scripts/setup.sh [--seed | --no-seed]

  (sem opcao) Semeia dados somente quando cria um novo .env.
  --seed      Executa o DatabaseSeeder mesmo se .env ja existia.
  --no-seed   Nao executa os seeders.

Para desenvolvimento local (WSL2/Linux + Docker Compose).
Nao remove volumes, nao apaga banco e nao sobrescreve .env existente.
USAGE
}

seed_mode=auto
for arg in "$@"; do
  case "$arg" in
    --seed) seed_mode=yes ;;
    --no-seed) seed_mode=no ;;
    -h|--help) usage; exit 0 ;;
    *) printf 'Argumento desconhecido: %s\n' "$arg" >&2; usage >&2; exit 2 ;;
  esac
done

for command in docker openssl sed grep; do
  if ! command -v "$command" >/dev/null 2>&1; then
    printf 'Erro: comando obrigatorio nao encontrado: %s\n' "$command" >&2
    exit 1
  fi
done
if ! docker compose version >/dev/null 2>&1; then
  echo 'Erro: Docker Compose V2 indisponivel (docker compose).' >&2
  exit 1
fi
if ! docker info >/dev/null 2>&1; then
  echo 'Erro: Docker nao esta acessivel. Inicie o Docker Desktop/Engine.' >&2
  exit 1
fi
if [[ ! -f compose.yaml || ! -f .env.example ]]; then
  echo 'Erro: compose.yaml e .env.example devem existir na raiz do PetGo.' >&2
  exit 1
fi

# Usado somente para um .env NOVO; nao reconfigura credenciais existentes.
set_env() {
  local key="$1" value="$2"
  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

new_env=false
if [[ ! -e .env ]]; then
  echo '[1/6] Criando .env local a partir de .env.example...'
  (umask 077; cp .env.example .env)
  chmod 600 .env
  set_env APP_ENV local
  set_env APP_DEBUG true
  set_env APP_KEY "base64:$(openssl rand -base64 32 | tr -d '\n')"
  set_env DB_PASSWORD "$(openssl rand -hex 24)"
  set_env DB_ROOT_PASSWORD "$(openssl rand -hex 24)"
  new_env=true
  echo 'Chave Laravel e senhas locais geradas. .env nao deve ser versionado.'
else
  echo '[1/6] .env existente encontrado: sera preservado sem alteracoes.'
fi

# Esta rotina nao deve alterar uma instancia de producao.
if grep -Eq "^APP_ENV=['\"]?production['\"]?$" .env; then
  echo 'Erro: APP_ENV=production. Este script e exclusivo de desenvolvimento local.' >&2
  exit 1
fi

echo '[2/6] Validando compose.yaml e variaveis de ambiente...'
docker compose -f compose.yaml config --quiet

echo '[3/6] Construindo imagens Docker...'
docker compose -f compose.yaml build

echo '[4/6] Iniciando MySQL e aguardando seu healthcheck...'
docker compose -f compose.yaml up -d --wait db

echo '[5/6] Executando migrations pendentes (sem apagar tabelas)...'
docker compose -f compose.yaml run --rm api php artisan migrate --no-interaction

run_seed=false
case "$seed_mode" in
  yes) run_seed=true ;;
  no) run_seed=false ;;
  auto) [[ "$new_env" == true ]] && run_seed=true ;;
esac
if [[ "$run_seed" == true ]]; then
  echo 'Populando banco local com DatabaseSeeder (dados demonstrativos)...'
  docker compose -f compose.yaml run --rm api php artisan db:seed --class=DatabaseSeeder --no-interaction
else
  echo 'Seeders nao executados (use --seed para executar explicitamente).'
fi

echo '[6/6] Iniciando frontend, API e proxy...'
docker compose -f compose.yaml up -d --no-build

echo
echo '=== Estado dos containers ==='
docker compose -f compose.yaml ps
port=$(sed -n 's/^PROXY_PORT=//p' .env | tail -n 1)
port=${port:-80}
printf '\nPetGo: http://localhost:%s/\nSwagger: http://localhost:%s/api/docs\n' "$port" "$port"
echo 'Se precisar conferir logs: docker compose logs --tail=80 api proxy db'

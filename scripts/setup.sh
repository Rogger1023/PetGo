
#!/usr/bin/env bash

# PetGo - Instalador automatico para WSL2/Linux
# Uso: bash scripts/setup.sh [--seed | --no-seed]

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd "$ROOT_DIR"

usage() {
  cat <<'HELP'
Uso: bash scripts/setup.sh [--seed | --no-seed]

Sem opcao:
  Executa seeders somente na primeira instalacao.

--seed:
  Executa os seeders mesmo com .env existente.

--no-seed:
  Nao executa os seeders.

O instalador:
  - Isola cada clone usando um projeto Docker Compose proprio.
  - Seleciona uma porta HTTP disponivel.
  - Limpa somente containers e redes deste clone.
  - Preserva volumes e dados existentes.
  - Configura o Laravel e inicia o PetGo.

Nao utilize este script em producao.
HELP
}

# ==========================================
# 1. Argumentos
# ==========================================

seed_mode=auto

for arg in "$@"; do
  case "$arg" in
    --seed)
      seed_mode=yes
      ;;
    --no-seed)
      seed_mode=no
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Argumento desconhecido: $arg" >&2
      usage >&2
      exit 2
      ;;
  esac
done

# ==========================================
# 2. Verificar requisitos
# ==========================================

for cmd in docker openssl python3 sed grep; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    echo "Falta instalar: $cmd" >&2
    exit 1
  fi
done

if ! docker compose version >/dev/null 2>&1; then
  echo "Docker Compose V2 indisponivel." >&2
  exit 1
fi

if ! docker info >/dev/null 2>&1; then
  echo "Docker Engine indisponivel. Inicie Docker Desktop." >&2
  exit 1
fi

if [[ ! -f compose.yaml || ! -f .env.example ]]; then
  echo "Arquivos compose.yaml / .env.example ausentes." >&2
  exit 1
fi

# ==========================================
# 3. Criar identificador do projeto
# ==========================================

# Cada clone recebe um projeto Compose diferente.
# Isso evita reutilizar acidentalmente volumes
# e containers pertencentes a outro clone.

project_hash="$(
  printf '%s' "$ROOT_DIR" |
    openssl dgst -sha256 |
    sed 's/^.*= //' |
    cut -c1-10
)"

project="petgo_${project_hash}"

# ==========================================
# 4. Configurar .env
# ==========================================

new_env=false

if [[ ! -f .env ]]; then
  echo '[1/7] Criando .env local...'

  (
    umask 077
    cp .env.example .env
  )

  chmod 600 .env
  new_env=true
else
  echo '[1/7] Mantendo .env existente.'
fi

# Atualiza uma variavel no .env.
set_env() {
  local key="$1"
  local value="$2"

  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '\n%s=%s\n' "$key" "$value" >> .env
  fi
}

# Le uma variavel do .env.
read_env() {
  local key="$1"

  sed -n "s/^${key}=//p" .env |
    tail -n 1 |
    tr -d '\r\"'
}

# Protecao contra uso em producao.
if [[ "$(read_env APP_ENV)" == production ]]; then
  echo "ERRO: APP_ENV=production." >&2
  echo "Instalador exclusivo para ambiente local." >&2
  exit 1
fi

# Na primeira instalacao, gera as credenciais.
if [[ "$new_env" == true ]]; then

  set_env APP_ENV local
  set_env APP_DEBUG true

  set_env APP_KEY \
    "base64:$(openssl rand -base64 32 | tr -d '\n')"

  set_env DB_PASSWORD \
    "$(openssl rand -hex 24)"

  set_env DB_ROOT_PASSWORD \
    "$(openssl rand -hex 24)"
fi

# Verifica configuracoes obrigatorias.
for key in APP_KEY DB_PASSWORD DB_ROOT_PASSWORD; do
  if [[ -z "$(read_env "$key")" ]]; then
    echo "ERRO: $key ausente no .env." >&2
    exit 1
  fi
done

# ==========================================
# 5. Isolar o container proxy
# ==========================================

# O compose.yaml original utiliza:
#
# container_name: proxy
#
# Esse nome fixo impede multiplos clones de
# executarem simultaneamente.
#
# Um override temporario resolve o problema
# sem modificar o compose.yaml versionado.

override="$(
  mktemp "${TMPDIR:-/tmp}/petgo-setup-XXXXXXXX.yaml"
)"

trap 'rm -f "$override"' EXIT

cat > "$override" <<YAML
services:
  proxy:
    container_name: ${project}-proxy
YAML

compose=(
  docker compose
  -p "$project"
  -f "$ROOT_DIR/compose.yaml"
  -f "$override"
)

echo "[2/7] Projeto Compose isolado: $project"

"${compose[@]}" config --quiet

# ==========================================
# 6. Limpeza segura do Docker
# ==========================================

echo '[3/7] Limpando containers anteriores deste clone...'

# Remove apenas containers e redes deste projeto.
# NAO remove volumes do banco de dados.
#
# Importante:
# Nao utilizar docker system prune --volumes
# ou docker compose down -v.

"${compose[@]}" down --remove-orphans

# ==========================================
# 7. Detectar porta HTTP disponivel
# ==========================================

# Prioridade:
# 1. PROXY_PORT existente no .env
# 2. Porta 80 (se nao configurada)
# 3. Primeira porta livre entre 8080 e 8999

preferred="$(read_env PROXY_PORT)"
preferred="${preferred:-80}"

port="$(
  PREFERRED_PORT="$preferred" python3 - <<'PY'
import json
import os
import socket
import subprocess
import sys

# Detecta portas publicadas por containers
# Docker que ja estao em execucao.

used_by_docker = set()

ids = subprocess.run(
    ['docker', 'ps', '-q'],
    check=True,
    capture_output=True,
    text=True
).stdout.split()

if ids:
    rows = json.loads(
        subprocess.run(
            ['docker', 'inspect', *ids],
            check=True,
            capture_output=True,
            text=True
        ).stdout
    )

    for row in rows:
        ports = (
            row.get('NetworkSettings', {}).get('Ports')
            or {}
        )

        for bindings in ports.values():
            for binding in bindings or []:
                try:
                    used_by_docker.add(
                        int(binding.get('HostPort', 0))
                    )
                except ValueError:
                    pass


def free(port):
    if not 1 <= port <= 65535:
        return False

    if port in used_by_docker:
        return False

    addresses = (
        (socket.AF_INET, '0.0.0.0'),
        (socket.AF_INET6, '::')
    )

    for family, address in addresses:
        try:
            with socket.socket(
                family,
                socket.SOCK_STREAM
            ) as sock:

                if family == socket.AF_INET6:
                    sock.setsockopt(
                        socket.IPPROTO_IPV6,
                        socket.IPV6_V6ONLY,
                        1
                    )

                sock.bind((address, port))

        except OSError as error:
            if (
                family == socket.AF_INET6
                and error.errno in (97, 93)
            ):
                continue

            return False

    return True


try:
    preferred = int(
        os.environ['PREFERRED_PORT']
    )
except ValueError:
    preferred = 80

candidates = dict.fromkeys(
    [preferred, *range(8080, 9000)]
)

for candidate in candidates:
    if free(candidate):
        print(candidate)
        sys.exit(0)

sys.exit(
    'ERRO: nenhuma porta HTTP livre encontrada.'
)
PY
)"

if [[ "$port" != "$preferred" ]]; then
  echo "Porta $preferred ocupada. Usando porta $port."

  set_env PROXY_PORT "$port"
fi

export PROXY_PORT="$port"

"${compose[@]}" config --quiet

# ==========================================
# 8. Construir imagens Docker
# ==========================================

echo '[4/7] Construindo imagens...'

"${compose[@]}" build

# ==========================================
# 9. Inicializar MySQL
# ==========================================

echo '[5/7] Inicializando MySQL...'

"${compose[@]}" up -d --wait db

# ==========================================
# 10. Executar migrations
# ==========================================

echo '[6/7] Aplicando migrations...'

if ! "${compose[@]}" run --rm api \
  php artisan migrate --no-interaction
then
  echo "ERRO durante a migration." >&2
  echo "Se for erro 1045, verifique as credenciais" >&2
  echo "armazenadas no volume MySQL deste clone." >&2
  echo "Nenhum dado foi removido automaticamente." >&2
  exit 1
fi

# ==========================================
# 11. Executar seeders
# ==========================================

run_seed=false

case "$seed_mode" in
  yes)
    run_seed=true
    ;;
  no)
    run_seed=false
    ;;
  auto)
    if [[ "$new_env" == true ]]; then
      run_seed=true
    fi
    ;;
esac

if [[ "$run_seed" == true ]]; then

  echo 'Executando DatabaseSeeder...'

  "${compose[@]}" run --rm api \
    php artisan db:seed \
    --class=DatabaseSeeder \
    --no-interaction
fi

# ==========================================
# 12. Iniciar aplicacao
# ==========================================

echo '[7/7] Subindo todos os servicos...'

"${compose[@]}" up -d --no-build

# Exibe o estado dos containers.
"${compose[@]}" ps

# ==========================================
# 13. Finalizacao
# ==========================================

echo ""
echo "Instalacao concluida!"
echo ""
echo "PetGo:"
echo "http://localhost:${port}/"
echo ""
echo "Swagger:"
echo "http://localhost:${port}/api/docs"
echo ""
echo "Projeto Docker Compose: $project"
echo ""
echo "Para consultar logs:"
echo "docker compose -p $project -f compose.yaml logs --tail=80"

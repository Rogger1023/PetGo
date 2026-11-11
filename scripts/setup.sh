
#!/usr/bin/env bash

# ==========================================================
# PetGo - Setup automatico para WSL2 / Linux
# ==========================================================
#
# Uso:
#   bash scripts/setup.sh
#   bash scripts/setup.sh --seed
#   bash scripts/setup.sh --no-seed
#
# Nao usar em producao.
# Nao remove volumes nem dados existentes.
# ==========================================================

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd "$ROOT_DIR"

SEED_MODE="auto"
DB_TIMEOUT=1200
DB_INTERVAL=10

log() {
    printf '\n[PetGo] %s\n' "$*"
}

fail() {
    printf '\n[ERRO] %s\n' "$*" >&2
    exit 1
}

usage() {
    cat <<'EOF'
Uso: bash scripts/setup.sh [opcao]

--seed       Executa os seeders.
--no-seed    Nao executa os seeders.
--help       Exibe a ajuda.

Sem opcao, executa seeders somente quando o
arquivo .env for criado pela primeira vez.

O instalador preserva os volumes existentes.
EOF
}

# ==========================================================
# 1. Argumentos e requisitos
# ==========================================================

for ARG in "$@"; do
    case "$ARG" in
        --seed) SEED_MODE="yes" ;;
        --no-seed) SEED_MODE="no" ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            fail "Argumento desconhecido: $ARG"
            ;;
    esac
done

log "[1/7] Verificando requisitos..."

for CMD in docker openssl python3 sed grep; do
    command -v "$CMD" >/dev/null 2>&1 ||
        fail "Comando nao encontrado: $CMD"
done

docker compose version >/dev/null 2>&1 ||
    fail "Docker Compose V2 nao encontrado."

docker info >/dev/null 2>&1 ||
    fail "Docker nao esta funcionando."

[[ -f compose.yaml ]] ||
    fail "compose.yaml nao encontrado."

[[ -f .env.example ]] ||
    fail ".env.example nao encontrado."

# ==========================================================
# 2. Configurar .env
# ==========================================================

log "[2/7] Configurando .env..."

NEW_ENV=false

if [[ ! -f .env ]]; then
    (
        umask 077
        cp .env.example .env
    )
    NEW_ENV=true
    echo "Novo .env criado."
else
    echo ".env existente preservado."
fi

read_env() {
    local KEY="$1"

    sed -n "s/^${KEY}=//p" .env |
        tail -n 1 |
        tr -d '\r"'
}

set_env() {
    local KEY="$1"
    local VALUE="$2"

    if grep -q "^${KEY}=" .env; then
        sed -i "s|^${KEY}=.*|${KEY}=${VALUE}|" .env
    else
        printf '%s=%s\n' "$KEY" "$VALUE" >> .env
    fi
}

if [[ "$(read_env APP_ENV)" == "production" ]]; then
    fail "Este instalador nao pode rodar em producao."
fi

if [[ "$NEW_ENV" == true ]]; then

    set_env APP_ENV "local"
    set_env APP_DEBUG "true"

    GENERATED_KEY="$(
        openssl rand -base64 32 | tr -d '\n'
    )"

    set_env APP_KEY "base64:${GENERATED_KEY}"

    set_env DB_PASSWORD "$(openssl rand -hex 24)"
    set_env DB_ROOT_PASSWORD "$(openssl rand -hex 24)"

    echo "Credenciais locais geradas."
fi

for KEY in APP_KEY DB_PASSWORD DB_ROOT_PASSWORD; do
    [[ -n "$(read_env "$KEY")" ]] ||
        fail "$KEY nao configurado no .env."
done

# ==========================================================
# 3. Isolar este clone no Docker
# ==========================================================

# Identificador baseado no caminho do clone.
# Cada diretorio recebe seu proprio projeto Compose.

PROJECT_HASH="$(
    printf '%s' "$ROOT_DIR" |
        openssl dgst -sha256 |
        sed 's/^.*= //' |
        cut -c1-10
)"

PROJECT="petgo_${PROJECT_HASH}"

# Override temporario para evitar conflito com
# o container_name: proxy do compose.yaml original.

OVERRIDE="$(mktemp /tmp/petgo-setup-XXXXXXXX.yaml)"

cleanup() {
    rm -f "$OVERRIDE"
}

trap cleanup EXIT

cat > "$OVERRIDE" <<EOF
services:
  proxy:
    container_name: ${PROJECT}-proxy
EOF

COMPOSE=(
    docker compose
    -p "$PROJECT"
    -f "$ROOT_DIR/compose.yaml"
    -f "$OVERRIDE"
)

log "[3/7] Projeto Docker: $PROJECT"

# ==========================================================
# 4. Selecionar uma porta HTTP disponivel
# ==========================================================

PREFERRED_PORT="$(read_env PROXY_PORT)"
PREFERRED_PORT="${PREFERRED_PORT:-80}"

# Verifica tanto sockets do sistema quanto portas
# ja publicadas por outros containers Docker.

PORT="$(
    PREFERRED_PORT="$PREFERRED_PORT" python3 - <<'PY'
import json
import os
import socket
import subprocess
import sys

used = set()

result = subprocess.run(
    ["docker", "ps", "-q"],
    capture_output=True,
    text=True,
    check=True
)

ids = result.stdout.split()

if ids:
    data = json.loads(
        subprocess.run(
            ["docker", "inspect", *ids],
            capture_output=True,
            text=True,
            check=True
        ).stdout
    )

    for container in data:
        ports = (
            container.get("NetworkSettings", {})
            .get("Ports") or {}
        )

        for bindings in ports.values():
            for binding in bindings or []:
                try:
                    used.add(int(binding["HostPort"]))
                except (KeyError, ValueError):
                    pass

def is_available(port):
    if not 1 <= port <= 65535:
        return False

    if port in used:
        return False

    for family, address in [
        (socket.AF_INET, "0.0.0.0"),
        (socket.AF_INET6, "::")
    ]:
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
        os.environ["PREFERRED_PORT"]
    )
except ValueError:
    preferred = 80

candidates = dict.fromkeys(
    [preferred, *range(8080, 9000)]
)

for candidate in candidates:
    if is_available(candidate):
        print(candidate)
        sys.exit(0)

sys.exit("Nenhuma porta HTTP disponivel.")
PY
)"

export PROXY_PORT="$PORT"

# Grava a porta efetiva, facilitando execucoes futuras.
set_env PROXY_PORT "$PORT"

if [[ "$PORT" != "$PREFERRED_PORT" ]]; then
    echo "Porta $PREFERRED_PORT ocupada."
fi

echo "Porta HTTP selecionada: $PORT"

"${COMPOSE[@]}" config --quiet ||
    fail "Configuracao Docker Compose invalida."

# ==========================================================
# 5. Construir imagens
# ==========================================================

log "[4/7] Construindo imagens Docker..."

"${COMPOSE[@]}" build ||
    fail "Falha ao construir as imagens."

# ==========================================================
# 6. Inicializar MySQL sem --wait
# ==========================================================

log "[5/7] Inicializando MySQL..."

# Nao usamos --wait.
# O MySQL 8.4 pode levar varios minutos para
# inicializar um volume totalmente novo.

"${COMPOSE[@]}" up -d --no-build db ||
    fail "Nao foi possivel iniciar o MySQL."

DB_CONTAINER="$(
    "${COMPOSE[@]}" ps -q db
)"

[[ -n "$DB_CONTAINER" ]] ||
    fail "Container MySQL nao encontrado."

echo "Aguardando MySQL aceitar conexoes..."
echo "Limite: ${DB_TIMEOUT} segundos."

START_TIME=$SECONDS
DB_READY=false

while (( SECONDS - START_TIME < DB_TIMEOUT )); do

    # Confirma se o container ainda esta em execucao.
    RUNNING="$(
        docker inspect "$DB_CONTAINER" \
            --format '{{.State.Running}}' 2>/dev/null ||
            echo false
    )"

    if [[ "$RUNNING" != "true" ]]; then
        echo "O container MySQL encerrou."
        break
    fi

    # Consulta SQL com o usuario da aplicacao.
    # Assim verificamos conexao, senha e permissoes.
    if docker exec "$DB_CONTAINER" sh -c '
        MYSQL_PWD="$MYSQL_PASSWORD" \
        mysql \
          -h 127.0.0.1 \
          -u "$MYSQL_USER" \
          "$MYSQL_DATABASE" \
          -Nse "SELECT 1" \
          >/dev/null 2>&1
    ' 2>/dev/null; then

        DB_READY=true
        break
    fi

    ELAPSED=$((SECONDS - START_TIME))

    printf '[MySQL] Inicializando... %s/%ss\n' \
        "$ELAPSED" "$DB_TIMEOUT"

    sleep "$DB_INTERVAL"
done

if [[ "$DB_READY" != true ]]; then

    echo ""
    echo "========== LOGS MYSQL =========="

    docker logs --tail 100 "$DB_CONTAINER" || true

    echo "================================"
    echo ""

    fail "MySQL nao ficou acessivel dentro do prazo."
fi

echo "MySQL aceitando conexoes com petgo_app."

# Verifica tambem o healthcheck.
HEALTH="$(
    docker inspect "$DB_CONTAINER" \
        --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}unknown{{end}}'
)"

echo "Healthcheck atual: $HEALTH"

# ==========================================================
# 7. Executar migrations
# ==========================================================

log "[6/7] Executando migrations..."

"${COMPOSE[@]}" run --rm api \
    php artisan migrate --no-interaction ||
    fail "Erro ao executar migrations."

# ==========================================================
# 8. Executar seeders
# ==========================================================

RUN_SEED=false

case "$SEED_MODE" in
    yes)
        RUN_SEED=true
        ;;
    no)
        RUN_SEED=false
        ;;
    auto)
        if [[ "$NEW_ENV" == true ]]; then
            RUN_SEED=true
        fi
        ;;
esac

if [[ "$RUN_SEED" == true ]]; then

    echo "Executando seeders do PetGo..."

    "${COMPOSE[@]}" run --rm api \
        php artisan db:seed \
        --class=DatabaseSeeder \
        --no-interaction ||
        fail "Erro ao executar seeders."

else
    echo "Seeders ignorados nesta execucao."
fi

# ==========================================================
# 9. Iniciar os quatro servicos
# ==========================================================

log "[7/7] Iniciando o PetGo..."

"${COMPOSE[@]}" up -d --no-build ||
    fail "Erro ao iniciar os containers."

echo ""
echo "========== CONTAINERS =========="
"${COMPOSE[@]}" ps
echo "================================"
echo ""

# ==========================================================
# 10. Finalizacao
# ==========================================================

echo "======================================="
echo " PetGo configurado com sucesso!"
echo "======================================="
echo ""
echo "Loja:"
echo "http://localhost:${PORT}/"
echo ""
echo "Painel administrativo:"
echo "http://localhost:${PORT}/admin/"
echo ""
echo "Swagger:"
echo "http://localhost:${PORT}/api/docs"
echo ""
echo "Projeto Compose: $PROJECT"
echo ""
echo "Para consultar logs do banco:"
echo "docker logs --tail 100 $DB_CONTAINER"
echo ""
echo "Nenhum volume foi apagado."

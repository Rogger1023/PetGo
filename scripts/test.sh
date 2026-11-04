#!/usr/bin/env bash

set -e

echo "===================================="
echo " Executando testes do PetGo"
echo " Banco: SQLite em memoria"
echo "===================================="

docker compose exec -T \
    -e APP_ENV=testing \
    -e DB_CONNECTION=sqlite \
    -e DB_DATABASE=:memory: \
    -e DB_URL= \
    api ./vendor/bin/phpunit --display-warnings

echo ""
echo "Testes finalizados com sucesso!"

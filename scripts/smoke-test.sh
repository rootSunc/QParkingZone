#!/usr/bin/env sh
set -eu

ROOT_DIR=$(
    CDPATH=
    cd -- "$(dirname -- "$0")/.." && pwd
)

PROJECT_NAME=${QPARKING_SMOKE_PROJECT:-qparking-smoke}
COMPOSE_FILES="-f $ROOT_DIR/infra/docker/docker-compose.yml -f $ROOT_DIR/infra/docker/docker-compose.smoke.yml"

compose() {
    # shellcheck disable=SC2086
    docker compose -p "$PROJECT_NAME" $COMPOSE_FILES "$@"
}

cleanup() {
    compose down -v --remove-orphans >/dev/null 2>&1 || true
}

wait_for_url() {
    url=$1
    label=$2
    attempts=${3:-30}

    while [ "$attempts" -gt 0 ]; do
        if curl -fsS "$url" >/dev/null 2>&1; then
            return 0
        fi

        attempts=$((attempts - 1))
        sleep 2
    done

    echo "Timed out waiting for $label at $url" >&2
    return 1
}

trap cleanup EXIT INT TERM

compose up -d --build

wait_for_url "http://127.0.0.1:18000/api/health" "backend health"
wait_for_url "http://127.0.0.1:15173/" "frontend"

health=$(curl -fsS "http://127.0.0.1:18000/api/health")
zones=$(curl -fsS "http://127.0.0.1:15173/api/zones?city=helsinki&limit=1")
facets=$(curl -fsS "http://127.0.0.1:15173/api/zones/facets?city=helsinki")

printf '%s\n' "$health" | grep -q '"status":"ok"'
printf '%s\n' "$health" | grep -q '"database":"ok"'
printf '%s\n' "$zones" | grep -q '"items"'
printf '%s\n' "$facets" | grep -q '"amenities"'

echo "Smoke test passed"


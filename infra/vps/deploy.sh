#!/usr/bin/env sh
set -eu

APP_DIR=${QPARKING_APP_DIR:-}
BRANCH=${QPARKING_BRANCH:-main}
COMPOSE_FILE=${QPARKING_COMPOSE_FILE:-infra/docker/docker-compose.prod.yml}
REPO_OWNER=${QPARKING_REPO_OWNER:-rootSunc}
REPO_NAME=${QPARKING_REPO_NAME:-QParkingZone}
WORKFLOW_NAME=${QPARKING_WORKFLOW_NAME:-CI}
REQUIRE_CI=${QPARKING_REQUIRE_CI:-true}
LOCK_DIR=${QPARKING_LOCK_DIR:-/tmp/qparking-zone-deploy.lock}
STATE_FILE=${QPARKING_STATE_FILE:-/var/lib/qparking-zone/deployed-sha}

log() {
    printf '%s %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$*"
}

need_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        log "Missing required command: $1"
        exit 1
    fi
}

script_dir() {
    (
        CDPATH=
        cd -- "$(dirname -- "$0")" && pwd
    )
}

repo_root_from_script() {
    (
        CDPATH=
        cd -- "$(script_dir)/../.." && pwd
    )
}

ci_succeeded_for_sha() {
    sha=$1
    api_url="https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/actions/runs?branch=$BRANCH&event=push&head_sha=$sha&per_page=10"

    runs_json=$(
        curl -fsSL \
            -H 'Accept: application/vnd.github+json' \
            "$api_url"
    )

    run_state=$(
        printf '%s\n' "$runs_json" |
        jq -r --arg sha "$sha" --arg name "$WORKFLOW_NAME" '
            [
              .workflow_runs[]
            | select(.head_sha == $sha and .name == $name)
            | "\(.status) \(.conclusion)"
            ][0] // ""
        '
    )

    if [ "$run_state" = "completed success" ]; then
        return 0
    fi

    if [ -z "$run_state" ]; then
        log "No successful $WORKFLOW_NAME run found yet for $sha"
    else
        log "$WORKFLOW_NAME run for $sha is not deployable yet: $run_state"
    fi

    return 1
}

if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    log "Deployment already running, skipping"
    exit 0
fi
trap 'rmdir "$LOCK_DIR"' EXIT INT TERM

need_command git
need_command docker

if [ "$REQUIRE_CI" = "true" ]; then
    need_command curl
    need_command jq
fi

if [ -z "$APP_DIR" ]; then
    APP_DIR=$(repo_root_from_script)
fi

cd "$APP_DIR"

log "Fetching origin/$BRANCH"
git fetch --prune origin "$BRANCH"

remote_sha=$(git rev-parse "origin/$BRANCH")
last_deployed=""
if [ -f "$STATE_FILE" ]; then
    last_deployed=$(cat "$STATE_FILE")
fi

if [ "$last_deployed" = "$remote_sha" ]; then
    log "Already deployed $remote_sha"
    exit 0
fi

if [ "$REQUIRE_CI" = "true" ] && ! ci_succeeded_for_sha "$remote_sha"; then
    exit 0
fi

log "Deploying $remote_sha"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

deployed_sha=$(git rev-parse HEAD)
if [ "$deployed_sha" != "$remote_sha" ]; then
    log "Pulled $deployed_sha, expected $remote_sha"
    exit 1
fi

docker compose -f "$COMPOSE_FILE" up -d --build --remove-orphans
docker compose -f "$COMPOSE_FILE" ps

mkdir -p "$(dirname -- "$STATE_FILE")"
printf '%s\n' "$deployed_sha" > "$STATE_FILE"

log "Deployment complete at $deployed_sha"

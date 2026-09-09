#!/usr/bin/env bash

# Deploy Hi.Events on the Raspberry Pi.
# Run this script from any directory on the Pi:
#   /home/xuheng/apps/hi-events/docker/all-in-one/deploy.sh

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
BRANCH="${BRANCH:-develop}"
HEALTH_URL="${HEALTH_URL:-http://localhost:8123}"
COMPOSE_FILE="${COMPOSE_FILE:-${SCRIPT_DIR}/docker-compose.rpi.yml}"

if [[ ! -f "${COMPOSE_FILE}" ]]; then
    COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.yml"
fi

if [[ ! -f "${COMPOSE_FILE}" ]]; then
    echo "Compose file not found: ${COMPOSE_FILE}" >&2
    exit 1
fi

# Compose generates a build image as <project>-all-in-one when no explicit
# `image:` field exists. Use that same tag so `up --no-build` starts the image
# created above. IMAGE_TAG remains available as an override for custom setups.
COMPOSE_PROJECT_NAME="$(docker compose --file "${COMPOSE_FILE}" config 2>/dev/null | awk '/^name:/ { print $2; exit }')"
IMAGE_TAG="${IMAGE_TAG:-${COMPOSE_PROJECT_NAME:-all-in-one}-all-in-one:latest}"

echo "Updating ${BRANCH}..."
git -C "${PROJECT_ROOT}" pull --ff-only origin "${BRANCH}"

echo "Building ${IMAGE_TAG}..."
# Host networking avoids intermittent package-fetch failures seen with Docker's bridge network on this Pi.
docker build \
    --network host \
    --file "${PROJECT_ROOT}/Dockerfile.all-in-one" \
    --tag "${IMAGE_TAG}" \
    "${PROJECT_ROOT}"

echo "Starting the updated service..."
docker compose --file "${COMPOSE_FILE}" up --detach --no-build

echo "Waiting for ${HEALTH_URL}..."
for attempt in {1..30}; do
    if curl --fail --silent --show-error "${HEALTH_URL}" > /dev/null; then
        echo "Deployment complete. ${HEALTH_URL} is healthy."
        exit 0
    fi

    sleep 2
done

echo "The container started, but ${HEALTH_URL} did not become healthy in time." >&2
docker compose --file "${COMPOSE_FILE}" ps >&2
exit 1

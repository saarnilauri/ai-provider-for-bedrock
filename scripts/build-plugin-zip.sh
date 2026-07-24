#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="ai-provider-for-bedrock"
DIST_DIR="${ROOT_DIR}/dist"
STAGING_DIR="${DIST_DIR}/${PLUGIN_SLUG}"
ZIP_PATH="${DIST_DIR}/${PLUGIN_SLUG}.zip"

for tool in rsync zip composer; do
    if ! command -v "${tool}" >/dev/null 2>&1; then
        echo "Error: ${tool} is required to build the plugin ZIP." >&2
        exit 1
    fi
done

rm -rf "${STAGING_DIR}" "${ZIP_PATH}"
mkdir -p "${STAGING_DIR}"

rsync -a "${ROOT_DIR}/" "${STAGING_DIR}/" \
    --exclude-from="${ROOT_DIR}/.distignore" \
    --exclude ".git" \
    --exclude "dist"

# Install production-only dependencies into the staging directory.
# Never ship dev dependencies: the dev copy of wordpress/php-ai-client would
# shadow the SDK bundled in WordPress core and cause fatal errors.
cp "${ROOT_DIR}/composer.json" "${ROOT_DIR}/composer.lock" "${STAGING_DIR}/"
composer install \
    --working-dir="${STAGING_DIR}" \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --quiet
rm -f "${STAGING_DIR}/composer.json" "${STAGING_DIR}/composer.lock"

if [ -d "${STAGING_DIR}/vendor/wordpress/php-ai-client" ]; then
    echo "Error: dev dependency wordpress/php-ai-client ended up in the build." >&2
    exit 1
fi

(
    cd "${DIST_DIR}"
    zip -rq "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}"
)

echo "Created ${ZIP_PATH}"

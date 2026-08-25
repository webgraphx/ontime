#!/usr/bin/env bash
#
# OnTime plugin packaging script — builds a marketplace-ready ZIP.
#
# Usage:  bash build-plugin.sh [output-dir]
# Default output: ./dist/ontime-1.0.0.zip
#
# @since 1.0.0

set -euo pipefail

VERSION="1.0.0"
PLUGIN_SLUG="ontime"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="${SCRIPT_DIR}"
OUTPUT_DIR="${1:-${SCRIPT_DIR}/dist}"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="${OUTPUT_DIR}/${ZIP_NAME}"

EXCLUDE_PATTERNS=(
	".git"
	".gitignore"
	"dist"
	"docs"
	"references"
	"tests"
	"composer.json"
	"composer.lock"
	"build-plugin.sh"
	"phpunit.xml"
	"phpunit.xml.dist"
	"README.md"
	"QA.md"
	".DS_Store"
	"node_modules"
	"*.bak"
	"*.log"
	"*.map"
)

log()  { printf "  › %s\n" "$1"; }
ok()   { printf "  ✓ %s\n" "$1"; }
err()  { printf "  ✗ %s\n" "$1" >&2; }
die()  { err "$1"; exit 1; }

[ -f "${SOURCE_DIR}/ontime.php" ] || die "ontime.php not found in ${SOURCE_DIR}"

log "Building OnTime v${VERSION} package…"

rm -rf "${OUTPUT_DIR}"
mkdir -p "${OUTPUT_DIR}"

STAGE="$(mktemp -d)"
STAGE_PLUGIN="${STAGE}/${PLUGIN_SLUG}"
mkdir -p "${STAGE_PLUGIN}"

log "Copying files (excluding dev assets)…"

EXCLUDE_ARGS=()
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
	EXCLUDE_ARGS+=( --exclude="${pattern}" )
done

if command -v rsync >/dev/null 2>&1; then
	rsync -a "${EXCLUDE_ARGS[@]}" "${SOURCE_DIR}/" "${STAGE_PLUGIN}/"
else
	cp -a "${SOURCE_DIR}/." "${STAGE_PLUGIN}/"
	for pattern in "${EXCLUDE_PATTERNS[@]}"; do
		find "${STAGE_PLUGIN}" -name "${pattern}" -exec rm -rf {} + 2>/dev/null || true
	done
fi

ok "Files staged in temporary directory."

for required in ontime.php readme.txt uninstall.php includes/class-ontime.php includes/class-database.php includes/class-calendar-engine.php; do
	[ -f "${STAGE_PLUGIN}/${required}" ] || die "Missing required file: ${required}"
done
ok "Essential files verified."

log "Creating ${ZIP_NAME} …"
(
	cd "${STAGE}"
	zip -r -q "${ZIP_PATH}" "${PLUGIN_SLUG}/"
)
ok "ZIP created: ${ZIP_PATH}"

SIZE=$(du -h "${ZIP_PATH}" | cut -f1)
ok "Package size: ${SIZE}"

log "Package contents:"
unzip -l "${ZIP_PATH}" | tail -n +4 | head -n -2 | awk '{ printf "    %s\n", $4 }' | head -30
FILE_COUNT=$(unzip -l "${ZIP_PATH}" | tail -1 | awk '{ print $2 }')
ok "Total files: ${FILE_COUNT}"

rm -rf "${STAGE}"

echo ""
ok "Build complete: ${ZIP_PATH}"

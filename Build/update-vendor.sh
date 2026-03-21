#!/usr/bin/env bash
# Update the bundled force-graph library from unpkg/npm.
#
# Usage:
#   bash Build/update-vendor.sh          # fetches latest version
#   bash Build/update-vendor.sh 1.51.2   # fetches specific version
#
# Run from the extension root: packages/page_graph/

set -euo pipefail

PACKAGE="force-graph"
TARGET_DIR="Resources/Public/JavaScript/Vendor"
TARGET_FILE="${TARGET_DIR}/force-graph.min.js"
LICENSE_FILE="${TARGET_DIR}/LICENSE.md"

# ---------------------------------------------------------------------------
# Resolve version
# ---------------------------------------------------------------------------
if [[ -n "${1:-}" ]]; then
    VERSION="$1"
else
    echo "Fetching latest version from npm registry..."
    VERSION=$(curl -fsSL "https://registry.npmjs.org/${PACKAGE}/latest" | grep -o '"version":"[^"]*"' | head -1 | cut -d'"' -f4)
    if [[ -z "$VERSION" ]]; then
        echo "ERROR: Could not determine latest version." >&2
        exit 1
    fi
fi

echo "Updating ${PACKAGE} to v${VERSION}..."

# ---------------------------------------------------------------------------
# Download
# ---------------------------------------------------------------------------
URL="https://unpkg.com/${PACKAGE}@${VERSION}/dist/force-graph.min.js"
TMP_FILE=$(mktemp)
trap 'rm -f "$TMP_FILE"' EXIT

HTTP_CODE=$(curl -fsSL -w '%{http_code}' -o "$TMP_FILE" "$URL")
if [[ "$HTTP_CODE" != "200" ]]; then
    echo "ERROR: Download failed (HTTP ${HTTP_CODE}): ${URL}" >&2
    exit 1
fi

if [[ ! -s "$TMP_FILE" ]]; then
    echo "ERROR: Downloaded file is empty." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Prepend version comment
# ---------------------------------------------------------------------------
COMMENT="// Version ${VERSION} ${PACKAGE} - https://github.com/vasturiano/${PACKAGE}"
{
    echo "$COMMENT"
    cat "$TMP_FILE"
} > "${TARGET_FILE}"

echo "Written ${TARGET_FILE} ($(wc -c < "${TARGET_FILE}") bytes)"

# ---------------------------------------------------------------------------
# Update LICENSE.md
# ---------------------------------------------------------------------------
if [[ -f "$LICENSE_FILE" ]]; then
    SED_TMP=$(mktemp)
    sed "s/## ${PACKAGE} v[0-9][0-9.]*/## ${PACKAGE} v${VERSION}/" "$LICENSE_FILE" > "$SED_TMP"
    mv "$SED_TMP" "$LICENSE_FILE"
    echo "Updated version in ${LICENSE_FILE}"
fi

echo "Done. ${PACKAGE} v${VERSION} is ready."

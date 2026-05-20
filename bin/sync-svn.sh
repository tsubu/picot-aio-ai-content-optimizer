#!/usr/bin/env bash
# Sync plugin to WordPress.org SVN working copy.
#   trunk/  + tags/{version}/  = plugin code (no assets/, no dotfiles)
#   assets/ at SVN root         = wordpress.org banners & icons
#
# Usage: ./bin/sync-svn.sh /path/to/svn-working-copy [tag-version]
set -euo pipefail

SVN_DIR="${1:?SVN working copy path required}"
TAG_VERSION="${2:-1.0.0}"
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"

TRUNK_EXCLUDES=(
  --exclude '.git/'
  --exclude '.gitignore'
  --exclude '.distignore'
  --exclude '.DS_Store'
  --exclude '._*'
  --exclude 'README.md'
  --exclude 'assets/'
  --exclude 'scratch/'
  --exclude 'logs/'
  --exclude 'phpcs.xml'
  --exclude 'phpunit.xml'
  --exclude 'tests/'
  --exclude 'bin/'
  --exclude 'node_modules/'
  --exclude 'package.json'
  --exclude 'package-lock.json'
  --exclude 'webpack.config.js'
  --exclude 'composer.json'
  --exclude 'composer.lock'
  --exclude 'vendor/'
)

# Plugin code -> trunk (never includes assets/ or hidden dev files)
rsync -a --delete "${TRUNK_EXCLUDES[@]}" "${PLUGIN_DIR}/" "${SVN_DIR}/trunk/"

# WordPress.org plugin banners/icons -> SVN assets/ (repository root)
if [[ -d "${PLUGIN_DIR}/assets" ]]; then
  mkdir -p "${SVN_DIR}/assets"
  rsync -a --delete \
    --exclude '.DS_Store' \
    --exclude '._*' \
    "${PLUGIN_DIR}/assets/" "${SVN_DIR}/assets/"
fi

# Stable tag mirrors trunk
rm -rf "${SVN_DIR}/tags/${TAG_VERSION}"
mkdir -p "${SVN_DIR}/tags/${TAG_VERSION}"
rsync -a "${SVN_DIR}/trunk/" "${SVN_DIR}/tags/${TAG_VERSION}/"

echo "Done."
echo "  trunk/  = plugin code"
echo "  tags/${TAG_VERSION}/ = release snapshot"
echo "  assets/ = $(find "${SVN_DIR}/assets" -type f 2>/dev/null | wc -l | tr -d ' ') file(s)"
find "${SVN_DIR}/trunk" -name '.*' 2>/dev/null | grep -q . && echo "WARNING: dotfiles found under trunk/" || echo "  (no dotfiles under trunk/)"

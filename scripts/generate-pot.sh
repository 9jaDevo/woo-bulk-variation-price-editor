#!/usr/bin/env bash
set -euo pipefail

if ! command -v wp >/dev/null 2>&1; then
  echo "wp CLI not found. Install WP-CLI or run pot generation manually."
  exit 1
fi

wp i18n make-pot . languages/woo-bulk-variation-pricer.pot
echo "POT generated at languages/woo-bulk-variation-pricer.pot"

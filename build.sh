#!/bin/sh
# Builds dist/dip-seo-fixes.zip — upload via WordPress → Plugins → Add New → Upload Plugin.
set -e
cd "$(dirname "$0")"
mkdir -p dist
rm -f dist/dip-seo-fixes.zip
(cd wordpress/wp-content/plugins && zip -qr ../../../dist/dip-seo-fixes.zip dip-seo-fixes)
echo "Built dist/dip-seo-fixes.zip"

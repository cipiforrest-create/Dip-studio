#!/bin/sh
# Builds dist/dip-studio-seo-<version>.zip — upload via WordPress → Plugins → Add New → Upload Plugin.
set -e
cd "$(dirname "$0")"
V=$(sed -n 's/^ \* Version: *//p' wordpress/wp-content/plugins/dip-studio-seo/dip-studio-seo.php)
mkdir -p dist
rm -f dist/dip-studio-seo-*.zip dist/dip-seo-fixes.zip
(cd wordpress/wp-content/plugins && zip -qr "../../../dist/dip-studio-seo-$V.zip" dip-studio-seo -x '*.DS_Store')
echo "Built dist/dip-studio-seo-$V.zip"

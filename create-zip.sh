#!/bin/bash
# Create a WordPress-ready ZIP from this checkout, regardless of its name.
set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
BUILD_DIR=$(mktemp -d)
trap 'rm -rf -- "$BUILD_DIR"' EXIT
mkdir "$BUILD_DIR/eme-rest-api"
cp "$SCRIPT_DIR/eme-rest-api.php" "$SCRIPT_DIR/readme.txt" "$SCRIPT_DIR/README.md" "$BUILD_DIR/eme-rest-api/"
cd "$BUILD_DIR"
zip -q candidate.zip eme-rest-api/eme-rest-api.php eme-rest-api/readme.txt eme-rest-api/README.md
unzip -tqq candidate.zip
mv candidate.zip "$SCRIPT_DIR/eme-rest-api.zip"

echo "✓ Created eme-rest-api.zip"
echo ""
echo "Included files:"
echo "  - eme-rest-api.php (main plugin)"
echo "  - readme.txt (WordPress.org format)"
echo "  - README.md (GitHub documentation)"
echo ""
echo "Installation instructions:"
echo "1. Upload eme-rest-api.zip to WordPress"
echo "2. Go to Plugins → Add New → Upload Plugin"
echo "3. Choose eme-rest-api.zip and click Install"
echo "4. Activate the plugin"
echo ""
echo "Or manually:"
echo "1. Upload the eme-rest-api folder to /wp-content/plugins/"
echo "2. Activate via WordPress admin"

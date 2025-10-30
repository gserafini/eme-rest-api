#!/bin/bash
# Create a WordPress-ready ZIP file for plugin installation

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

# Remove old ZIP if exists
rm -f eme-rest-api.zip

# Create ZIP with plugin files
zip -r eme-rest-api.zip . -x "*.git*" "*.DS_Store" "create-zip.sh" "*.zip"

echo "✓ Created eme-rest-api.zip"
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

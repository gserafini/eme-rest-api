#!/bin/bash
# Create a WordPress-ready ZIP file for plugin installation

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR/.."

# Remove old ZIP if exists
rm -f eme-rest-api/eme-rest-api.zip

# Create ZIP with proper folder structure for WordPress
# The cd to parent dir and then zip with path creates the folder structure
zip -r eme-rest-api/eme-rest-api.zip eme-rest-api/eme-rest-api.php eme-rest-api/readme.txt eme-rest-api/README.md \
  -x "*.git*" "*.DS_Store"

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

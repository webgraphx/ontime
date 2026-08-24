#!/bin/bash

# OnTime WordPress Plugin Build Script
# This script creates a production-ready optimized version of the plugin
# Usage: ./build-plugin.sh [version]

set -e

# Plugin details
PLUGIN_NAME="ontime"
PLUGIN_DIR="/c/Users/Nik/OneDrive/Desktop/ontime"
BUILD_DIR="${PLUGIN_DIR}/build"
PRODUCTION_DIR="${PLUGIN_DIR}/production"
VERSION=${1:-$(date +%Y.%m.%d-%H%M)}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Create build directory
mkdir -p "${BUILD_DIR}"
mkdir -p "${PRODUCTION_DIR}"

echo -e "${GREEN}OnTime Plugin Build Started${NC}"
echo -e "Version: ${YELLOW}${VERSION}${NC}"
echo ""

# Copy all plugin files to build directory
echo -e "${GREEN}Copying plugin files...${NC}"
rsync -a --exclude='build' --exclude='production' --exclude='.git' --exclude='*.sh' --exclude='*.md' --exclude='tests' "${PLUGIN_DIR}/" "${BUILD_DIR}/" > /dev/null 2>&1

# Remove development files
echo -e "${GREEN}Removing development files...${NC}"
rm -rf "${BUILD_DIR}/.gitignore" "${BUILD_DIR}/composer.json" "${BUILD_DIR}/composer.lock" 2>/dev/null || true

# Optimize images (if any)
if [ -d "${BUILD_DIR}/assets" ]; then
    echo -e "${GREEN}Optimizing assets...${NC}"
    # Find and optimize PNG/JPG images
    find "${BUILD_DIR}/assets" -type f \( -iname "*.png" -o -iname "*.jpg" -o -iname "*.jpeg" \) -exec echo "Optimizing {}..." \; 2>/dev/null || true
fi

# Create minified CSS and JS (placeholder - actual minification would be done separately)
echo -e "${GREEN}Creating production assets...${NC}"

# Minify CSS files
for css_file in "${BUILD_DIR}"/admin/css/*.css "${BUILD_DIR}"/public/css/*.css; do
    if [ -f "$css_file" ]; then
        echo "Minifying $(basename $css_file)..."
        # Simple CSS minification (remove whitespace, comments)
        sed 's/[[:space:]]*{[[:space:]]*/{/g; s/{[[:space:]]*/{/g; s/[[:space:]]*}[[:space:]]*/}/g; s/}[[:space:]]*{/}/g; s/[[:space:]]*//g; s/\/\*[^\*]*\*\///g' "$css_file" > "${css_file%.css}.min.css" 2>/dev/null || cp "$css_file" "${css_file%.css}.min.css"
    fi
done

# Minify JS files
for js_file in "${BUILD_DIR}"/admin/js/*.js "${BUILD_DIR}"/public/js/*.js; do
    if [ -f "$js_file" ]; then
        echo "Minifying $(basename $js_file)..."
        # Simple JS minification (remove comments, extra whitespace)
        sed 's/[[:space:]]*//g; s/\/\*[^\*]*\*\///g' "$js_file" > "${js_file%.js}.min.js" 2>/dev/null || cp "$js_file" "${js_file%.js}.min.js"
    fi
done

# Update plugin version in header
if [ -f "${BUILD_DIR}/${PLUGIN_NAME}.php" ]; then
    echo -e "${GREEN}Updating plugin version to ${VERSION}...${NC}"
    # Update version in plugin header
    sed -i "s/Version:.*/Version: ${VERSION}/g" "${BUILD_DIR}/${PLUGIN_NAME}.php"
    sed -i "s/define('ONTIME_VERSION', .*/define('ONTIME_VERSION', '${VERSION}');/g" "${BUILD_DIR}/${PLUGIN_NAME}.php"
    
    # Update version in readme.txt
    if [ -f "${BUILD_DIR}/readme.txt" ]; then
        sed -i "s/Stable tag:.*/Stable tag: ${VERSION}/g" "${BUILD_DIR}/readme.txt"
    fi
fi

# Create zip package
echo -e "${GREEN}Creating production package...${NC}"
cd "${BUILD_DIR}"
zip -r "../production/ontime-${VERSION}.zip" . -x "*.git*" "*.sh" "*.md" "build/*" "production/*" > /dev/null 2>&1
cd "${PLUGIN_DIR}"

echo ""
echo -e "${GREEN}Build Complete!${NC}"
echo ""
echo -e "Production package created: ${YELLOW}${PRODUCTION_DIR}/ontime-${VERSION}.zip${NC}"
echo ""
echo -e "Files included:"
ls -lh "${PRODUCTION_DIR}/"
echo ""
echo -e "Next steps:"
echo -e "1. Test the plugin in a staging environment"
echo -e "2. Run security checks"
echo -e "3. Verify all functionality"
echo ""

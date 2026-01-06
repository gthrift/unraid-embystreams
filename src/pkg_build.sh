#!/bin/bash
#
# EmbyStreams Package Build Script
# Creates a .txz package from the src folder structure
#
# Usage: ./pkg_build.sh [version]
#

PLUGIN_NAME="embystreams"
VERSION="${1:-$(date +%Y.%m.%d)}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_DIR="${SCRIPT_DIR}/embystreams"
BUILD_DIR="${SCRIPT_DIR}/../archive"
PACKAGE_NAME="${PLUGIN_NAME}-${VERSION}-x86_64-1"

echo "Building ${PLUGIN_NAME} version ${VERSION}..."

# Create build directory
mkdir -p "${BUILD_DIR}"

# Create package structure in temp directory
TEMP_DIR=$(mktemp -d)
mkdir -p "${TEMP_DIR}/usr/local/emhttp/plugins/${PLUGIN_NAME}"
cp -r "${SRC_DIR}"/* "${TEMP_DIR}/usr/local/emhttp/plugins/${PLUGIN_NAME}/"

# Create the package (requires makepkg from Slackware)
cd "${TEMP_DIR}"

if command -v makepkg &> /dev/null; then
    makepkg -l y -c n "${BUILD_DIR}/${PACKAGE_NAME}.txz"
else
    # Fallback: create tar.xz manually
    echo "makepkg not found, creating archive manually..."
    tar -cJf "${BUILD_DIR}/${PACKAGE_NAME}.txz" .
fi

# Generate MD5
cd "${BUILD_DIR}"
md5sum "${PACKAGE_NAME}.txz" > "${PACKAGE_NAME}.txz.md5"

# Clean up
rm -rf "${TEMP_DIR}"

echo ""
echo "Package created: ${BUILD_DIR}/${PACKAGE_NAME}.txz"
echo "MD5: $(cat ${BUILD_DIR}/${PACKAGE_NAME}.txz.md5)"

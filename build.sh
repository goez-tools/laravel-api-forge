#!/bin/bash

# build.sh - Script for building laravel-api-forge executable
# Usage: ./build.sh <version>
# Example: ./build.sh 1.0.0

set -e  # Exit immediately if a command exits with a non-zero status

# Check if version parameter is provided
if [ -z "$1" ]; then
    echo "❌ Error: Please provide version number"
    echo "Usage: $0 <version>"
    echo "Example: $0 1.0.0"
    exit 1
fi

VERSION="$1"
BACKUP_DIR=".backup_$(date +%Y%m%d_%H%M%S)"

echo "🔧 Starting build for laravel-api-forge $VERSION"

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup composer files
echo "📦 Backing up composer files..."
cp composer.json "$BACKUP_DIR/"
cp composer.lock "$BACKUP_DIR/"

# Define cleanup function to execute on script exit
cleanup() {
    echo "🔄 Restoring composer files..."
    cp "$BACKUP_DIR/composer.json" .
    cp "$BACKUP_DIR/composer.lock" .

    echo "🧹 Cleaning up backup files..."
    rm -rf "$BACKUP_DIR"

    echo "📥 Reinstalling complete dependencies..."
    composer install --optimize-autoloader --quiet

    echo "✅ Restored to original state"
}

# Set trap to ensure cleanup executes on script exit
trap cleanup EXIT

# Remove unnecessary dev packages to reduce executable size
echo "🗑️  Removing dev packages..."
composer remove laravel/pint mockery/mockery pestphp/pest --dev --no-update

echo "📥 Updating dependencies..."
composer update --optimize-autoloader

# Execute build command with retry mechanism
echo "🔨 Building executable..."

BUILD_SUCCESS=false
MAX_RETRIES=3
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ] && [ "$BUILD_SUCCESS" = false ]; do
    RETRY_COUNT=$((RETRY_COUNT + 1))

    if [ $RETRY_COUNT -gt 1 ]; then
        echo "🔄 Retry attempt $RETRY_COUNT of $MAX_RETRIES..."
        # Clean up previous failed build
        rm -f builds/laravel-api-forge
    fi

    # Execute build command
    if php laravel-api-forge app:build --build-version="$VERSION"; then
        # Verify build result
        echo "🔍 Verifying build result..."
        if [ -f "builds/laravel-api-forge" ]; then
            # Execute version check to confirm build success
            VERSION_OUTPUT=$(builds/laravel-api-forge --version 2>/dev/null || echo "Version check failed")
            if [[ "$VERSION_OUTPUT" == *"$VERSION"* ]]; then
                echo "✅ Build successful! Version: $VERSION_OUTPUT"
                BUILD_SUCCESS=true
            else
                echo "⚠️  Warning: Version verification failed - $VERSION_OUTPUT"
            fi
        else
            echo "⚠️  Warning: Build executable not found"
        fi
    else
        echo "⚠️  Warning: Build command failed"
    fi

    if [ "$BUILD_SUCCESS" = false ] && [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
        echo "⏳ Build failed, waiting 2 seconds before retry..."
        sleep 2
    fi
done

# Check final result
if [ "$BUILD_SUCCESS" = false ]; then
    echo "❌ Error: Build failed after $MAX_RETRIES attempts"
    exit 1
fi

echo "✅ Build completed!"
echo "🎉 Executable built successfully, version: $VERSION"

# Display build results
SIZE=$(stat -f%z builds/laravel-api-forge 2>/dev/null || stat -c%s builds/laravel-api-forge 2>/dev/null || echo "Unknown")
echo "📊 Executable size: $SIZE bytes"
echo "📍 Executable location: builds/laravel-api-forge"

# Git operations - commit and tag the release
echo "🔖 Creating git commit and tag..."
git add builds/laravel-api-forge
git commit -m "release: $VERSION"
git tag "$VERSION"

echo "✅ Git commit and tag created successfully!"
echo "📝 Commit message: release: $VERSION"
echo "🏷️  Tag: $VERSION"

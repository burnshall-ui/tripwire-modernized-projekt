#!/bin/bash
#
# Git Commit Script for Tripwire 2.1 Changes
# Run: bash commit-changes.sh
#

echo "📝 Preparing Git Commit for Tripwire 2.1..."
echo ""

# Check if we're in a git repo
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo "❌ ERROR: Not a git repository"
    exit 1
fi

# Show what will be committed
echo "📋 Files to be committed:"
echo ""
git status --short
echo ""

# Ask for confirmation
read -p "❓ Commit these changes? (y/n) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Commit cancelled"
    exit 0
fi

# Add all changes
echo "➕ Adding files..."
git add .

# Commit with detailed message
echo "💾 Creating commit..."
git commit -m "feat: Tripwire 2.1 - PSR-4 Autoloading + Logging + Health Check

## Major Changes

### 1. PSR-4 Autoloading ✅
- Add namespaces to all classes (Services, Controllers, Models, Views)
- Remove empty src/ directory
- Update composer.json with correct PSR-4 paths
- Refactor tripwire.php to use Composer autoloader

### 2. Monolog Logging ✅
- Create Logger service with rotating file handlers
- Development: All logs to logs/tripwire.log (7 days)
- Production: Errors to logs/error.log (30 days)
- Support for debug, info, warning, error, critical levels

### 3. Environment Configuration ✅
- Add .env support with vlucas/phpdotenv
- Create .env.example template
- Load environment variables in tripwire.php
- Support for dev/prod environments

### 4. Health Check Endpoint ✅
- Create public/health.php
- Monitor: MySQL, Redis, Sessions, Disk Space, PHP version
- JSON response with HTTP status codes
- Docker/Kubernetes ready

### 5. Documentation ✅
- UPGRADE.md - Complete upgrade guide
- FIXES_APPLIED.md - Summary of all changes
- QUICKSTART.md - Quick testing guide
- test-autoload.php - Automated test script

## Modified Files
- composer.json - PSR-4 paths, add phpdotenv
- tripwire.php - Use autoloader + .env
- All services/ - Add namespaces
- All controllers/ - Add namespaces
- All models/ - Add namespaces
- All views/ - Add namespaces

## New Files
- services/Logger.php
- .env.example
- public/health.php
- test-autoload.php
- UPGRADE.md
- FIXES_APPLIED.md
- QUICKSTART.md

## Deleted
- src/ directory (was empty)

## Breaking Changes
⚠️ Requires: composer install && composer dump-autoload

## Testing
Run: php test-autoload.php

## Migration
See: UPGRADE.md

---

Co-Authored-By: Claude <noreply@anthropic.com>
"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Commit created successfully!"
    echo ""
    echo "📊 Commit Details:"
    git log -1 --stat
    echo ""
    echo "🚀 Next Steps:"
    echo "  1. Test changes: php test-autoload.php"
    echo "  2. Push to remote: git push origin master"
    echo "  3. Create tag (optional): git tag -a v2.1 -m 'Version 2.1'"
    echo "  4. Push tag: git push --tags"
else
    echo ""
    echo "❌ Commit failed!"
    exit 1
fi

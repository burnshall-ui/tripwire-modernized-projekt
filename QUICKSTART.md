# Quick Start - Testing on Linux/Docker

**Status:** Ready to test (Windows 11 doesn't have PHP/Composer)
**Test Environment:** Linux, macOS, Docker, or WSL

---

## 🐳 Option 1: Docker (Empfohlen)

### Quick Test with Docker Compose

```bash
# 1. Start all services
docker-compose up -d

# 2. Install dependencies inside container
docker-compose exec php composer install
docker-compose exec php composer dump-autoload --optimize

# 3. Run autoload test
docker-compose exec php php test-autoload.php

# 4. Test health check
curl http://localhost/health.php | jq

# 5. Check logs
docker-compose exec php tail -f logs/tripwire.log
```

---

## 🐧 Option 2: Linux/WSL

### Install Dependencies

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install php8.1 php8.1-cli php8.1-mysql php8.1-redis composer

# Install Composer dependencies
composer install
composer dump-autoload --optimize
```

### Create .env File

```bash
cp .env.example .env
nano .env  # Edit with your settings
```

### Run Tests

```bash
# 1. Test autoloading
php test-autoload.php

# Expected output:
# 🧪 Testing Tripwire 2.1 Autoloading...
# ✅ Tripwire\Services\Container
# ✅ Tripwire\Services\RedisService
# ...
# 🎉 All tests passed!

# 2. Syntax check all PHP files
find services/ controllers/ models/ views/ -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"

# 3. Start built-in PHP server (for testing)
php -S localhost:8000 -t public/

# 4. In another terminal: Test health check
curl http://localhost:8000/health.php | jq
```

---

## 🪟 Option 3: Windows (WSL2)

### Enable WSL2 (PowerShell als Admin)

```powershell
wsl --install
wsl --set-default-version 2
wsl --install -d Ubuntu
```

### Nach WSL Neustart:

```bash
# In WSL Ubuntu:
cd /mnt/c/Users/tspor/cursor/tripwire

# Install PHP
sudo apt update
sudo apt install php8.1-cli composer

# Test
php test-autoload.php
```

---

## 📋 Pre-Flight Checklist

Bevor du testest, stelle sicher:

- [ ] Git Commit gemacht (siehe unten)
- [ ] Backup erstellt (falls etwas schiefgeht)
- [ ] Docker läuft ODER PHP 8.0+ installiert
- [ ] Composer verfügbar

---

## 🔧 Git Commit (Empfohlen)

```bash
# Check status
git status

# Should show:
# modified:   composer.json
# modified:   tripwire.php
# modified:   services/Container.php
# ... (all services/controllers/models/views)
# new file:   services/Logger.php
# new file:   .env.example
# new file:   public/health.php
# new file:   test-autoload.php
# new file:   UPGRADE.md
# new file:   FIXES_APPLIED.md
# new file:   QUICKSTART.md
# deleted:    src/

# Add all changes
git add .

# Commit
git commit -m "feat: PSR-4 autoloading, Monolog logging, .env support, health check

- Add PSR-4 namespaces to all classes (Services, Controllers, Models, Views)
- Integrate Monolog for structured logging with rotating files
- Add .env support with vlucas/phpdotenv
- Create health check endpoint (public/health.php)
- Remove empty src/ directory
- Refactor tripwire.php to use Composer autoloader
- Add comprehensive documentation (UPGRADE.md, FIXES_APPLIED.md)
- Add test script (test-autoload.php)

Closes #1 #2 #3 (wenn du Issues hast)
"

# Optional: Tag the version
git tag -a v2.1 -m "Version 2.1 - PSR-4 + Logging + Health Check"
```

---

## 🧪 What to Test

### 1. Autoloading Test

```bash
php test-autoload.php
```

**Expected:**
- ✅ All classes load without errors
- ✅ Logger writes to logs/tripwire.log
- ✅ .env file loads (if present)

---

### 2. Health Check Test

```bash
# Start web server
php -S localhost:8000 -t public/

# In another terminal:
curl http://localhost:8000/health.php
```

**Expected JSON:**
```json
{
  "status": "healthy",
  "services": {
    "database": { "status": "up" },
    "redis": { "status": "up" }
  },
  "system": {
    "php_version": "8.1.0",
    "php_ok": true
  }
}
```

---

### 3. Logging Test

```bash
# Create a test script
cat > test-logging.php << 'EOF'
<?php
require 'vendor/autoload.php';
use Tripwire\Services\Logger;

Logger::info("Test log entry");
Logger::error("Test error", ['user' => 'test']);
Logger::debug("Debug info", ['data' => ['key' => 'value']]);

echo "Logs written to logs/tripwire.log\n";
EOF

# Run it
php test-logging.php

# Check logs
cat logs/tripwire.log
```

**Expected:**
```
[2025-11-02 03:55:00] tripwire.INFO: Test log entry [] []
[2025-11-02 03:55:00] tripwire.ERROR: Test error {"user":"test"} []
[2025-11-02 03:55:00] tripwire.DEBUG: Debug info {"data":{"key":"value"}} []
```

---

### 4. Full Integration Test

```bash
# 1. Install dependencies
composer install

# 2. Create .env
cp .env.example .env
# Edit .env with real database credentials

# 3. Test database connection
php -r "
require 'vendor/autoload.php';
require 'db.inc.php';
echo 'MySQL Version: ';
echo \$mysql->query('SELECT VERSION()')->fetchColumn();
echo PHP_EOL;
"

# 4. Start application
php -S localhost:8000

# 5. Open browser: http://localhost:8000
```

---

## ⚠️ Troubleshooting

### "composer: command not found"

```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### "Class not found" errors

```bash
# Regenerate autoloader
composer dump-autoload --optimize

# Check if vendor/autoload.php exists
ls -la vendor/autoload.php
```

### Permission errors on logs/

```bash
# Create logs directory
mkdir -p logs
chmod 755 logs

# For web server:
sudo chown -R www-data:www-data logs/
```

### Docker issues

```bash
# Rebuild containers
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Check logs
docker-compose logs -f
```

---

## 📊 Success Criteria

✅ **All Tests Pass:**
- [ ] `php test-autoload.php` shows "🎉 All tests passed!"
- [ ] Health check returns HTTP 200 + JSON
- [ ] Logs are written to `logs/tripwire.log`
- [ ] No PHP syntax errors
- [ ] Application loads without errors

---

## 🚀 When Tests Pass

1. **Push to Git:**
   ```bash
   git push origin master
   git push --tags  # if you created a tag
   ```

2. **Deploy to Production:**
   - Copy `.env.example` to `.env` on server
   - Run `composer install --no-dev --optimize-autoloader`
   - Set proper permissions on `logs/`
   - Configure Nginx/Apache
   - Restart services

3. **Monitor:**
   - Check health endpoint: `curl https://your-domain.com/health.php`
   - Watch logs: `tail -f logs/tripwire.log`
   - Set up monitoring alerts (optional)

---

## 📞 Support

Falls Probleme auftreten:

1. **Check logs:** `tail -f logs/tripwire.log`
2. **Run test:** `php test-autoload.php`
3. **Check git status:** `git status` (hast du alle Dateien?)
4. **Discord:** https://discord.gg/xjFkJAx

---

## 🎯 Next Steps

After successful testing:

1. Update README.md with new features
2. Add PHPUnit tests (optional)
3. Setup CI/CD pipeline (GitHub Actions)
4. Configure production logging rotation
5. Add API documentation (if needed)

---

**Ready to test when you are!** 🚀

Good luck! o7

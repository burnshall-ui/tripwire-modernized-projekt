# Tripwire Modernisierung - Dokumentation

## 🎯 Übersicht

Diese Dokumentation beschreibt die Modernisierung der Tripwire-Codebase von einer monolithischen Legacy-Anwendung zu einer modernen, modularen PHP 8.0+ Architektur.

## 📁 Neue Dateistruktur

```
tripwire/
├── tripwire_new.php          # Modernisierte Entry-Point-Datei
├── services/                   # Service Layer
│   ├── Container.php          # Dependency Injection Container
│   ├── RedisService.php       # Redis Cache & Session Service
│   ├── RedisSessionHandler.php # Session Handler Implementation
│   ├── UserService.php        # User Management Service
│   ├── SignatureService.php   # Signature Management mit Cache
│   ├── WormholeService.php    # Wormhole Management
│   ├── ErrorHandler.php       # Zentrales Error Handling
│   └── DatabaseConnection.php # Database Connection Manager
├── controllers/               # Controller Layer
│   └── SystemController.php   # System-bezogene Logik
├── views/                     # View Layer
│   └── SystemView.php         # Template Rendering
├── models/                    # Data Models
│   ├── Signature.php          # Signature Model
│   └── Wormhole.php           # Wormhole Model
└── websockets/                # WebSocket Server
    └── WebSocketServer.php    # Real-time Updates
```

## 🚀 Hauptverbesserungen

### 1. **Dependency Injection Container**

**Datei:** `services/Container.php`

- ✅ Singleton-Pattern für Services
- ✅ Factory-Pattern für Views
- ✅ Parameter-Management
- ✅ Service-Discovery
- ✅ Cache-Management für bessere Performance

**Beispiel:**
```php
$container = createContainer();

// Singleton-Services (immer gleiche Instanz)
$userService = $container->get('userService');
$redis = $container->get('redis');

// Factory-Services (neue Instanz jedes Mal)
$view = $container->get('systemView');
```

### 2. **Redis-Integration**

**Dateien:** `services/RedisService.php`, `services/RedisSessionHandler.php`

**Features:**
- ✅ Persistent Connections
- ✅ JSON-Serialisierung
- ✅ Automatisches Fallback auf File-Sessions
- ✅ Cache-Tag-System für Invalidierung
- ✅ Session-Management mit TTL
- ✅ Umfassende Error-Handling

**Cache-Strategie:**
```php
// Signature-Cache mit automatischer Invalidierung
$signatures = $signatureService->getBySystem($systemId, $maskId);

// Cache-Tag-basierte Invalidierung
$redis->tagInvalidate("system:{$systemId}");
```

**Session-Handling:**
- Redis-basierte Sessions (wenn verfügbar)
- Automatischer Fallback auf sichere File-Sessions
- 24h Session-TTL
- Secure Cookie-Konfiguration

### 3. **Service Layer Architecture**

**UserService** (`services/UserService.php`):
- User-Aktivitäts-Tracking
- Permission-Checking
- Session-Management

**SignatureService** (`services/SignatureService.php`):
- CRUD-Operationen mit Cache
- Cache-Tag-basierte Invalidierung
- WebSocket-Broadcasting
- Expired Signature Detection

**WormholeService** (`services/WormholeService.php`):
- Wormhole-Management
- Stability-Checks
- Mass-Status-Berechnungen

### 4. **MVC-Pattern**

**Controller** (`controllers/SystemController.php`):
- System-Auflösung und Validierung
- Business-Logik-Koordination
- Default-Fallbacks (Jita)

**View** (`views/SystemView.php`):
- Template-Rendering
- XSS-Protection durch `htmlspecialchars()`
- Container-Injection für Service-Zugriff
- Modulares Rendering (Head, Topbar, Panel, Footer)

**Models** (`models/Signature.php`, `models/Wormhole.php`):
- Type-hinted Properties (PHP 8.0+)
- DateTime-Handling
- Utility-Methoden (isExpired, getTimeToLive, etc.)
- Array-Serialisierung

### 5. **Error Handling**

**Datei:** `services/ErrorHandler.php`

**Custom Exception-Klassen:**
- `AppException` - Basis-Exception mit HTTP-Code
- `ValidationException` - HTTP 400 für Validierungsfehler
- `PermissionException` - HTTP 403 für Access Denied
- `NotFoundException` - HTTP 404 für fehlende Ressourcen

**Features:**
- ✅ Zentrales Exception-Handling
- ✅ Error → Exception Konvertierung
- ✅ Shutdown-Handler für Fatal Errors
- ✅ Debug-Mode mit Stack Traces
- ✅ Security Headers (X-Content-Type-Options, X-Frame-Options)
- ✅ Error-Logging

### 6. **WebSocket Integration**

**Datei:** `websockets/WebSocketServer.php`

**Features:**
- Real-time Signature/Wormhole Updates
- Subscription-basiertes Broadcasting
- Mask & System-basierte Channels
- Ping/Pong Heartbeat
- Error-Recovery

## 🔧 Migration zu tripwire_new.php

### Session-Handling
```php
// Redis-Session mit Fallback
$redisSessionInitialized = RedisSessionHandler::init();

if (!$redisSessionInitialized) {
    // Sichere File-based Sessions
    session_start([
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'use_only_cookies' => true
    ]);
} else {
    session_start();
}
```

### Service-Initialisierung
```php
// Container mit allen Services erstellen
$container = createContainer();

// Services abrufen
$systemController = $container->get('systemController');
$userService = $container->get('userService');
$signatureService = $container->get('signatureService');
$wormholeService = $container->get('wormholeService');
$view = $container->get('systemView');
```

### View-Rendering
```php
// View-Daten setzen
$view->setData('system', $systemData['system']);
$view->setData('systemID', $systemData['systemID']);
$view->setData('user', $userService->getUserData());

// Seite rendern
$view->renderHead();
$view->renderTopbar();
$view->renderUserPanel();
$view->renderFooter();
```

## 📊 Performance-Verbesserungen

### Redis-Caching
- Signature-Queries werden 5 Minuten gecached
- Tag-basierte Cache-Invalidierung
- Automatische Session-Verwaltung
- Reduzierte Datenbank-Last

### Singleton-Services
- Services werden nur einmal instanziiert
- Container verwaltet Lifecycle
- Reduzierter Memory-Footprint

### Prepared Statements
- Alle DB-Queries nutzen PDO Prepared Statements
- SQL-Injection-Schutz
- Parameter-Type-Binding

## 🔒 Security-Verbesserungen

### Session-Security
```php
'cookie_secure' => true,        // Nur über HTTPS
'cookie_httponly' => true,      // Kein JavaScript-Zugriff
'cookie_samesite' => 'Strict',  // CSRF-Schutz
'use_strict_mode' => true,      // Session-Fixation-Schutz
'use_only_cookies' => true      // Keine URL-basierte Session-ID
```

### XSS-Protection
```php
// Alle Outputs werden escaped
<?= htmlspecialchars($userData['characterName']) ?>
```

### SQL-Injection-Prevention
```php
// Prepared Statements überall
$stmt = $this->db->prepare($query);
$stmt->bindValue(':userID', $userId, PDO::PARAM_INT);
$stmt->execute();
```

## 🧪 Testing-Vorbereitung

Die neue Architektur ist voll testbar:

```php
// Mock Container für Unit-Tests
$container = new Container();
$container->singleton('db', fn() => $mockDB);

// Service isoliert testen
$userService = new UserService($mockDB);
$result = $userService->trackUserActivity(123);
```

## 📋 Composer-Abhängigkeiten

**Root `composer.json`:**
```json
{
    "require": {
        "php": ">=8.0",
        "cboden/ratchet": "^0.4.4",
        "react/event-loop": "^1.3",
        "react/socket": "^1.12",
        "predis/predis": "^2.0",
        "monolog/monolog": "^3.0",
        "symfony/cache": "^6.0",
        "symfony/http-client": "^6.0"
    }
}
```

**Installation:**
```bash
composer install
```

## 🚀 Deployment

### 1. Redis installieren
```bash
docker-compose up -d redis
```

### 2. Composer-Dependencies installieren
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Config-Dateien anlegen
```bash
cp config.example.php config.php
cp db.inc.example.php db.inc.php
```

### 4. Web-Server konfigurieren
DocumentRoot auf `/public` setzen:
```apache
DocumentRoot /var/www/tripwire/public
```

### 5. WebSocket-Server starten (optional)
```bash
php websockets/WebSocketServer.php
```

## 🎓 Best Practices

### Service-Nutzung
```php
// ✅ RICHTIG - Service aus Container holen
$userService = $container->get('userService');
$userData = $userService->getUserData();

// ❌ FALSCH - Direktes Instanziieren
$userService = new UserService($mysql);
```

### Cache-Invalidierung
```php
// ✅ RICHTIG - Tag-basiert invalidieren
$redis->tagInvalidate("system:{$systemId}");

// ❌ FALSCH - Manuell einzelne Keys löschen
$redis->delete("signatures:system:{$systemId}:{$maskId}");
```

### Error-Handling
```php
// ✅ RICHTIG - Custom Exceptions werfen
throw new PermissionException("Access denied to mask {$maskId}");

// ❌ FALSCH - Generische Exceptions
throw new Exception("Error");
```

## 📈 Nächste Schritte

### Kurzfristig
- [ ] PSR-4 Autoloading implementieren
- [ ] Unit-Tests schreiben
- [ ] API-Layer erstellen (REST)
- [ ] Logging mit Monolog implementieren

### Mittelfristig
- [ ] Frontend modernisieren (Vue.js/React)
- [ ] Rate-Limiting implementieren
- [ ] API-Authentication (JWT)
- [ ] Database-Migrations

### Langfristig
- [ ] Microservices-Architektur
- [ ] Event-Sourcing für Signaturen
- [ ] GraphQL-API
- [ ] Kubernetes-Deployment

## 🤝 Contributing

Beim Hinzufügen neuer Features:

1. **Services** in `services/` anlegen
2. Im **Container** registrieren
3. **Tests** schreiben
4. **Dokumentation** aktualisieren

## 📞 Support

Bei Fragen zur Modernisierung:
- Discord: https://discord.gg/xjFkJAx
- In-Game: Tripwire Public Channel

---

**Version:** 2.0-modern  
**Letztes Update:** November 2025  
**Autor:** Tripwire Modernisierung Team


# Tripwire Modernisierung - Fortschritt & Fehleranalyse

**Datum:** 03./04. November 2025
**Projekt:** Tripwire (EVE Online Wormhole Mapper)
**Status:** Docker-Deployment erfolgreich, aber Applikation nicht funktional

---

## 🎯 Ausgangslage

- Docker-Deployment läuft (NGINX, PHP-FPM 8.2, MySQL 8.0, Redis 7.2, WebSocket)
- Webinterface erreichbar unter `http://192.168.2.150:8081`
- Landing Page zeigt sich, aber keine Funktionalität
- Registrierung und Login funktionieren nicht
- Map-Interface nicht nutzbar

---

## ✅ Erfolgreich Durchgeführte Fixes

### 1. CDN Domain Konfiguration
**Problem:** Static Assets (JS/CSS) luden von hardcoded `//localhost`, funktionierte nicht von Remote-IP
**Lösung:** CDN_DOMAIN dynamisch gemacht mit `$_SERVER['HTTP_HOST']`
**Datei:** `/config.php`

### 2. EVE SSO Authentifizierung
**Problem:** EVE SSO Callback URLs nicht konfiguriert
**Lösung:**
- EVE SSO Credentials in `config.php` eingefügt
- Unified SSO Router erstellt: `/public/sso.php`
- Routed basierend auf `state` Parameter zu Login/Registration
- Simplified Login Handler: `/public/login_sso_simple.php`

**Credentials:**
```
Client ID: d8b63fd50cd34f7ea9051f2e0bbd0b31
Callback: http://192.168.2.150:8081/sso.php
```

**Status:** ✅ Registrierung erfolgreich (Character: Discordia Jezzman, ID: 94447211)

### 3. ESI Class Error Handling
**Problem:** Roles/Titles Endpoints schlugen fehl, verursachten Fatal Errors
**Lösung:** Error Handling in `esi.class.php` für `getCharacterRoles()` und `getCharacterTitles()` verbessert
**Datei:** `/esi.class.php`

### 4. Redis Service Fixes
**Problem 1:** RedisService class not found
**Lösung:** `require_once('services/RedisService.php')` in tripwire.php hinzugefügt

**Problem 2:** Redis im Protected Mode
**Lösung:** `protected-mode no` in `/redis/redis.conf`
**Container Restart:** `docker compose restart redis`

**Problem 3:** Falscher Redis Hostname
**Lösung:** `'host' => 'redis'` → `'host' => 'tripwire-redis'`
**Datei:** `/services/RedisService.php` Zeile 10

**Problem 4:** Redis ping() Version-Inkompatibilität
**Lösung:** `isConnected()` akzeptiert jetzt `true`, `'PONG'` und `'+PONG'`
**Datei:** `/services/RedisService.php` Zeile 62-78

### 5. Redis Session Handler
**Problem:** Deprecation Warning für `gc()` return type
**Lösung:** `#[\ReturnTypeWillChange]` Attribut und korrekter Return Type
**Datei:** `/services/RedisSessionHandler.php`

**Status:** ✅ RedisSessionHandler::init() gibt jetzt TRUE zurück

### 6. EVE SDE Database Fallback
**Problem:** SELECT denied für table 'mapSolarSystems' - EVE SDE nicht importiert
**Lösung:** Try-Catch Fallback in SystemController, defaultet zu Jita
**Datei:** `/controllers/SystemController.php`

### 7. Session Conflict Fixes
**Problem:** index.php startete File-Session, tripwire.php versuchte Redis-Session zu starten
**Lösung:** index.php schließt existierende Session mit `session_write_close()` bevor tripwire.php geladen wird
**Datei:** `/public/index.php` Zeile 14-21

---

## ❌ KRITISCHE BUGS GEFUNDEN (NICHT GEFIXT)

### Bug #1: JavaScript Syntax Error durch falsche JSON-Ausgabe
**Datei:** `/views/SystemView.php` Zeile 173
**Code:**
```php
var init = <?= htmlspecialchars(json_encode($this->data['session'] ?? []), ENT_QUOTES, 'UTF-8') ?>;
```

**Problem:**
- `htmlspecialchars()` wird auf JSON angewendet
- Konvertiert `"` zu `&quot;`
- Erzeugt invalides JavaScript: `var init = {&quot;userID&quot;:123, ...};`
- JavaScript Syntax Error: "expected property name, got '&'"
- `init` Variable wird nie definiert
- `options.js:6` wirft: "Uncaught ReferenceError: init is not defined"

**Auswirkung:**
- JavaScript kann Session-Daten nicht parsen
- App denkt User ist nicht eingeloggt
- Keine UI-Elemente werden angezeigt
- Nur Hintergrund und Logo sichtbar

**Fix (NICHT IMPLEMENTIERT):**
```php
var init = <?= json_encode($this->data['session'] ?? []) ?>;
```

---

### Bug #2: Session-Mismatch zwischen Landing Page und Map
**Problem:**
- Landing Page (`index.php`) verwendet **File-based Sessions**
- Map (`tripwire.php`) verwendet **Redis-based Sessions**
- Session-IDs sind unterschiedlich
- Session-Daten gehen verloren beim Wechsel von Landing zu Map

**Beweise:**
1. `test_map.php` zeigt: Session ist leer trotz Login
2. `session_check.php` zeigt: File-Session und Redis-Session haben unterschiedliche Daten
3. `UserService->getUserData()` gibt leeres Array zurück weil `$_SESSION['userID']` nicht existiert

**Auswirkung:**
- User loggt sich auf Landing Page ein (File-Session)
- Geht zur Map → Redis-Session hat keine User-Daten
- Map denkt User ist nicht eingeloggt
- Redirect zur Landing oder nur Logo

**Mögliche Lösungen (NICHT IMPLEMENTIERT):**

**Option 1: Alles auf Redis**
- Landing Page auch auf Redis-Sessions umstellen
- `index.php` müsste RedisSessionHandler::init() aufrufen
- Vorteil: Konsistenz, besser skalierbar
- Nachteil: Mehr Änderungen erforderlich

**Option 2: Alles auf Files**
- tripwire.php auf File-Sessions umstellen
- RedisSessionHandler entfernen oder deaktivieren
- Vorteil: Einfachste Lösung, weniger Änderungen
- Nachteil: Keine Redis-Vorteile (Performance, Skalierung)

**Option 3: Session-Migration**
- Beim Wechsel von Landing zu Map: Session-Daten von File nach Redis kopieren
- Komplexer, aber erlaubt unterschiedliche Session-Backends

---

## 🔧 Erstellte Debug-Dateien

| Datei | Zweck |
|-------|-------|
| `/public/sso.php` | Unified EVE SSO Callback Router |
| `/public/login_sso_simple.php` | Simplified Login mit Debug-Output |
| `/public/setup_admin.php` | Manuelle Admin-Elevation |
| `/public/debug_login.php` | Login Debug Wrapper |
| `/public/test_map.php` | Session Verification für Map |
| `/public/tripwire_debug.php` | Tripwire Loading Debug |
| `/public/map_debug.php` | Map Loading mit Error Catching |
| `/public/redis_test.php` | Redis Connection Test |
| `/public/redis_handler_debug.php` | RedisSessionHandler Detail Debug |
| `/public/session_check.php` | Session Mismatch Detection |

---

## 📊 Aktueller Status

### Was funktioniert ✅
- Docker Container laufen alle
- NGINX/PHP-FPM/MySQL/Redis/WebSocket healthy
- Static Assets laden korrekt
- EVE SSO Authentifizierung funktioniert
- User Registrierung erfolgreich
- Login speichert Session (in Files)
- Redis Connection funktioniert
- RedisSessionHandler initialisiert korrekt

### Was NICHT funktioniert ❌
- Map-Interface zeigt keine UI-Elemente
- JavaScript kann Session-Daten nicht parsen (Syntax Error)
- Session-Daten gehen verloren beim Wechsel zur Map
- UserService sieht keinen eingeloggten User
- Keine Map-Funktionalität verfügbar

---

## 🎯 Geplante Nächste Schritte

### Schritt 1: JSON Ausgabe Fix (KRITISCH)
**Datei:** `/views/SystemView.php`
**Zeile:** 173
**Änderung:**
```php
// VORHER (FALSCH):
var init = <?= htmlspecialchars(json_encode($this->data['session'] ?? []), ENT_QUOTES, 'UTF-8') ?>;

// NACHHER (RICHTIG):
var init = <?= json_encode($this->data['session'] ?? []) ?>;
```

**Warum sicher:**
- `json_encode()` escaped bereits alle gefährlichen Zeichen korrekt
- JSON hat eigene Escape-Mechanik (z.B. `\"`  für quotes)
- HTML-Escaping von JSON macht es invalide
- Dieser Code sitzt in einem `<script>` Block, nicht in HTML-Kontext

### Schritt 2: Session-System Vereinheitlichen (KRITISCH)

**Empfohlener Ansatz: Option 2 (Alles auf Files)**

**Grund:**
- Einfachste und schnellste Lösung
- Minimale Code-Änderungen
- Redis kann weiter für Caching genutzt werden
- Sessions sind nicht performance-kritisch bei kleiner User-Basis

**Änderungen:**

**A) `/public/index.php`**
- Session-close Code ENTFERNEN (Zeilen 15-18)
- Normale File-Session beibehalten

**B) `/tripwire.php`**
- RedisSessionHandler::init() durch normale session_start() ersetzen
- Zeilen 3-24 ersetzen durch:
```php
// Start secure file-based session
if (!session_id()) {
    session_start([
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'use_only_cookies' => true
    ]);
}
```

**Alternative: Option 1 (Alles auf Redis)**

Falls Redis-Sessions gewünscht:

**A) `/public/index.php`**
- Session-close Code ENTFERNEN
- RedisSessionHandler vor session_start() initialisieren
- Gleiche Logik wie in tripwire.php

**B) Auch `/public/login.php`, `/public/register.php` anpassen

### Schritt 3: Testing nach Fixes
1. Login durchführen
2. Zu Map navigieren (`/?system=`)
3. Browser Console prüfen:
   - Kein JavaScript Syntax Error
   - `init` Variable definiert und gefüllt
4. Prüfen ob UI-Elemente erscheinen:
   - Top Bar mit User-Name
   - Map Canvas
   - Signature Panels
   - Admin Interface (falls Admin)

### Schritt 4: EVE SDE Import (OPTIONAL)
- System-Search wird aktuell nicht funktionieren
- Fällt immer auf Jita zurück
- Für volle Funktionalität: EVE Static Data Export importieren

### Schritt 5: Admin-Rechte vergeben
- `http://192.168.2.150:8081/setup_admin.php?allow_remote=1` aufrufen
- Character "Discordia Jezzman" Admin-Rechte geben

### Schritt 6: Cleanup
Nach erfolgreichen Tests alle Debug-Dateien entfernen:
```bash
rm /public/debug_login.php
rm /public/test_map.php
rm /public/tripwire_debug.php
rm /public/map_debug.php
rm /public/redis_test.php
rm /public/redis_handler_debug.php
rm /public/session_check.php
rm /public/setup_admin.php  # Nach Admin-Setup
```

---

## 🔍 Root Cause Analysis

### "Ist denn überhaupt eine Map implementiert nach der Modernisierung?"

**ANTWORT: JA, aber mit fundamentalen Bugs!**

Die Map ist implementiert, aber die "Modernisierung" hat **zwei kritische Fehler** eingeführt:

1. **Security-over-Funktionalität**: Jemand hat `htmlspecialchars()` überall angewendet, auch auf JSON-Daten wo es nicht hingehört
   - Gut gemeint (XSS-Schutz)
   - Falsch umgesetzt (zerstört JSON-Syntax)
   - Nicht getestet (JavaScript Syntax Errors übersehen)

2. **Session-Backend Inkonsistenz**: Landing Page und Map nutzen unterschiedliche Session-Systeme
   - Vermutlich wollte jemand Redis für Performance
   - Nur teilweise implementiert
   - Session-Migration vergessen

3. **Fehlende End-to-End Tests**: Diese Bugs wären sofort aufgefallen bei:
   - Login + Map-Zugriff Test
   - Browser Console Check
   - JavaScript Error Monitoring

---

## 📝 Lessons Learned

1. **Security-Features müssen Kontext-bewusst sein**
   - HTML-Escaping ≠ JavaScript-Escaping ≠ JSON
   - `htmlspecialchars()` nur für HTML-Kontext
   - JSON braucht `json_encode()` ohne weiteres Escaping

2. **Session-Backend-Migration braucht Strategie**
   - Entweder ganz oder gar nicht
   - Session-Daten müssen überall verfügbar sein
   - Keine stillen Failures

3. **Modernisierung braucht Testing**
   - Code-Änderungen ohne Tests sind gefährlich
   - Browser Console Errors sind kritische Hinweise
   - End-to-End Tests vor Deployment

---

## 🛠️ Benötigte Zeit für Fixes

**Geschätzt: 15-30 Minuten**

- JSON Output Fix: 2 Minuten
- Session System Vereinheitlichung: 10-20 Minuten (je nach Option)
- Testing: 5-10 Minuten

---

## 📚 Technische Details

### Betroffene Dateien (Bereits geändert)
- `/config.php` - CDN Domain, EVE SSO
- `/esi.class.php` - Error Handling
- `/tripwire.php` - RedisService require, Session handling
- `/public/index.php` - Session conflict fix
- `/services/RedisService.php` - Hostname, ping() check
- `/services/RedisSessionHandler.php` - Return type
- `/controllers/SystemController.php` - EVE SDE fallback
- `/redis/redis.conf` - Protected mode

### Betroffene Dateien (Fix benötigt)
- `/views/SystemView.php` - JSON output (Zeile 173)
- `/tripwire.php` ODER `/public/index.php` - Session system unification

---

## 🎖️ Erfolge

Trotz der fundamentalen Bugs haben wir:
- ✅ Vollständiges Docker-Setup zum Laufen gebracht
- ✅ EVE SSO Integration implementiert
- ✅ Redis Connection konfiguriert
- ✅ User-Registrierung ermöglicht
- ✅ Root Cause der Map-Probleme identifiziert
- ✅ Klare Lösungswege definiert

**Die App ist 2 kleine Fixes von vollständiger Funktionalität entfernt!**

---

**Dokumentiert am:** 04. November 2025, 00:15 Uhr
**Nächster Schritt:** JSON & Session Fixes implementieren

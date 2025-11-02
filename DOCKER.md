# Tripwire Docker Setup

Dieses Projekt enthält **3 Docker-Compose-Konfigurationen** für verschiedene Einsatzzwecke.

## 📋 Welche Datei wofür?

### 🟢 `docker-compose.yml` - **Standard Production Setup**
**Nutze diese für**: Normale Production-Installation

**Enthält**:
- ✅ Nginx (Web Server)
- ✅ PHP-FPM (Application)
- ✅ MySQL (Database)
- ✅ Redis (Cache & Sessions)
- ✅ WebSocket Server (Real-time)

**Ports**:
- `80/443` - Web
- `3306` - MySQL
- `6379` - Redis
- `8080` - WebSocket

**Starten**:
```bash
docker-compose up -d
```

---

### 🟡 `docker-compose.dev.yml` - **Development Setup**
**Nutze diese für**: Lokale Entwicklung

**Unterschiede zu Production**:
- 🔧 Read-Write Volumes (für Code-Änderungen)
- 🔧 XDebug aktiviert
- 🔧 Error Reporting aktiviert
- 🔧 OPCache deaktiviert
- 🔧 Adminer (DB-Admin-Tool) auf Port 8081
- 🔧 Andere Ports (8080 statt 80, 3307 statt 3306)

**Starten**:
```bash
docker-compose -f docker-compose.dev.yml up -d

# Zugriff auf Adminer:
# http://localhost:8081
```

---

### 🔴 `docker-compose.prod.yml` - **Production + Monitoring**
**Nutze diese für**: Production mit zusätzlichem Monitoring

**Zusätzlich zu Standard Production**:
- 📊 Watchtower (Auto-Update Container)
- 🔒 SSL-Zertifikate-Volume
- 🚀 Optimierte MySQL-Settings
- 📈 Production-Only Features

**Starten**:
```bash
docker-compose -f docker-compose.prod.yml up -d
```

---

## 🎯 Empfehlung

### Für die meisten Nutzer:
```bash
# Einfach nutzen:
docker-compose up -d

# Oder explizit:
docker-compose -f docker-compose.yml up -d
```

### Für Entwickler:
```bash
docker-compose -f docker-compose.dev.yml up -d
```

### Für Production mit Monitoring:
```bash
docker-compose -f docker-compose.prod.yml up -d
```

---

## 🔄 Dateien kombinieren (Optional)

Du kannst auch mehrere Dateien kombinieren:

```bash
# Base + Dev-Overrides
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Base + Prod-Overrides
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

---

## 🧹 Willst du es vereinfachen?

Falls du **nur eine** Datei möchtest, können wir:

1. **Option A**: Nur `docker-compose.yml` behalten
2. **Option B**: Die anderen in einen `docker/` Unterordner verschieben
3. **Option C**: Ein Makefile/Script erstellen für einfache Nutzung

Was bevorzugst du?

---

## 📝 Zusammenfassung

| Datei | Zweck | Nutzen wenn |
|-------|-------|-------------|
| `docker-compose.yml` | Standard Production | Normal-Installation |
| `docker-compose.dev.yml` | Development | Du entwickelst Code |
| `docker-compose.prod.yml` | Production + Monitoring | Enterprise-Setup |

**Tipp**: Für 99% der Nutzer reicht `docker-compose.yml`! 🎯


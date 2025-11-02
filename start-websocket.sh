#!/bin/bash

# Tripwire WebSocket Server Start-Script
# Usage: ./start-websocket.sh [start|stop|restart|status]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WS_SCRIPT="$SCRIPT_DIR/websockets/WebSocketServer.php"
PID_FILE="/var/run/tripwire-websocket.pid"
LOG_DIR="$SCRIPT_DIR/logs"
LOG_FILE="$LOG_DIR/websocket.log"
ERROR_LOG="$LOG_DIR/websocket-error.log"

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Log-Verzeichnis erstellen
mkdir -p "$LOG_DIR"

function check_composer() {
    if [ ! -d "$SCRIPT_DIR/vendor" ]; then
        echo -e "${RED}[ERROR]${NC} Composer dependencies not installed"
        echo "Run: composer install"
        exit 1
    fi
}

function start_server() {
    check_composer
    
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p "$PID" > /dev/null 2>&1; then
            echo -e "${YELLOW}[WARNING]${NC} WebSocket server already running (PID: $PID)"
            exit 1
        else
            echo -e "${YELLOW}[WARNING]${NC} Stale PID file found, removing..."
            rm -f "$PID_FILE"
        fi
    fi
    
    echo -e "${GREEN}[INFO]${NC} Starting Tripwire WebSocket Server..."
    
    nohup php "$WS_SCRIPT" >> "$LOG_FILE" 2>> "$ERROR_LOG" &
    PID=$!
    echo $PID > "$PID_FILE"
    
    sleep 2
    
    if ps -p "$PID" > /dev/null 2>&1; then
        echo -e "${GREEN}[SUCCESS]${NC} WebSocket server started (PID: $PID)"
        echo "Logs: $LOG_FILE"
        echo "Errors: $ERROR_LOG"
    else
        echo -e "${RED}[ERROR]${NC} Failed to start WebSocket server"
        echo "Check error log: $ERROR_LOG"
        rm -f "$PID_FILE"
        exit 1
    fi
}

function stop_server() {
    if [ ! -f "$PID_FILE" ]; then
        echo -e "${YELLOW}[WARNING]${NC} No PID file found"
        exit 1
    fi
    
    PID=$(cat "$PID_FILE")
    
    if ! ps -p "$PID" > /dev/null 2>&1; then
        echo -e "${YELLOW}[WARNING]${NC} WebSocket server not running"
        rm -f "$PID_FILE"
        exit 1
    fi
    
    echo -e "${GREEN}[INFO]${NC} Stopping WebSocket server (PID: $PID)..."
    kill -TERM "$PID"
    
    # Warte max 10 Sekunden
    for i in {1..10}; do
        if ! ps -p "$PID" > /dev/null 2>&1; then
            echo -e "${GREEN}[SUCCESS]${NC} WebSocket server stopped"
            rm -f "$PID_FILE"
            exit 0
        fi
        sleep 1
    done
    
    # Force kill wenn noch läuft
    echo -e "${YELLOW}[WARNING]${NC} Force killing server..."
    kill -KILL "$PID"
    rm -f "$PID_FILE"
    echo -e "${GREEN}[SUCCESS]${NC} WebSocket server stopped (forced)"
}

function restart_server() {
    echo -e "${GREEN}[INFO]${NC} Restarting WebSocket server..."
    stop_server
    sleep 2
    start_server
}

function server_status() {
    if [ ! -f "$PID_FILE" ]; then
        echo -e "${RED}[STATUS]${NC} WebSocket server is NOT running"
        exit 1
    fi
    
    PID=$(cat "$PID_FILE")
    
    if ps -p "$PID" > /dev/null 2>&1; then
        UPTIME=$(ps -p "$PID" -o etime= | tr -d ' ')
        MEM=$(ps -p "$PID" -o rss= | awk '{printf "%.2f MB", $1/1024}')
        echo -e "${GREEN}[STATUS]${NC} WebSocket server is running"
        echo "  PID:     $PID"
        echo "  Uptime:  $UPTIME"
        echo "  Memory:  $MEM"
        echo "  Port:    8080"
        
        # Verbindungen zählen
        CONNECTIONS=$(ss -tn | grep :8080 | wc -l)
        echo "  Connections: $CONNECTIONS"
    else
        echo -e "${RED}[STATUS]${NC} WebSocket server is NOT running (stale PID file)"
        rm -f "$PID_FILE"
        exit 1
    fi
}

function show_logs() {
    if [ -f "$LOG_FILE" ]; then
        echo -e "${GREEN}[INFO]${NC} Showing last 50 lines of log..."
        tail -n 50 "$LOG_FILE"
    else
        echo -e "${YELLOW}[WARNING]${NC} No log file found"
    fi
}

function show_errors() {
    if [ -f "$ERROR_LOG" ]; then
        echo -e "${GREEN}[INFO]${NC} Showing last 50 lines of error log..."
        tail -n 50 "$ERROR_LOG"
    else
        echo -e "${YELLOW}[WARNING]${NC} No error log found"
    fi
}

# Main
case "$1" in
    start)
        start_server
        ;;
    stop)
        stop_server
        ;;
    restart)
        restart_server
        ;;
    status)
        server_status
        ;;
    logs)
        show_logs
        ;;
    errors)
        show_errors
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|logs|errors}"
        echo ""
        echo "Commands:"
        echo "  start   - Start WebSocket server"
        echo "  stop    - Stop WebSocket server"
        echo "  restart - Restart WebSocket server"
        echo "  status  - Show server status"
        echo "  logs    - Show recent logs"
        echo "  errors  - Show recent errors"
        exit 1
        ;;
esac


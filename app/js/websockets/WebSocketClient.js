/**
 * Tripwire WebSocket Client
 * Handles real-time communication with the server
 */
class TripwireWebSocket {
    constructor() {
        this.ws = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000; // Start with 1 second
        this.maxReconnectDelay = 30000; // Max 30 seconds
        this.pingInterval = null;
        this.isConnected = false;
        this.maskId = null;
        this.systemId = null;

        // Event callbacks
        this.onConnect = null;
        this.onDisconnect = null;
        this.onMessage = null;
        this.onError = null;
        this.onSignatureUpdate = null;
        this.onWormholeUpdate = null;
    }

    /**
     * Connect to WebSocket server
     * @param {string} maskId - User's mask ID
     * @param {number} systemId - Current system ID
     */
    connect(maskId, systemId) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            console.log('WebSocket already connected');
            return;
        }

        this.maskId = maskId;
        this.systemId = systemId;

        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}:8080`;

        console.log('Connecting to WebSocket:', wsUrl);

        try {
            this.ws = new WebSocket(wsUrl);

            this.ws.onopen = (event) => this.handleOpen(event);
            this.ws.onmessage = (event) => this.handleMessage(event);
            this.ws.onclose = (event) => this.handleClose(event);
            this.ws.onerror = (event) => this.handleError(event);

        } catch (error) {
            console.error('WebSocket connection error:', error);
            this.handleError(error);
        }
    }

    /**
     * Disconnect from WebSocket server
     */
    disconnect() {
        if (this.ws) {
            this.ws.close(1000, 'Client disconnect');
        }
        this.stopPing();
        this.isConnected = false;
    }

    /**
     * Handle successful connection
     */
    handleOpen(event) {
        console.log('WebSocket connected');
        this.isConnected = true;
        this.reconnectAttempts = 0;
        this.reconnectDelay = 1000;

        // Start ping/pong for connection health
        this.startPing();

        // Subscribe to updates
        this.subscribe();

        if (this.onConnect) {
            this.onConnect(event);
        }
    }

    /**
     * Handle incoming messages
     */
    handleMessage(event) {
        try {
            const data = JSON.parse(event.data);

            if (this.onMessage) {
                this.onMessage(data);
            }

            switch (data.action) {
                case 'subscribed':
                    console.log('Successfully subscribed to updates');
                    break;

                case 'unsubscribed':
                    console.log('Successfully unsubscribed from updates');
                    break;

                case 'initial_data':
                    this.handleInitialData(data);
                    break;

                case 'update':
                    this.handleUpdate(data);
                    break;

                case 'pong':
                    // Connection is healthy
                    break;

                case 'error':
                    console.error('WebSocket error:', data.error);
                    if (this.onError) {
                        this.onError(new Error(data.error));
                    }
                    break;

                default:
                    console.log('Unknown message type:', data.action);
            }

        } catch (error) {
            console.error('Error parsing WebSocket message:', error);
        }
    }

    /**
     * Handle connection close
     */
    handleClose(event) {
        console.log('WebSocket disconnected:', event.code, event.reason);
        this.isConnected = false;
        this.stopPing();

        if (this.onDisconnect) {
            this.onDisconnect(event);
        }

        // Attempt to reconnect if not a clean disconnect
        if (event.code !== 1000) {
            this.attemptReconnect();
        }
    }

    /**
     * Handle connection errors
     */
    handleError(event) {
        console.error('WebSocket error:', event);
        this.isConnected = false;

        if (this.onError) {
            this.onError(event);
        }
    }

    /**
     * Subscribe to system updates
     */
    subscribe() {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
            console.warn('Cannot subscribe: WebSocket not connected');
            return;
        }

        const message = {
            action: 'subscribe',
            maskId: this.maskId,
            systemId: this.systemId
        };

        this.ws.send(JSON.stringify(message));
    }

    /**
     * Unsubscribe from updates
     */
    unsubscribe() {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
            return;
        }

        const message = {
            action: 'unsubscribe'
        };

        this.ws.send(JSON.stringify(message));
    }

    /**
     * Handle initial data from server
     */
    handleInitialData(data) {
        console.log('Received initial data:', data);

        if (data.signatures) {
            // Update signatures in tripwire client
            if (window.tripwire && window.tripwire.client) {
                window.tripwire.client.signatures = {};
                data.signatures.forEach(sig => {
                    window.tripwire.client.signatures[sig.id] = sig;
                });
            }
        }

        if (data.wormholes) {
            // Update wormholes in tripwire client
            if (window.tripwire && window.tripwire.client) {
                window.tripwire.client.wormholes = {};
                data.wormholes.forEach(wh => {
                    window.tripwire.client.wormholes[wh.id] = wh;
                });
            }
        }

        // Trigger UI update
        if (window.tripwire && window.tripwire.sync) {
            window.tripwire.sync();
        }
    }

    /**
     * Handle real-time updates
     */
    handleUpdate(data) {
        console.log('Received update:', data);

        switch (data.type) {
            case 'signature':
                this.handleSignatureUpdate(data.data);
                break;

            case 'wormhole':
                this.handleWormholeUpdate(data.data);
                break;

            default:
                console.log('Unknown update type:', data.type);
        }
    }

    /**
     * Handle signature updates
     */
    handleSignatureUpdate(signatureData) {
        if (window.tripwire && window.tripwire.client) {
            // Update local signature data
            window.tripwire.client.signatures[signatureData.id] = signatureData;

            // Trigger UI update for this signature
            if (window.tripwire.signature && window.tripwire.signature.update) {
                window.tripwire.signature.update(signatureData);
            }
        }

        if (this.onSignatureUpdate) {
            this.onSignatureUpdate(signatureData);
        }
    }

    /**
     * Handle wormhole updates
     */
    handleWormholeUpdate(wormholeData) {
        if (window.tripwire && window.tripwire.client) {
            // Update local wormhole data
            window.tripwire.client.wormholes[wormholeData.id] = wormholeData;

            // Trigger UI update for this wormhole
            if (window.tripwire.wormhole && window.tripwire.wormhole.update) {
                window.tripwire.wormhole.update(wormholeData);
            }
        }

        if (this.onWormholeUpdate) {
            this.onWormholeUpdate(wormholeData);
        }
    }

    /**
     * Attempt to reconnect with exponential backoff
     */
    attemptReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('Max reconnection attempts reached');
            return;
        }

        this.reconnectAttempts++;
        console.log(`Attempting reconnection ${this.reconnectAttempts}/${this.maxReconnectAttempts} in ${this.reconnectDelay}ms`);

        setTimeout(() => {
            if (this.maskId && this.systemId) {
                this.connect(this.maskId, this.systemId);
            }
        }, this.reconnectDelay);

        // Exponential backoff
        this.reconnectDelay = Math.min(this.reconnectDelay * 2, this.maxReconnectDelay);
    }

    /**
     * Start ping/pong to keep connection alive
     */
    startPing() {
        this.pingInterval = setInterval(() => {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify({ action: 'ping' }));
            }
        }, 30000); // Ping every 30 seconds
    }

    /**
     * Stop ping/pong
     */
    stopPing() {
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }
    }

    /**
     * Check if WebSocket is connected
     */
    isConnected() {
        return this.isConnected && this.ws && this.ws.readyState === WebSocket.OPEN;
    }

    /**
     * Get connection status
     */
    getStatus() {
        if (!this.ws) return 'disconnected';

        switch (this.ws.readyState) {
            case WebSocket.CONNECTING:
                return 'connecting';
            case WebSocket.OPEN:
                return 'connected';
            case WebSocket.CLOSING:
                return 'closing';
            case WebSocket.CLOSED:
                return 'disconnected';
            default:
                return 'unknown';
        }
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TripwireWebSocket;
}

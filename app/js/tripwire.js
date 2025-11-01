var tripwire = new function() {
    this.timer, this.xhr;
	this.version = version;
	this.client = {signatures: {}, wormholes: {}};
	this.server = {signatures: {}, wormholes: {}};
	this.signatures = {list: {}, undo: JSON.parse(sessionStorage.getItem("tripwire_undo")) || {}, redo: JSON.parse(sessionStorage.getItem("tripwire_redo")) || {}};
	this.activity = {};
	this.data = {tracking: {}, esi: {}};
	this.refreshRate = 5000; // Fallback polling rate
	this.connected = true;
	this.ageFormat = "HM";
	this.instance = window.name ? window.name : (new Date().getTime() / 1000, window.name = new Date().getTime() / 1000);

	// WebSocket client
	this.wsClient = null;
	this.useWebSocket = true; // Try WebSocket first, fallback to polling

    // Command to start/stop tripwire updates
	// ToDo: Include API and Server timers
	this.stop = function() {
		clearTimeout(this.timer);

		// Stop WebSocket connection
		if (this.wsClient) {
			this.wsClient.disconnect();
		}

		return this.timer;
	};

	this.start = function() {
		// Try WebSocket first
		if (this.useWebSocket && this.initWebSocket()) {
			console.log('Using WebSocket for real-time updates');
			return true;
		} else {
			// Fallback to polling
			console.log('Falling back to polling for updates');
			return this.sync();
		}
	};

	// Initialize WebSocket connection
	this.initWebSocket = function() {
		try {
			// Import WebSocket client (will be loaded by build system)
			if (typeof TripwireWebSocket === 'undefined') {
				console.warn('WebSocket client not available, falling back to polling');
				return false;
			}

			this.wsClient = new TripwireWebSocket();

			// Set up event handlers
			this.wsClient.onConnect = () => {
				console.log('WebSocket connected');
				this.connected = true;
			};

			this.wsClient.onDisconnect = () => {
				console.log('WebSocket disconnected');
				this.connected = false;
				// Fallback to polling if WebSocket fails
				setTimeout(() => this.sync(), 1000);
			};

			this.wsClient.onMessage = (data) => {
				this.handleWebSocketMessage(data);
			};

			this.wsClient.onError = (error) => {
				console.error('WebSocket error:', error);
			};

			// Get current system and mask info
			const systemId = window.systemID || init.systemID;
			const maskId = init.mask || null;

			if (systemId && maskId) {
				this.wsClient.connect(maskId, parseInt(systemId));
				return true;
			} else {
				console.warn('Missing systemId or maskId for WebSocket connection');
				return false;
			}

		} catch (error) {
			console.error('Failed to initialize WebSocket:', error);
			return false;
		}
	};

	// Handle WebSocket messages
	this.handleWebSocketMessage = function(data) {
		switch (data.action) {
			case 'update':
				this.handleRealTimeUpdate(data);
				break;
			case 'initial_data':
				this.handleInitialData(data);
				break;
			default:
				console.log('Unhandled WebSocket message:', data.action);
		}
	};

	// Handle real-time updates from WebSocket
	this.handleRealTimeUpdate = function(data) {
		console.log('Real-time update received:', data);

		// Update local data
		if (data.type === 'signature' && data.data) {
			this.client.signatures[data.data.id] = data.data;
		} else if (data.type === 'wormhole' && data.data) {
			this.client.wormholes[data.data.id] = data.data;
		}

		// Trigger UI updates
		this.refresh();
	};

	// Handle initial data from WebSocket
	this.handleInitialData = function(data) {
		console.log('Initial data received via WebSocket');

		if (data.signatures) {
			data.signatures.forEach(sig => {
				this.client.signatures[sig.id] = sig;
			});
		}

		if (data.wormholes) {
			data.wormholes.forEach(wh => {
				this.client.wormholes[wh.id] = wh;
			});
		}

		// Initial UI update
		this.refresh();
	};

    // Command to change Age format
	// ToDo: Cookie change to keep
	this.setAgeFormat = function(format) {
		var format = typeof(format) !== 'undefined' ? format : this.ageFormat;

		$("span[data-age]").each(function() {
			$(this).countdown("option", {format: format});
		});

		return true;
	}

    this.serverTime = function() {
		this.time;

		this.serverTime.getTime = function() {
			return tripwire.serverTime.time;
		}
	}
    this.serverTime();

    // Handles putting chain together
	this.chainMap = function() {
		this.chainMap.parse = function(data) {
			chain.draw(data);
		}
	}
    this.chainMap();

    this.pastEOL = function() {
		var options = {since: $(this).countdown('option', 'until'), format: "HM", layout: "-{hnn}{sep}{mnn}&nbsp;"};
		$(this).countdown("option", options);
	}

    // Handles WH Type hover-over tooltip
	// ToDo: Use native JS
	this.whTooltip = function(sig) {
		if (viewingSystemID == sig.systemID) {
			if ($.inArray(sig.type, $.map(tripwire.wormholes, function(item, index) { return index;})) >= 0) {
				var type = sig.type;
				var tooltip = '';
			} else {
				var type = sig.sig2Type;
				var tooltip = "<b>Type:</b> "+type+"<br/>";
			}
		} else {
			if ($.inArray(sig.sig2Type, $.map(tripwire.wormholes, function(item, index) { return index;})) >= 0) {
				var type = sig.sig2Type;
				var tooltip = '';
			} else {
				var type = sig.type;
				var tooltip = "<b>Type:</b> "+type+"<br/>";
			}
		}

		if ($.inArray(type, $.map(tripwire.wormholes, function(item, index) { return index;})) >= 0) {
			var whType = true;
		} else {
			var whType = false;
		}

		tooltip += "<b>Life:</b> "+(whType?tripwire.wormholes[type].life:"Unknown")+"<br/>";

		if (whType) {
			switch (tripwire.wormholes[type].leadsTo.split(" ")[0]) {
				case 'High-Sec':
					tooltip += "<b>Leads To:</b> <span class='hisec'>"+tripwire.wormholes[type].leadsTo+"</span><br/>";
					break;
				case 'Low-Sec':
					tooltip += "<b>Leads To:</b> <span class='lowsec'>"+tripwire.wormholes[type].leadsTo+"</span><br/>";
					break;
				case 'Null-Sec':
					tooltip += "<b>Leads To:</b> <span class='nullsec'>"+tripwire.wormholes[type].leadsTo+"</span><br/>";
					break;
				case 'Class':
					tooltip += "<b>Leads To:</b> <span class='wh'>"+tripwire.wormholes[type].leadsTo+"</span><br/>";
					break;
				default:
					tooltip += "<b>Leads To:</b> <span>"+tripwire.wormholes[type].leadsTo+"</span><br/>";
			}
		} else {
			tooltip += "<b>Leads To:</b> <span>Unknown</span><br/>";
		}

		tooltip += "<b>Max Mass</b>: "+(whType?numFormat(tripwire.wormholes[type].mass):"Unknown")+" Kg<br/>";

		tooltip += "<b>Max Jumpable</b>: "+(whType?numFormat(tripwire.wormholes[type].jump):"Unknown")+" Kg<br/>";

		return tooltip;
	}

	// Handles Age hover-over tooltip
	// ToDo: Use native JS
	this.ageTooltip = function(sig) {
		// var date = new Date(sig.lifeTime);
		// var localOffset = date.getTimezoneOffset() * 60000;
		// date = new Date(date.getTime() + localOffset);
        var date = new Date(sig.lifeTime);

		var tooltip = "<table class=\"age-tooltip-table\"><tr>"
        + "<th>Created:</th><td>"+(date.getMonth()+1)+"/"+date.getDate()+" "+(date.getHours() < 10?'0':'')+date.getHours()+":"+(date.getMinutes() < 10?'0':'')+date.getMinutes()+"</td>"
        + "<td>"+sig.createdByName.replace(/'/g, '&#39;').replace(/"/g, '&#34;')+"</td>"
        + "</tr>";

		if (sig.lifeTime != sig.modifiedTime) {
			date = new Date(sig.modifiedTime);
			// localOffset = date.getTimezoneOffset() * 60000;
			// date = new Date(date.getTime() + localOffset);
      
			tooltip += "<tr><th>Last Modified:</th><td>"+(date.getMonth()+1)+"/"+date.getDate()+" "+(date.getHours() < 10?'0':'')+date.getHours()+":"+(date.getMinutes() < 10?'0':'')+date.getMinutes()+"</td>"
          + "<td>"+sig.modifiedByName.replace(/'/g, '&#39;').replace(/"/g, '&#34;')+"</td>"
          + "</tr>";
		}

		tooltip += "</table>";

		return tooltip;
	}

    this.refresh = function(mode, data, successCallback, alwaysCallback) {
		var mode = mode || 'refresh';

		this.sync(mode, data, successCallback, alwaysCallback);
	}
}

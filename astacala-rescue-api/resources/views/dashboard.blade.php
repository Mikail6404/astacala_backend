<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Astacala Rescue Dashboard</title>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .dashboard {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .status-panel {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .websocket-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            color: white;
            font-weight: bold;
        }
        .connected { background-color: #27ae60; }
        .disconnected { background-color: #e74c3c; }
        .connecting { background-color: #f39c12; }
        .event-log {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-height: 400px;
            overflow-y: auto;
        }
        .event-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
        }
        .event-time {
            color: #666;
            font-size: 0.8em;
        }
        .event-type {
            font-weight: bold;
            color: #2c3e50;
        }
        .button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .button:hover {
            background: #2980b9;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="header">
            <h1>🚨 Astacala Rescue Dashboard</h1>
            <p>Real-time monitoring and management system</p>
        </div>

        <div class="status-panel">
            <h2>Connection Status</h2>
            <span id="websocket-status" class="websocket-status disconnected">Disconnected</span>
            <button onclick="connectWebSocket()" class="button">Connect</button>
            <button onclick="disconnectWebSocket()" class="button">Disconnect</button>
            <button onclick="testEvents()" class="button">Test Events</button>
            <button onclick="clearLog()" class="button">Clear Log</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="total-reports">0</div>
                <div class="stat-label">Total Reports</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="pending-reports">0</div>
                <div class="stat-label">Pending Reports</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="active-volunteers">0</div>
                <div class="stat-label">Active Volunteers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="events-received">0</div>
                <div class="stat-label">Events Received</div>
            </div>
        </div>

        <div class="event-log">
            <h2>Real-time Event Log</h2>
            <div id="event-container">
                <div class="event-item">
                    <div class="event-time">Waiting for events...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let pusher = null;
        let channel = null;
        let eventsReceived = 0;

        function updateStatus(status, text) {
            const statusElement = document.getElementById('websocket-status');
            statusElement.className = `websocket-status ${status}`;
            statusElement.textContent = text;
        }

        function addEvent(type, data, timestamp = null) {
            const container = document.getElementById('event-container');
            const eventItem = document.createElement('div');
            eventItem.className = 'event-item';
            
            const time = timestamp || new Date().toLocaleTimeString();
            
            eventItem.innerHTML = `
                <div class="event-time">${time}</div>
                <div class="event-type">${type}</div>
                <div class="event-data">${JSON.stringify(data, null, 2)}</div>
            `;
            
            container.insertBefore(eventItem, container.firstChild);
            
            eventsReceived++;
            document.getElementById('events-received').textContent = eventsReceived;
        }

        function connectWebSocket() {
            try {
                updateStatus('connecting', 'Connecting...');
                
                // Initialize Pusher with the same config as mobile app
                pusher = new Pusher('tzqjqphkvinqop5drju6', {
                    cluster: 'mt1',
                    wsHost: '127.0.0.1',
                    wsPort: 8080,
                    wssPort: 8080,
                    forceTLS: false,
                    enabledTransports: ['ws'],
                    disableStats: true,
                });

                pusher.connection.bind('connected', function() {
                    updateStatus('connected', 'Connected');
                    addEvent('CONNECTION', { status: 'Connected to WebSocket server' });
                });

                pusher.connection.bind('disconnected', function() {
                    updateStatus('disconnected', 'Disconnected');
                    addEvent('CONNECTION', { status: 'Disconnected from WebSocket server' });
                });

                pusher.connection.bind('error', function(error) {
                    updateStatus('disconnected', 'Connection Error');
                    addEvent('ERROR', { error: error });
                });

                // Subscribe to general notifications channel
                channel = pusher.subscribe('general-notifications');
                
                // Listen for disaster report events
                channel.bind('disaster-report-submitted', function(data) {
                    addEvent('DISASTER_REPORT_SUBMITTED', data);
                    // Update statistics if available
                    if (data.statistics) {
                        document.getElementById('total-reports').textContent = data.statistics.total_reports || 0;
                        document.getElementById('pending-reports').textContent = data.statistics.pending_reports || 0;
                    }
                });

                // Listen for report verification events
                channel.bind('report-verified', function(data) {
                    addEvent('REPORT_VERIFIED', data);
                });

                // Listen for admin notifications
                channel.bind('admin-notification', function(data) {
                    addEvent('ADMIN_NOTIFICATION', data);
                });

                // Listen for system notifications
                channel.bind('system-notification', function(data) {
                    addEvent('SYSTEM_NOTIFICATION', data);
                });

                addEvent('WEBSOCKET', { action: 'Connection initiated' });
                
            } catch (error) {
                updateStatus('disconnected', 'Connection Failed');
                addEvent('ERROR', { error: error.message });
            }
        }

        function disconnectWebSocket() {
            if (pusher) {
                pusher.disconnect();
                pusher = null;
                channel = null;
                updateStatus('disconnected', 'Disconnected');
                addEvent('WEBSOCKET', { action: 'Manually disconnected' });
            }
        }

        function testEvents() {
            // Test by making HTTP requests to trigger events
            fetch('/api/test-websocket-events', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ test: true })
            })
            .then(response => response.json())
            .then(data => {
                addEvent('TEST', { message: 'Test events triggered', response: data });
            })
            .catch(error => {
                addEvent('ERROR', { message: 'Failed to trigger test events', error: error.message });
            });
        }

        function clearLog() {
            document.getElementById('event-container').innerHTML = '<div class="event-item"><div class="event-time">Log cleared</div></div>';
            eventsReceived = 0;
            document.getElementById('events-received').textContent = eventsReceived;
        }

        // Load statistics on page load
        function loadStatistics() {
            fetch('/api/statistics')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        document.getElementById('total-reports').textContent = data.data.total_reports || 0;
                        document.getElementById('pending-reports').textContent = data.data.pending_reports || 0;
                    }
                })
                .catch(error => {
                    console.error('Failed to load statistics:', error);
                });
        }

        // Auto-connect on page load
        window.addEventListener('load', function() {
            loadStatistics();
            connectWebSocket();
        });
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - {{ $trackingData['order_id'] }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color:rgb(44, 153, 30);
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --info-color: #3a0ca3;
            --light-bg: #f8f9fa;
            --dark-text: #212529;
            --light-text: #6c757d;
            --border-radius: 12px;
            --box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 2rem;
        }

        .tracking-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 15px;
        }

        .tracking-header {
            background: linear-gradient(120deg, var(--primary-color), #098925);
            color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .tracking-header::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }

        .tracking-card {
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: none;
            margin-bottom: 1.5rem;
            transition: var(--transition);
            background: white;
        }

        .tracking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .card-header i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        #map {
            height: 400px;
            width: 100%;
            border-radius: 8px;
            background-color: #f0f0f0; /* Fallback background */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #map:empty::before {
            content: "Loading map...";
            color: #6c757d;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .tracking-info {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
        }

        .info-section {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px dashed #eee;
        }

        .info-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }

        .info-title i {
            margin-right: 10px;
        }

        .status-timeline {
            position: relative;
            padding: 20px 0;
        }

        .status-timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, var(--primary-color), var(--success-color));
            border-radius: 2px;
        }

        .status-item {
            position: relative;
            padding: 20px 20px 20px 50px;
            margin-bottom: 15px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: var(--transition);
            border: 1px solid #eee;
        }

        .status-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #e0e0e0;
        }

        .status-item::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #e9ecef;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #e9ecef;
            z-index: 2;
            transition: var(--transition);
        }

        .status-item.completed {
            border-left: 3px solid var(--success-color);
        }

        .status-item.completed::before {
            background: var(--success-color);
            box-shadow: 0 0 0 3px var(--success-color);
        }

        .status-item.pending {
            border-left: 3px solid #ffc107;
        }

        .status-item.pending::before {
            background: #ffc107;
            box-shadow: 0 0 0 3px #ffc107;
        }

        .status-item.active {
            border-left: 3px solid var(--primary-color);
            background: rgba(67, 97, 238, 0.03);
        }

        .status-item.active::before {
            background: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color);
            width: 22px;
            height: 22px;
        }

        .status-header {
            margin-bottom: 10px;
        }

        .status-title {
            font-weight: 600;
            margin-bottom: 0;
            color: var(--dark-text);
            font-size: 1.1rem;
        }

        .status-time {
            font-size: 0.85rem;
            color: var(--light-text);
        }

        .status-description {
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .badge-status {
            padding: 0.5em 0.8em;
            font-weight: 500;
            border-radius: 50px;
        }

        .location-marker {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid white;
            box-shadow: 0 0 0 3px rgba(40, 181, 15, 0.3);
        }

        .detail-row {
            margin-bottom: 0.75rem;
            display: flex;
        }

        .detail-label {
            font-weight: 500;
            min-width: 120px;
            color: var(--light-text);
        }

        .detail-value {
            flex: 1;
            color: var(--dark-text);
        }

        .progress-container {
            margin: 1.5rem 0;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 40px;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            height: 4px;
            width: 100%;
            background: #e0e0e0;
            transform: translateY(-50%);
            z-index: 1;
            border-radius: 2px;
        }

        .progress-bar {
            position: absolute;
            top: 50%;
            left: 0;
            height: 4px;
            width: 0%;
            background: linear-gradient(to right, var(--primary-color), var(--success-color));
            transform: translateY(-50%);
            z-index: 2;
            transition: width 0.8s cubic-bezier(0.22, 0.61, 0.36, 1);
            border-radius: 2px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #999;
            z-index: 3;
            position: relative;
            transition: var(--transition);
        }

        .step.completed {
            border-color: var(--success-color);
            background: var(--success-color);
            color: white;
        }

        .step.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
            transform: scale(1.15);
            box-shadow: 0 0 15px rgba(67, 97, 238, 0.4);
        }

        .step-label {
            position: absolute;
            top: 45px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            font-size: 0.8rem;
            color: var(--light-text);
            font-weight: 500;
        }

        .contact-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.25rem;
            background: linear-gradient(120deg, var(--primary-color), var(--info-color));
            color: white;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            width: 100%;
            box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
        }

        .contact-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(67, 97, 238, 0.4);
        }

        .contact-button:active {
            transform: translateY(-1px);
        }

        .contact-button i {
            margin-right: 8px;
        }

        .order-summary {
            background: linear-gradient(120deg, #f0f4ff, #e6f0ff);
            border-radius: var(--border-radius);
            padding: 1.25rem;
            margin-top: 1rem;
        }

        .summary-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .summary-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .summary-label {
            color: var(--light-text);
        }

        .summary-value {
            font-weight: 500;
        }

        .estimated-time {
            background: linear-gradient(120deg, #e0f7fa, #b2ebf2);
            border-radius: var(--border-radius);
            padding: 1rem;
            text-align: center;
            margin: 1.5rem 0;
        }

        .estimated-time h6 {
            color: var(--info-color);
            margin-bottom: 0.5rem;
        }

        .estimated-time p {
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 0;
            color: var(--dark-text);
        }

        .refresh-btn {
            background: rgba(67, 97, 238, 0.1);
            border: 1px solid rgba(67, 97, 238, 0.3);
            color: var(--primary-color);
            border-radius: 50px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
        }

        .refresh-btn:hover {
            background: rgba(67, 97, 238, 0.2);
        }

        .refresh-btn i {
            margin-right: 5px;
        }

        .realtime-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #4caf50;
            margin-right: 5px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.4; }
            100% { opacity: 1; }
        }

        @media (max-width: 991px) {
            .tracking-header h2 {
                font-size: 1.5rem;
            }

            #map {
                height: 300px;
            }

            .progress-steps {
                margin-bottom: 60px;
            }

            .step-label {
                top: 50px;
                font-size: 0.7rem;
            }
        }

        @media (max-width: 768px) {
            .tracking-container {
                margin: 1rem auto;
                padding: 0 10px;
            }

            .tracking-header {
                padding: 1rem;
            }

            .status-timeline::before {
                left: 15px;
            }

            .status-item {
                padding: 15px 15px 15px 40px;
            }

            .status-item::before {
                left: 8px;
                width: 15px;
                height: 15px;
            }

            .detail-label {
                min-width: 100px;
            }

            .row {
                flex-direction: column;
            }

            .col-lg-8, .col-lg-4 {
                width: 100%;
                margin-bottom: 1rem;
            }

            .tracking-info {
                margin-top: 1rem;
            }

            #map {
                height: 250px;
            }

            .progress-steps {
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
            }

            .step {
                width: 35px;
                height: 35px;
                margin: 0 5px;
            }

            .step-label {
                top: 40px;
                font-size: 0.6rem;
            }
        }

        @media (max-width: 576px) {
            .tracking-header h2 {
                font-size: 1.25rem;
            }

            .tracking-header p {
                font-size: 0.85rem;
            }

            .detail-row {
                flex-direction: column;
                gap: 5px;
            }

            .detail-label {
                min-width: auto;
                font-weight: 600;
            }

            .step {
                width: 30px;
                height: 30px;
                margin: 0 3px;
            }

            .step-label {
                top: 35px;
                font-size: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="tracking-container">
        <div class="tracking-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-0"><i class="fas fa-shipping-fast me-2"></i>Order Tracking #{{ $trackingData['order_id'] }}</h2>
                    <p class="mb-0 mt-2 opacity-75">Real-time updates on your order status and delivery progress</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <span class="badge badge-status bg-{{
                        $trackingData['order_status'] == 'delivered' ? 'success' :
                        ($trackingData['order_status'] == 'canceled' ? 'danger' :
                        ($trackingData['order_status'] == 'scheduled' ? 'success' : 'success'))
                    }}">
                        <i class="fas fa-circle me-1"></i>{{ ucfirst(str_replace('_', ' ', $trackingData['order_status'])) }}
                    </span>
                    <div class="mt-2">
                        <span class="realtime-indicator"></span>
                        <small>Real-time tracking active</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card tracking-card">
                    <div class="card-header">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Live Tracking</span>
                        <div class="ms-auto">
                            <button class="refresh-btn" id="refreshBtn">
                                <i class="fas fa-sync-alt"></i>Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="map"></div>

                        <!-- Progress Steps -->
                        <div class="progress-container">
                            <div class="progress-steps">
                                <div class="progress-bar" id="progressBar"></div>
                                <div class="step {{ in_array($trackingData['order_status'], ['pending', 'confirmed', 'processing', 'handover', 'picked_up', 'delivered', 'scheduled']) ? 'completed' : '' }}" data-step="1">
                                    <i class="fas fa-receipt"></i>
                                    <div class="step-label">Placed</div>
                                </div>
                                <div class="step {{ in_array($trackingData['order_status'], ['confirmed', 'processing', 'handover', 'picked_up', 'delivered', 'scheduled']) ? 'completed' : '' }}" data-step="2">
                                    <i class="fas fa-check-circle"></i>
                                    <div class="step-label">Confirmed</div>
                                </div>
                                <div class="step {{ in_array($trackingData['order_status'], ['processing', 'handover', 'picked_up', 'delivered', 'scheduled']) ? 'completed' : '' }}" data-step="3">
                                    <i class="fas fa-blender-phone"></i>
                                    <div class="step-label">Processing</div>
                                </div>
                                <div class="step {{ in_array($trackingData['order_status'], ['handover', 'picked_up', 'delivered', 'scheduled']) ? 'completed' : (in_array($trackingData['order_status'], ['scheduled']) ? 'active' : '') }}" data-step="4">
                                    <i class="fas fa-box-open"></i>
                                    <div class="step-label">
                                        {{ $trackingData['order_status'] == 'scheduled' ? 'Scheduled' : 'Handover' }}
                                    </div>
                                </div>
                                <div class="step {{ in_array($trackingData['order_status'], ['picked_up', 'delivered']) ? 'completed' : '' }}" data-step="5">
                                    <i class="fas fa-truck"></i>
                                    <div class="step-label">In Transit</div>
                                </div>
                                <div class="step {{ $trackingData['order_status'] == 'delivered' ? 'completed' : '' }}" data-step="6">
                                    <i class="fas fa-home"></i>
                                    <div class="step-label">Delivered</div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="tracking-info">
                    <h5 class="mb-4 pb-2 border-bottom"><i class="fas fa-info-circle me-2"></i>Order Information</h5>

                    <div class="info-section">
                        <div class="info-title">
                            <i class="fas fa-shopping-bag"></i>Order Details
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Order ID:</div>
                            <div class="detail-value">#{{ $trackingData['order_id'] }}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value">
                                <span class="badge bg-{{
                                    $trackingData['order_status'] == 'delivered' ? 'success' :
                                    ($trackingData['order_status'] == 'canceled' ? 'danger' :
                                    ($trackingData['order_status'] == 'scheduled' ? 'success' : 'success'))
                                }}>
                                    {{ ucfirst(str_replace('_', ' ', $trackingData['order_status'])) }}
                                </span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Order Date:</div>
                            <div class="detail-value">{{ $trackingData['created_at'] ?? 'N/A' }}</div>
                        </div>
                        @if($trackingData['order_status'] == 'scheduled' && isset($trackingData['schedule_at']))
                            <div class="detail-row">
                                <div class="detail-label">Scheduled For:</div>
                                <div class="detail-value">{{ $trackingData['schedule_at'] ?? 'N/A' }}</div>
                            </div>
                        @endif
                    </div>

                    @if($trackingData['store'])
                        <div class="info-section">
                            <div class="info-title">
                                <i class="fas fa-store"></i>Store Information
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Name:</div>
                                <div class="detail-value">{{ $trackingData['store']['name'] }}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Address:</div>
                                <div class="detail-value">{{ $trackingData['store']['location']['address'] ?? 'N/A' }}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Phone:</div>
                                <div class="detail-value">{{ $trackingData['store']['phone'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                    @endif

                    @if($trackingData['delivery_man'])
                        <div class="info-section">
                            <div class="info-title">
                                <i class="fas fa-user-check"></i>Delivery Person
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Name:</div>
                                <div class="detail-value">{{ $trackingData['delivery_man']['name'] }}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Phone:</div>
                                <div class="detail-value">{{ $trackingData['delivery_man']['phone'] }}</div>
                            </div>
                            @if($trackingData['delivery_man']['current_location']['latitude'] && $trackingData['delivery_man']['current_location']['longitude'])
                            <div class="detail-row">
                                <div class="detail-label">Current Location:</div>
                                <div class="detail-value">
                                    Lat: {{ number_format($trackingData['delivery_man']['current_location']['latitude'], 6) }},
                                    Lng: {{ number_format($trackingData['delivery_man']['current_location']['longitude'], 6) }}
                                </div>
                            </div>
                            @endif
                            <div class="mt-3">
                                <a href="tel:{{ $trackingData['delivery_man']['phone'] }}" class="contact-button">
                                    <i class="fas fa-phone-alt"></i>Contact Delivery Person
                                </a>
                            </div>
                        </div>
                    @endif

                    @if($trackingData['customer']['location'])
                        <div class="info-section mb-0 pb-0 border-0">
                            <div class="info-title">
                                <i class="fas fa-map-marker-alt"></i>Delivery Address
                            </div>
                            <div class="detail-row">
                                <div class="detail-value">{{ $trackingData['customer']['location']['address'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    @if($mapApiKey)
        <script src="https://maps.googleapis.com/maps/api/js?key={{ $mapApiKey }}&callback=initMap" async defer></script>
    @else
        <script src="https://maps.googleapis.com/maps/api/js?callback=initMap" async defer></script>
    @endif

    <script>
        // Global variables
        let map;
        let deliveryManMarker;
        let deliveryPathPolyline;
        let bounds;
        let websocket;
        let orderId = "{{ $trackingData['order_id'] }}";
        let isConnected = false;

        // Update progress bar based on current status
        function updateProgressBar() {
            const statuses = ['pending', 'confirmed', 'processing', 'handover', 'picked_up', 'delivered'];
            const currentStatus = "{{ $trackingData['order_status'] }}";
            const statusOrder = {};
            statuses.forEach((status, index) => {
                statusOrder[status] = index;
            });

            // Handle scheduled orders
            let currentStatusIndex;
            if (currentStatus === 'scheduled') {
                // For scheduled orders, show progress up to handover
                currentStatusIndex = 3; // handover position
            } else {
                currentStatusIndex = statusOrder[currentStatus] !== undefined ? statusOrder[currentStatus] : 0;
            }

            const progressPercentage = (currentStatusIndex / (statuses.length - 1)) * 100;
            document.getElementById('progressBar').style.width = progressPercentage + '%';

            // Update step classes
            document.querySelectorAll('.step').forEach((step, index) => {
                if (index <= currentStatusIndex) {
                    step.classList.add('completed');
                }
                if (index === currentStatusIndex) {
                    step.classList.add('active');
                }
            });
        }

        // Initialize WebSocket connection for real-time tracking
        function initWebSocket() {
            try {
                // Get the WebSocket URL from environment or use default
                const wsUrl = `ws://${window.location.hostname}:6001/app/${"{{ env('PUSHER_APP_KEY') }}" || 'app-key'}?protocol=7&client=js&version=7.4.0&flash=false`;

                websocket = new WebSocket(wsUrl);

                websocket.onopen = function(event) {
                    console.log('WebSocket connection established');
                    isConnected = true;

                    // Subscribe to order tracking updates
                    const subscribeMessage = {
                        event: 'subscribe_to_order',
                        order_id: orderId
                    };
                    websocket.send(JSON.stringify(subscribeMessage));
                };

                websocket.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    handleWebSocketMessage(data);
                };

                websocket.onclose = function(event) {
                    console.log('WebSocket connection closed');
                    isConnected = false;
                    // Try to reconnect after 5 seconds
                    setTimeout(initWebSocket, 5000);
                };

                websocket.onerror = function(error) {
                    console.error('WebSocket error:', error);
                };
            } catch (error) {
                console.error('Failed to initialize WebSocket:', error);
            }
        }

        // Handle incoming WebSocket messages
        function handleWebSocketMessage(data) {
            if (data.event === 'order_data_update') {
                updateTrackingData(data);
            } else if (data.event === 'connection_established') {
                console.log('Connected to tracking service:', data.message);
            } else if (data.event === 'subscription_confirmed') {
                console.log('Subscribed to order tracking:', data.message);
            }
        }

        // Update tracking data with real-time information
        function updateTrackingData(data) {
            // Update delivery man location on map
            if (data.map_data && data.map_data.delivery_man_location) {
                updateDeliveryManLocation(data.map_data.delivery_man_location);
            }

            // Update delivery path
            if (data.delivery_path) {
                updateDeliveryPath(data.delivery_path);
            }

            // Update delivery man information in the sidebar
            if (data.delivery_man) {
                updateDeliveryManInfo(data.delivery_man);
            }
        }

        // Update delivery man location on the map
        function updateDeliveryManLocation(location) {
            if (!map || !location.latitude || !location.longitude) return;

            const position = {
                lat: parseFloat(location.latitude),
                lng: parseFloat(location.longitude)
            };

            // Create or update delivery man marker
            if (!deliveryManMarker) {
                deliveryManMarker = new google.maps.Marker({
                    position: position,
                    map: map,
                    title: location.name || "Delivery Person",
                    icon: {
                        url: "http://maps.google.com/mapfiles/ms/icons/blue-dot.png",
                        scaledSize: new google.maps.Size(40, 40)
                    }
                });
            } else {
                deliveryManMarker.setPosition(position);
            }

            // Extend map bounds to include the new position
            bounds.extend(position);
            map.fitBounds(bounds);
        }

        // Update delivery path on the map
        function updateDeliveryPath(pathPoints) {
            if (!map || pathPoints.length < 2) return;

            const validPathPoints = pathPoints.filter(point =>
                point.latitude && point.longitude
            );

            if (validPathPoints.length < 2) return;

            const pathCoordinates = validPathPoints.map(point => ({
                lat: parseFloat(point.latitude),
                lng: parseFloat(point.longitude)
            }));

            // Create or update delivery path polyline
            if (!deliveryPathPolyline) {
                deliveryPathPolyline = new google.maps.Polyline({
                    path: pathCoordinates,
                    geodesic: true,
                    strokeColor: "#4361ee",
                    strokeOpacity: 0.8,
                    strokeWeight: 4
                });
                deliveryPathPolyline.setMap(map);
            } else {
                deliveryPathPolyline.setPath(pathCoordinates);
            }

            // Extend bounds to include path
            pathCoordinates.forEach(coord => bounds.extend(coord));
            map.fitBounds(bounds);
        }

        // Update delivery man information in the sidebar
        function updateDeliveryManInfo(deliveryMan) {
            // This would update the delivery man information in the UI
            // For now, we're just logging it
            console.log('Delivery man updated:', deliveryMan);
        }

        function initMap() {
            // Get tracking data from PHP
            const trackingData = @json($trackingData);

            // Initialize map
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 14,
                center: { lat: 23.8103, lng: 90.4125 } // Default to Dhaka, Bangladesh
            });

            // Set map center based on available locations
            bounds = new google.maps.LatLngBounds();
            let centerSet = false;

            // Add store marker if available
            if (trackingData.map_data && trackingData.map_data.store_location &&
                trackingData.map_data.store_location.latitude && trackingData.map_data.store_location.longitude) {
                const storeLocation = {
                    lat: parseFloat(trackingData.map_data.store_location.latitude),
                    lng: parseFloat(trackingData.map_data.store_location.longitude)
                };

                new google.maps.Marker({
                    position: storeLocation,
                    map: map,
                    title: trackingData.map_data.store_location.name || "Store Location",
                    icon: {
                        url: "http://maps.google.com/mapfiles/ms/icons/red-dot.png",
                        scaledSize: new google.maps.Size(40, 40)
                    }
                });

                bounds.extend(storeLocation);
                centerSet = true;
            }

            // Add customer marker if available
            if (trackingData.map_data && trackingData.map_data.customer_location &&
                trackingData.map_data.customer_location.latitude && trackingData.map_data.customer_location.longitude) {
                const customerLocation = {
                    lat: parseFloat(trackingData.map_data.customer_location.latitude),
                    lng: parseFloat(trackingData.map_data.customer_location.longitude)
                };

                new google.maps.Marker({
                    position: customerLocation,
                    map: map,
                    title: "Delivery Address",
                    icon: {
                        url: "http://maps.google.com/mapfiles/ms/icons/green-dot.png",
                        scaledSize: new google.maps.Size(40, 40)
                    }
                });

                bounds.extend(customerLocation);
                centerSet = true;
            }

            // Add delivery man marker if available
            if (trackingData.map_data && trackingData.map_data.delivery_man_location &&
                trackingData.map_data.delivery_man_location.latitude && trackingData.map_data.delivery_man_location.longitude) {
                const deliveryManLocation = {
                    lat: parseFloat(trackingData.map_data.delivery_man_location.latitude),
                    lng: parseFloat(trackingData.map_data.delivery_man_location.longitude)
                };

                deliveryManMarker = new google.maps.Marker({
                    position: deliveryManLocation,
                    map: map,
                    title: trackingData.map_data.delivery_man_location.name || "Delivery Person",
                    icon: {
                        url: "http://maps.google.com/mapfiles/ms/icons/blue-dot.png",
                        scaledSize: new google.maps.Size(40, 40)
                    }
                });

                bounds.extend(deliveryManLocation);
                centerSet = true;
            }

            // Draw delivery path if available
            if (trackingData.delivery_path && trackingData.delivery_path.length > 1) {
                const validPathPoints = trackingData.delivery_path.filter(point =>
                    point.latitude && point.longitude
                );

                if (validPathPoints.length > 1) {
                    const pathCoordinates = validPathPoints.map(point => ({
                        lat: parseFloat(point.latitude),
                        lng: parseFloat(point.longitude)
                    }));

                    deliveryPathPolyline = new google.maps.Polyline({
                        path: pathCoordinates,
                        geodesic: true,
                        strokeColor: "#4361ee",
                        strokeOpacity: 0.8,
                        strokeWeight: 4
                    });

                    deliveryPathPolyline.setMap(map);

                    // Extend bounds to include path
                    pathCoordinates.forEach(coord => bounds.extend(coord));
                }
            }

            // Fit map to show all markers
            if (centerSet) {
                map.fitBounds(bounds);
                // Minimum zoom level
                google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
                    if (map.getZoom() > 15) {
                        map.setZoom(15);
                    }
                });
            }
        }

        // Initialize progress bar when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateProgressBar();

            // Add refresh button functionality
            document.getElementById('refreshBtn').addEventListener('click', function() {
                const btn = this;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
                btn.disabled = true;

                // Simulate refresh delay
                setTimeout(function() {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    // In a real app, you would reload data here
                }, 1500);
            });

            // Initialize WebSocket connection for real-time tracking
            initWebSocket();
        });
    </script>
</body>
</html>

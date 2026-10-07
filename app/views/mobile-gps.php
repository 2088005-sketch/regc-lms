<?php
// GPS token is passed by controller — driver does NOT need to stay logged in after page loads
$gpsToken = $driverGpsToken ?? '';
$driverIdForPage = $selectedDriverId ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mobile Driver GPS | REGC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        :root {
            --navy: #0e1726;
            --navy-dark: #080d16;
            --gold: #f5c842;
        }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #0e1726;
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-bottom: 30px;
        }
        .header-bar {
            background: rgba(8, 13, 22, 0.95);
            padding: 14px 18px;
            border-bottom: 1px solid rgba(245, 200, 66, 0.2);
        }
        .radar-box {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 20px auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .radar-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px solid #22c55e;
            opacity: 0;
        }
        .broadcasting .radar-ring:nth-child(1) {
            animation: radarPulse 2.4s ease-out infinite;
        }
        .broadcasting .radar-ring:nth-child(2) {
            animation: radarPulse 2.4s ease-out 0.8s infinite;
        }
        .broadcasting .radar-ring:nth-child(3) {
            animation: radarPulse 2.4s ease-out 1.6s infinite;
        }
        @keyframes radarPulse {
            0% { transform: scale(0.4); opacity: 1; }
            100% { transform: scale(1.6); opacity: 0; }
        }
        .radar-center-btn {
            position: relative;
            z-index: 2;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: none;
            background: #22c55e;
            color: #fff;
            font-weight: 800;
            font-size: 13px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 25px rgba(34, 197, 94, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .radar-center-btn.active {
            background: #ef4444;
            box-shadow: 0 0 25px rgba(239, 68, 68, 0.6);
        }
        .stat-badge-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
        }
        .stat-badge-val {
            font-size: 18px;
            font-weight: 800;
            color: var(--gold);
        }
        .stat-badge-lbl {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        #mobile-map {
            width: 100%;
            height: 220px;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .retry-count { font-size: 11px; color: rgba(255,200,66,0.8); }
    </style>
</head>
<body>

<div class="header-bar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-truck text-warning fs-4"></i>
        <div>
            <strong class="d-block" style="font-size: 15px; line-height: 1.2;">REGC Driver GPS</strong>
            <small class="text-secondary" style="font-size: 11px;">Mobile Telematics Transmitter</small>
        </div>
    </div>
    <span class="badge bg-secondary" id="conn-pill" style="font-size: 11px;">Idle</span>
</div>

<div class="container py-3" style="max-width: 500px;">

    <!-- Driver Selector -->
    <div class="mb-3">
        <label class="form-label small text-secondary mb-1">Select Driver to Transmit As:</label>
        <select class="form-select bg-dark text-white border-secondary" id="driver-select">
            <?php foreach ($drivers as $d): ?>
                <option value="<?= (int) $d['id']; ?>" data-token="<?= htmlspecialchars($d['gps_token'] ?? '', ENT_QUOTES); ?>" <?= ($selectedDriverId == $d['id']) ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($d['full_name'], ENT_QUOTES); ?> (<?= htmlspecialchars($d['license_number'], ENT_QUOTES); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Pulsating Radar Button -->
    <div class="radar-box" id="radar-container">
        <div class="radar-ring"></div>
        <div class="radar-ring"></div>
        <div class="radar-ring"></div>
        <button type="button" class="radar-center-btn" id="toggle-gps-btn">
            <i class="bi bi-broadcast fs-3 mb-1"></i>
            <span id="btn-label">START GPS</span>
        </button>
    </div>

    <div class="text-center mb-3">
        <div class="small" id="status-text" style="color: rgba(255,255,255,0.7);">
            Tap "START GPS" to begin broadcasting live phone movement.
        </div>
        <div class="retry-count" id="retry-info"></div>
    </div>

    <!-- Real-time metrics grid -->
    <div class="row g-2 mb-3">
        <div class="col-6">
            <div class="stat-badge-card">
                <div class="stat-badge-val" id="disp-speed">0.0 km/h</div>
                <div class="stat-badge-lbl"><i class="bi bi-speedometer2 me-1"></i> Speed</div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-badge-card">
                <div class="stat-badge-val" id="disp-acc">&plusmn; 0 m</div>
                <div class="stat-badge-lbl"><i class="bi bi-bullseye me-1"></i> GPS Accuracy</div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-badge-card">
                <div class="stat-badge-val" id="disp-lat">---.-----</div>
                <div class="stat-badge-lbl">Latitude</div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-badge-card">
                <div class="stat-badge-val" id="disp-lng">---.-----</div>
                <div class="stat-badge-lbl">Longitude</div>
            </div>
        </div>
    </div>

    <!-- Live Mini Map -->
    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <small class="text-secondary fw-bold"><i class="bi bi-geo-alt-fill text-warning me-1"></i> Your Phone Location</small>
            <small class="text-secondary" id="updates-count">Updates Sent: 0</small>
        </div>
        <div id="mobile-map"></div>
    </div>

    <!-- Walk Simulation Mode (for testing indoors or without GPS signal) -->
    <div class="p-3 rounded-3 bg-dark border border-secondary text-center">
        <small class="d-block text-secondary mb-2">Testing indoors or without GPS signal?</small>
        <button type="button" class="btn btn-outline-warning btn-sm w-100" id="simulate-step-btn">
            <i class="bi bi-person-walking me-1"></i> Simulate Walking Step (+3m East)
        </button>
    </div>

    <div class="mt-3 p-2 rounded text-center small" style="background: rgba(255,255,255,0.03); color: rgba(255,255,255,0.5); font-size: 11px;">
        GPS updates are sent securely to the REGC fleet server using your driver token.
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
    // Base URL for GPS endpoint — injected by PHP
    var BASE_URL = <?= json_encode(rtrim(APP_URL, '/')) ?>;
    var GPS_ENDPOINT = BASE_URL + '/index.php?route=driver-gps-token';

    var isTracking = false;
    var watchId = null;
    var sendTimer = null;
    var updatesSent = 0;
    var retryCount = 0;
    var maxRetry = 5;

    var currentLat = 6.1164;
    var currentLng = 125.1716;
    var hasRealFix = false;

    var toggleBtn  = document.getElementById('toggle-gps-btn');
    var btnLabel   = document.getElementById('btn-label');
    var radarContainer = document.getElementById('radar-container');
    var statusText = document.getElementById('status-text');
    var connPill   = document.getElementById('conn-pill');
    var retryInfo  = document.getElementById('retry-info');

    var dispSpeed  = document.getElementById('disp-speed');
    var dispAcc    = document.getElementById('disp-acc');
    var dispLat    = document.getElementById('disp-lat');
    var dispLng    = document.getElementById('disp-lng');
    var updatesCountEl = document.getElementById('updates-count');
    var driverSelect   = document.getElementById('driver-select');
    var simStepBtn     = document.getElementById('simulate-step-btn');

    // Initialize Leaflet Map
    var mMap = L.map('mobile-map', { zoomControl: false }).setView([currentLat, currentLng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(mMap);

    var userMarker = L.circleMarker([currentLat, currentLng], {
        radius: 9, fillColor: '#22c55e', color: '#ffffff', weight: 3, fillOpacity: 1
    }).addTo(mMap);

    function getSelectedToken() {
        var sel = driverSelect.selectedOptions[0];
        return sel ? (sel.dataset.token || '') : '';
    }

    function transmitPosition(lat, lng) {
        var driverId = parseInt(driverSelect.value);
        var token = getSelectedToken();

        if (!token) {
            statusText.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> No driver token found. Please reload the page and sign in.</span>';
            return;
        }

        fetch(GPS_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                driver_id: driverId,
                lat: parseFloat(lat),
                lng: parseFloat(lng),
                gps_token: token
            })
        })
        .then(function(r) {
            return r.json().then(function(data) {
                if (!r.ok || !data.ok) throw new Error(data.error || 'GPS update rejected (status ' + r.status + ')');
                return data;
            });
        })
        .then(function() {
            updatesSent++;
            retryCount = 0;
            updatesCountEl.textContent = 'Updates Sent: ' + updatesSent;
            connPill.className = 'badge bg-success';
            connPill.textContent = 'Live (' + updatesSent + ')';
            retryInfo.textContent = '';
        })
        .catch(function(err) {
            retryCount++;
            connPill.className = 'badge bg-warning text-dark';
            connPill.textContent = 'Retry ' + retryCount + '/' + maxRetry;
            retryInfo.textContent = 'Last error: ' + (err.message || 'Network issue');
            if (retryCount >= maxRetry) {
                statusText.innerHTML = '<span class="text-danger"><i class="bi bi-wifi-off me-1"></i> Server unreachable after ' + maxRetry + ' retries. Check your internet connection.</span>';
            }
        });
    }

    function onGpsPosition(pos) {
        hasRealFix = true;
        currentLat = pos.coords.latitude;
        currentLng = pos.coords.longitude;

        var speedKmh = pos.coords.speed !== null && !isNaN(pos.coords.speed) ? (pos.coords.speed * 3.6).toFixed(1) : '0.0';
        var accM = pos.coords.accuracy ? Math.round(pos.coords.accuracy) : 5;

        dispLat.textContent = currentLat.toFixed(5);
        dispLng.textContent = currentLng.toFixed(5);
        dispSpeed.textContent = speedKmh + ' km/h';
        dispAcc.textContent = '± ' + accM + ' m';

        userMarker.setLatLng([currentLat, currentLng]);
        mMap.setView([currentLat, currentLng], 16);

        statusText.innerHTML = '<span class="text-success"><i class="bi bi-broadcast me-1"></i> Live GPS fix! Transmitting coordinates...</span>';
    }

    function onGpsError(err) {
        var msg = 'GPS Error: ' + err.message;
        if (err.code === 1) msg = 'Location permission denied. Please allow location access in your browser settings.';
        else if (err.code === 2) msg = 'GPS signal unavailable. Move outdoors or use simulate button below.';
        else if (err.code === 3) msg = 'GPS acquisition timed out. Retrying...';
        statusText.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> ' + msg + '</span>';
        connPill.className = 'badge bg-warning text-dark';
        connPill.textContent = 'GPS Error';
    }

    function startTracking() {
        isTracking = true;
        retryCount = 0;
        toggleBtn.classList.add('active');
        radarContainer.classList.add('broadcasting');
        btnLabel.textContent = 'STOP GPS';
        connPill.className = 'badge bg-success';
        connPill.textContent = 'Broadcasting';
        statusText.textContent = 'Acquiring high-accuracy GPS lock...';

        if (navigator.geolocation) {
            watchId = navigator.geolocation.watchPosition(onGpsPosition, onGpsError, {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: 15000
            });
        } else {
            statusText.textContent = 'Geolocation not supported. Use the Simulate Step button below.';
        }

        // Transmit every 10 seconds
        sendTimer = setInterval(function() {
            if (isTracking && hasRealFix) {
                transmitPosition(currentLat.toFixed(8), currentLng.toFixed(8));
            }
        }, 10000);

        // Send first ping immediately once we have a fix
        var firstPingDone = false;
        var pingWatcher = setInterval(function() {
            if (hasRealFix && !firstPingDone) {
                firstPingDone = true;
                transmitPosition(currentLat.toFixed(8), currentLng.toFixed(8));
                clearInterval(pingWatcher);
            }
        }, 1000);
    }

    function stopTracking() {
        isTracking = false;
        toggleBtn.classList.remove('active');
        radarContainer.classList.remove('broadcasting');
        btnLabel.textContent = 'START GPS';
        connPill.className = 'badge bg-secondary';
        connPill.textContent = 'Stopped';
        statusText.textContent = 'GPS transmission stopped.';
        retryInfo.textContent = '';

        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
        if (sendTimer !== null) {
            clearInterval(sendTimer);
            sendTimer = null;
        }
    }

    toggleBtn.addEventListener('click', function() {
        if (!isTracking) startTracking();
        else stopTracking();
    });

    // Simulate Step Button
    simStepBtn.addEventListener('click', function() {
        hasRealFix = true;
        currentLng += 0.000027; // ~3 meters East
        dispLat.textContent = currentLat.toFixed(5);
        dispLng.textContent = currentLng.toFixed(5);
        dispSpeed.textContent = '4.5 km/h';
        dispAcc.textContent = '± 3 m';

        userMarker.setLatLng([currentLat, currentLng]);
        mMap.setView([currentLat, currentLng], 17);

        transmitPosition(currentLat.toFixed(8), currentLng.toFixed(8));
        statusText.innerHTML = '<span class="text-warning"><i class="bi bi-person-walking me-1"></i> Simulated step transmitted!</span>';
    });
})();
</script>
</body>
</html>

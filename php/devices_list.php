<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/db.php';

require_auth_json();

if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    json_error('Forbidden', 403);
}

try {
    $pdo  = Database::get();
    $rows = $pdo->query(
        'SELECT Id, BrandName, ModelName, ModelOs, BatteryLevel, ConnectType,
                Latitude, Longitude, LastSeen
         FROM Devices
         ORDER BY Id DESC'
    )->fetchAll();

    $devices = array_map(static function (array $r): array {
        $online = $r['LastSeen'] && (time() - strtotime($r['LastSeen'])) < 300;
        return [
            'id'      => (int)   $r['Id'],
            'brand'   =>         $r['BrandName'] ?? '',
            'model'   =>         $r['ModelName'] ?? '',
            'os'      =>         $r['ModelOs'] ?? '',
            'battery' => (int)   ($r['BatteryLevel'] ?? 0),
            'connect' =>         $r['ConnectType'] ?? '',
            'lat'     => (float) ($r['Latitude'] ?? 0),
            'lon'     => (float) ($r['Longitude'] ?? 0),
            'online'  =>         $online,
        ];
    }, $rows);

    json_response(['success' => true, 'devices' => $devices]);
} catch (PDOException $e) {
    error_log('devices_list.php: ' . $e->getMessage());
    json_error('Database error', 500);
}

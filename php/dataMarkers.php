<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/db.php';

require_auth_json();

header('Cache-Control: no-store');

try {
    $pdo  = Database::get();
    $stmt = $pdo->query(
        'SELECT Id, BrandName, ModelName, Latitude, Longitude, BatteryLevel, LastSeen
         FROM Devices
         WHERE Latitude IS NOT NULL AND Longitude IS NOT NULL
           AND Latitude != 0 AND Longitude != 0
         ORDER BY Id DESC'
    );
    $rows = $stmt->fetchAll();

    $markers = array_map(static function (array $r): array {
        return [
            'id'       => (int)   $r['Id'],
            'brand'    =>         $r['BrandName'],
            'model'    =>         $r['ModelName'],
            'lat'      => (float) $r['Latitude'],
            'lon'      => (float) $r['Longitude'],
            'battery'  => (int)   $r['BatteryLevel'],
            'lastSeen' =>         $r['LastSeen'],
        ];
    }, $rows);

    json_response(['success' => true, 'markers' => $markers]);
} catch (PDOException $e) {
    error_log('dataMarkers.php: ' . $e->getMessage());
    json_error('Database error', 500);
}

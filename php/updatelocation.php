<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

$id  = filter_input(INPUT_POST, 'GPSid', FILTER_VALIDATE_INT);
$lat = filter_input(INPUT_POST, 'latitude',  FILTER_VALIDATE_FLOAT);
$lon = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);

if (!$id || $id < 1 || $lat === false || $lat === null || $lon === false || $lon === null) {
    http_response_code(400);
    echo '0';
    exit;
}

try {
    $pdo  = Database::get();
    $stmt = $pdo->prepare(
        'UPDATE Devices SET Latitude = ?, Longitude = ?, LastSeen = NOW() WHERE Id = ?'
    );
    $stmt->execute([$lat, $lon, $id]);
    echo '1';
} catch (PDOException $e) {
    error_log('updatelocation.php: ' . $e->getMessage());
    http_response_code(500);
    echo '0';
}

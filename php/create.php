<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

// Validate required fields
$required = ['brandName', 'modelName', 'modelOs', 'batteryLevel', 'connectType', 'boardHardware', 'latitude', 'longitude'];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || $_POST[$field] === '') {
        echo '0';
        exit;
    }
}

$brandName    = sanitize_post('brandName', 50);
$modelName    = sanitize_post('modelName', 50);
$modelOs      = sanitize_post('modelOs', 30);
$battery      = max(0, min(100, (int) $_POST['batteryLevel']));
$connectType  = sanitize_post('connectType', 30);
$boardHardware = sanitize_post('boardHardware', 50);
$latitude     = validate_coord($_POST['latitude']);
$longitude    = validate_coord($_POST['longitude']);

if ($latitude === null || $longitude === null) {
    echo '0';
    exit;
}

try {
    $pdo  = Database::get();
    $stmt = $pdo->prepare(
        'INSERT INTO Devices (BrandName, ModelName, ModelOs, BatteryLevel, ConnectType, BoardHardware, Latitude, Longitude, LastSeen)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$brandName, $modelName, $modelOs, $battery, $connectType, $boardHardware, $latitude, $longitude]);
    echo $pdo->lastInsertId();
} catch (PDOException $e) {
    error_log('create.php: ' . $e->getMessage());
    echo '0';
}

function sanitize_post(string $key, int $maxLen): string
{
    return mb_substr(trim(strip_tags($_POST[$key])), 0, $maxLen);
}

function validate_coord(string $value): ?float
{
    $f = filter_var($value, FILTER_VALIDATE_FLOAT);
    return ($f === false) ? null : $f;
}

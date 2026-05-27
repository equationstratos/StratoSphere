<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function fetch_devices(): array|string
{
    try {
        $pdo  = Database::get();
        $stmt = $pdo->query(
            'SELECT Id, BrandName, ModelName, ModelOs, BatteryLevel, ConnectType,
                    BoardHardware, Latitude, Longitude, Command, LastSeen
             FROM Devices
             ORDER BY Id DESC'
        );
        $rows = $stmt->fetchAll();
        return $rows ?: 'No devices connected';
    } catch (PDOException $e) {
        error_log('developersTable.php: ' . $e->getMessage());
        return 'Database error';
    }
}

$fetchData = fetch_devices();

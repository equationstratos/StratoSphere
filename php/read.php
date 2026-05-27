<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    echo 'NONE';
    exit;
}

try {
    $pdo = Database::get();

    // Update last-seen timestamp
    $pdo->prepare('UPDATE Devices SET LastSeen = NOW() WHERE Id = ?')->execute([$id]);

    $stmt = $pdo->prepare('SELECT Command FROM Devices WHERE Id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row) {
        $cmd = trim($row['Command'] ?? '');

        if ($cmd !== '' && $cmd !== 'NONE') {
            // ══════════════════════════════════════════════════════════
            //  [FIX CRITIQUE] Vider la commande après lecture
            //  Sans ça, l'app Android re-exécute la même commande
            //  toutes les 5 secondes en boucle infinie
            // ══════════════════════════════════════════════════════════
            $pdo->prepare('UPDATE Devices SET Command = ? WHERE Id = ?')
                ->execute(['NONE', $id]);

            echo $cmd;
        } else {
            echo 'NONE';
        }
    } else {
        echo 'NONE';
    }
} catch (PDOException $e) {
    error_log('read.php: ' . $e->getMessage());
    echo 'NONE';
}

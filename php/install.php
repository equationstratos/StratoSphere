<?php
declare(strict_types=1);
/**
 * STRATOSPHERE - Installation script (non-interactive)
 * Lit la config depuis le .env existant (créé par install.sh ou install-interactive.php)
 *
 * Usage CLI : php install.php
 * Usage HTTP: php install.php?token=<INSTALL_TOKEN>
 *
 * Pour une installation guidée avec prompts, utilisez plutôt:
 *   bash install.sh
 *   php php/install-interactive.php
 */

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    $token = getenv('INSTALL_TOKEN') ?: null;
    if (!$token || ($_GET['token'] ?? '') !== $token) {
        http_response_code(403);
        exit('Access denied. Set INSTALL_TOKEN env variable and pass ?token=<value>');
    }
}

require_once __DIR__ . '/config.php';

// ── Vérifications préalables ──────────────────────────────────────────────
$missingExts = [];
foreach (['pdo_mysql', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) {
        $missingExts[] = $ext;
    }
}
if ($missingExts) {
    exit('[ERR] Extensions PHP manquantes: ' . implode(', ', $missingExts) . "\n");
}

$schemaPath = dirname(__DIR__) . '/sql/schema.sql';
if (!file_exists($schemaPath)) {
    exit("[ERR] Fichier schéma introuvable: $schemaPath\n");
}

// ── Connexion ─────────────────────────────────────────────────────────────
$dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, getenv('DB_PORT') ?: '3306');
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    exit('[ERR] Connexion MySQL impossible: ' . $e->getMessage() . "\n");
}

// ── Exécution d'un statement SQL ────────────────────────────────────────
function run(string $sql, PDO $pdo): bool
{
    try {
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo '[ERR] ' . rtrim(substr($sql, 0, 80)) . '…' . ' → ' . $e->getMessage() . "\n";
        return false;
    }
}

// ── Création BDD et import schéma ─────────────────────────────────────────
echo "[...] Création de la base de données `" . DB_NAME . "`\n";
run("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci", $pdo);
$pdo->exec("USE `" . DB_NAME . "`");

echo "[...] Import du schéma\n";
$schema = file_get_contents($schemaPath);
$ok = true;
foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
    if (!run($stmt, $pdo)) {
        $ok = false;
    }
}

// ── Résumé ────────────────────────────────────────────────────────────────
if ($ok) {
    echo "\n[OK] Installation terminée.\n";
    echo "     Base: " . DB_NAME . "@" . DB_HOST . "\n";
    echo "     Supprimez ce fichier après utilisation.\n\n";
} else {
    echo "\n[WARN] Installation partielle. Vérifiez les erreurs ci-dessus.\n\n";
}

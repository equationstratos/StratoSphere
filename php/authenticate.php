<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/db.php';

session_init();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: ../index.html?error=empty');
    exit;
}

$ip = get_client_ip();
$pdo = Database::get();

// Rate limiting: max LOGIN_MAX_ATTEMPTS per IP in LOGIN_LOCKOUT_SECONDS
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM LoginAttempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)'
);
$stmt->execute([$ip, LOGIN_LOCKOUT_SECONDS]);
$attempts = (int) $stmt->fetchColumn();

if ($attempts >= LOGIN_MAX_ATTEMPTS) {
    header('Location: ../index.html?error=locked');
    exit;
}

// Look up user
$stmt = $pdo->prepare('SELECT Id, Password FROM Accounts WHERE Username = ? LIMIT 1');
$stmt->execute([$username]);
$account = $stmt->fetch();

if ($account && password_verify($password, $account['Password'])) {
    // Clean up old attempts for this IP
    $pdo->prepare('DELETE FROM LoginAttempts WHERE ip = ?')->execute([$ip]);

    session_regenerate_id(true);
    $_SESSION['loggedin'] = true;
    $_SESSION['user_id']  = $account['Id'];
    $_SESSION['name']     = $username;

    header('Location: index.php');
    exit;
}

// Record failed attempt
$pdo->prepare('INSERT INTO LoginAttempts (ip) VALUES (?)')->execute([$ip]);
header('Location: ../index.html?error=invalid');
exit;

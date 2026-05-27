<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_middleware.php';
require_auth();
header('Location: index.php');
exit;

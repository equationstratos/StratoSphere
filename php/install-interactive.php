<?php
declare(strict_types=1);

/**
 * STRATOSPHERE - Installation Interactive
 * Fichier: php/install-interactive.php
 *
 * Installeur multi-mode:
 * - Mode CLI: php install-interactive.php (prompts via stdin)
 * - Mode HTTP: visite avec token (?token=...) pour afficher formulaire web
 * - Supporté par: bash install.sh avec variables d'env
 */

namespace StratosphereInstall;

define('INSTALL_DIR', dirname(__DIR__));
define('IS_CLI', php_sapi_name() === 'cli');

// ────────────────────────────────────────────────────────────────────────
// Classe Installation
// ────────────────────────────────────────────────────────────────────────

class Installer
{
    private array $config = [];
    private array $errors = [];
    private \PDO $pdo;

    public function __construct()
    {
        $this->config = [
            'DB_HOST'    => getenv('DB_HOST')    ?: 'localhost',
            'DB_PORT'    => getenv('DB_PORT')    ?: '3306',
            'DB_NAME'    => getenv('DB_NAME')    ?: '',
            'DB_USER'    => getenv('DB_USER')    ?: 'stratos_app',
            'DB_PASS'    => getenv('DB_PASS')    ?: '',
            'SERVER_URL' => getenv('SERVER_URL') ?: 'http://localhost/stratosphere',
            'APP_ENV'    => getenv('APP_ENV')    ?: 'development',
            'ADMIN_PASS' => getenv('INSTALL_ADMIN_PASS') ?: '',
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    // Affichage (CLI & HTML)
    // ────────────────────────────────────────────────────────────────────

    public function say(string $msg, string $type = 'info'): void
    {
        if (IS_CLI) {
            echo $this->formatCLI($msg, $type);
        }
    }

    private function formatCLI(string $msg, string $type): string
    {
        $colors = [
            'info'    => "\033[0;34m",    // Blue
            'success' => "\033[0;32m",    // Green
            'error'   => "\033[0;31m",    // Red
            'warn'    => "\033[1;33m",    // Yellow
            'reset'   => "\033[0m",
        ];

        $prefix = match($type) {
            'success' => '✓',
            'error'   => '✗',
            'warn'    => '⚠',
            default   => 'ℹ',
        };

        $color = $colors[$type] ?? $colors['info'];
        return "{$color}{$prefix}\033[0m {$msg}\n";
    }

    // ────────────────────────────────────────────────────────────────────
    // Validation
    // ────────────────────────────────────────────────────────────────────

    public function checkPHPExtensions(): bool
    {
        $this->say('Vérification des extensions PHP...');

        $required = ['pdo_mysql', 'mbstring'];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $this->errors[] = "Extension PHP manquante: $ext";
                return false;
            }
        }

        $this->say("Extensions PHP OK", 'success');
        return true;
    }

    public function testMySQLConnection(): bool
    {
        $this->say('Test de connexion MySQL...');

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;charset=utf8mb4',
                $this->config['DB_HOST'],
                $this->config['DB_PORT']
            );

            $this->pdo = new \PDO($dsn, $this->config['DB_USER'], $this->config['DB_PASS'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $this->say("Connexion MySQL réussie", 'success');
            return true;
        } catch (\PDOException $e) {
            $this->errors[] = "Erreur MySQL: " . $e->getMessage();
            return false;
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // Création de la base de données et tables
    // ────────────────────────────────────────────────────────────────────

    public function createDatabase(): bool
    {
        $this->say("Création de la base de données '{$this->config['DB_NAME']}'...");

        try {
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->config['DB_NAME']}`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $this->say("Base de données créée", 'success');
            return true;
        } catch (\PDOException $e) {
            $this->errors[] = "Erreur création BDD: " . $e->getMessage();
            return false;
        }
    }

    public function importSchema(): bool
    {
        $this->say("Importation du schéma...");

        $schemaFile = INSTALL_DIR . '/sql/schema.sql';
        if (!file_exists($schemaFile)) {
            $this->errors[] = "Fichier schema.sql non trouvé: $schemaFile";
            return false;
        }

        try {
            $this->pdo->exec("USE `{$this->config['DB_NAME']}`");

            $sql = file_get_contents($schemaFile);
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->pdo->exec($statement);
                }
            }

            $this->say("Schéma importé avec succès", 'success');
            return true;
        } catch (\PDOException $e) {
            $this->errors[] = "Erreur import schéma: " . $e->getMessage();
            return false;
        }
    }

    public function setupAdminAccount(): bool
    {
        $this->say("Configuration du compte administrateur...");

        if (empty($this->config['ADMIN_PASS'])) {
            // Générer un mot de passe aléatoire
            $this->config['ADMIN_PASS'] = bin2hex(random_bytes(8));
            $this->say("Mot de passe admin généré: {$this->config['ADMIN_PASS']}", 'warn');
        }

        try {
            $hash = password_hash($this->config['ADMIN_PASS'], PASSWORD_BCRYPT);

            $stmt = $this->pdo->prepare(
                'UPDATE Accounts SET Password = ? WHERE Username = ?'
            );
            $stmt->execute([$hash, 'admin']);

            $this->say("Compte admin configuré", 'success');
            return true;
        } catch (\PDOException $e) {
            $this->errors[] = "Erreur configuration admin: " . $e->getMessage();
            return false;
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // Génération .env
    // ────────────────────────────────────────────────────────────────────

    public function writeEnvFile(): bool
    {
        $this->say("Écriture du fichier .env...");

        $envFile = INSTALL_DIR . '/.env';
        if (file_exists($envFile) && (getenv('INSTALL_SKIP_OVERWRITE') ?? '') !== '1') {
            $this->say(".env existe déjà, suppression", 'warn');
        }

        $content = <<<ENV
# STRATOSPHERE Configuration
# Généré par install-interactive.php à {$this->timestamp()}

# Base de données
DB_HOST={$this->config['DB_HOST']}
DB_PORT={$this->config['DB_PORT']}
DB_NAME={$this->config['DB_NAME']}
DB_USER={$this->config['DB_USER']}
DB_PASS={$this->config['DB_PASS']}

# Application
SERVER_URL={$this->config['SERVER_URL']}
APP_ENV={$this->config['APP_ENV']}

# Session
SESSION_LIFETIME=3600

# Rate limiting
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_SECONDS=900
ENV;

        if (file_put_contents($envFile, $content, LOCK_EX) === false) {
            $this->errors[] = "Impossible d'écrire dans $envFile";
            return false;
        }

        chmod($envFile, 0600);
        $this->say("Fichier .env écrit (permissions 600)", 'success');
        return true;
    }

    // ────────────────────────────────────────────────────────────────────
    // Mode CLI - Prompts interactifs
    // ────────────────────────────────────────────────────────────────────

    public function promptCLI(): void
    {
        if (!IS_CLI) {
            return;
        }

        // Si variables d'env définies (par install.sh), passer les prompts
        if ((getenv('INSTALL_SKIP_PROMPTS') ?? '') === '1') {
            return;
        }

        echo "\n🔧 Configuration interactive de StratoSphere\n\n";

        // BDD
        $this->config['DB_HOST'] = $this->readInput('Hôte MySQL', $this->config['DB_HOST']);
        $this->config['DB_PORT'] = $this->readInput('Port MySQL', $this->config['DB_PORT']);
        $this->config['DB_USER'] = $this->readInput('Utilisateur MySQL', $this->config['DB_USER']);

        // Génerer DB name si absent
        if (empty($this->config['DB_NAME'])) {
            $this->config['DB_NAME'] = 'stratosphere_' . bin2hex(random_bytes(3));
        }
        $this->config['DB_NAME'] = $this->readInput('Nom BDD', $this->config['DB_NAME']);

        // Password (masqué)
        $this->config['DB_PASS'] = $this->readInputHidden('Mot de passe MySQL');

        // URL serveur
        $this->config['SERVER_URL'] = $this->readInput('URL serveur', $this->config['SERVER_URL']);

        // Admin pass (masqué)
        $this->config['ADMIN_PASS'] = $this->readInputHidden('Mot de passe admin');
    }

    private function readInput(string $prompt, string $default = ''): string
    {
        $suffix = $default ? " [{$default}]" : '';
        echo "\033[0;34m> $prompt$suffix\033[0m: ";
        $input = trim(fgets(STDIN));
        return $input ?: $default;
    }

    private function readInputHidden(string $prompt): string
    {
        echo "\033[0;34m> $prompt\033[0m: ";
        system('stty -echo');
        $input = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $input;
    }

    // ────────────────────────────────────────────────────────────────────
    // Mode HTTP - Formulaire web
    // ────────────────────────────────────────────────────────────────────

    public function renderHTML(): void
    {
        if (IS_CLI) {
            return;
        }

        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STRATOSPHERE - Installation</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0d1117; color: #c9d1d9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 40px; max-width: 500px; width: 100%; }
        h1 { font-size: 28px; margin-bottom: 8px; color: #fff; }
        .subtitle { color: #6e7681; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; color: #6e7681; margin-bottom: 6px; font-weight: 600; }
        input { width: 100%; padding: 10px 12px; background: #0d1117; border: 1px solid #30363d; border-radius: 8px; color: #c9d1d9; font-size: 14px; }
        input:focus { outline: none; border-color: #3498db; }
        button { width: 100%; padding: 12px; background: #3498db; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 20px; }
        button:hover { opacity: .9; }
        .info { background: rgba(52, 152, 219, .1); border-left: 3px solid #3498db; padding: 12px; border-radius: 0 6px 6px 0; font-size: 13px; margin-bottom: 20px; }
        .error { background: rgba(231, 76, 60, .1); border-left: 3px solid #e74c3c; padding: 12px; border-radius: 0 6px 6px 0; color: #f85149; font-size: 13px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="card">
    <h1>STRATOSPHERE</h1>
    <p class="subtitle">Configuration initiale</p>

    <div class="info">
        ⚠️ Cette page d'installation doit être supprimée après utilisation pour des raisons de sécurité.
    </div>

    <form method="post">
        <div class="form-group">
            <label>Hôte MySQL</label>
            <input type="text" name="db_host" value="localhost" required>
        </div>

        <div class="form-group">
            <label>Port MySQL</label>
            <input type="text" name="db_port" value="3306" required>
        </div>

        <div class="form-group">
            <label>Utilisateur MySQL</label>
            <input type="text" name="db_user" value="stratos_app" required>
        </div>

        <div class="form-group">
            <label>Mot de passe MySQL</label>
            <input type="password" name="db_pass" required>
        </div>

        <div class="form-group">
            <label>Nom de la base de données</label>
            <input type="text" name="db_name" value="stratosphere_demo" required>
        </div>

        <div class="form-group">
            <label>URL du serveur</label>
            <input type="url" name="server_url" value="http://localhost/stratosphere" required>
        </div>

        <div class="form-group">
            <label>Mot de passe administrateur</label>
            <input type="password" name="admin_pass" required>
        </div>

        <button type="submit">Installer</button>
    </form>
</div>
</body>
</html>
        <?php
        exit;
    }

    // ────────────────────────────────────────────────────────────────────
    // Exécution globale
    // ────────────────────────────────────────────────────────────────────

    public function run(): int
    {
        if (!IS_CLI) {
            // Mode HTTP
            if (empty($_GET['token']) || $_GET['token'] !== (getenv('INSTALL_TOKEN') ?: 'admin')) {
                http_response_code(403);
                $this->renderHTML();
                return 1;
            }

            // Traiter POST
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->config['DB_HOST']   = $_POST['db_host'] ?? $this->config['DB_HOST'];
                $this->config['DB_PORT']   = $_POST['db_port'] ?? $this->config['DB_PORT'];
                $this->config['DB_USER']   = $_POST['db_user'] ?? $this->config['DB_USER'];
                $this->config['DB_PASS']   = $_POST['db_pass'] ?? $this->config['DB_PASS'];
                $this->config['DB_NAME']   = $_POST['db_name'] ?? $this->config['DB_NAME'];
                $this->config['SERVER_URL'] = $_POST['server_url'] ?? $this->config['SERVER_URL'];
                $this->config['ADMIN_PASS'] = $_POST['admin_pass'] ?? '';
            }
        } else {
            // Mode CLI
            $this->promptCLI();
        }

        // Étapes installation
        $steps = [
            'checkPHPExtensions' => 'Vérification extensions PHP',
            'testMySQLConnection' => 'Connexion MySQL',
            'createDatabase' => 'Création BDD',
            'importSchema' => 'Import schéma',
            'setupAdminAccount' => 'Configuration admin',
            'writeEnvFile' => 'Écriture .env',
        ];

        foreach ($steps as $method => $label) {
            $this->say($label . '...');
            if (!$this->$method()) {
                $this->say("Erreur: $label échouée", 'error');
                return 1;
            }
        }

        $this->showSummary();
        return 0;
    }

    private function showSummary(): void
    {
        $this->say("\n✓ Installation terminée avec succès!", 'success');

        if (IS_CLI) {
            echo "\n📝 Configuration:\n";
            printf("  - BDD: %s@%s:%s\n", $this->config['DB_USER'], $this->config['DB_HOST'], $this->config['DB_PORT']);
            printf("  - Base: %s\n", $this->config['DB_NAME']);
            printf("  - URL: %s\n", $this->config['SERVER_URL']);
            printf("  - Admin: admin (mot de passe que vous avez choisi)\n");
            echo "\n➜  Accéder au tableau de bord:\n";
            echo "   {$this->config['SERVER_URL']}\n\n";
        }
    }

    private function timestamp(): string
    {
        return date('Y-m-d H:i:s');
    }
}

// ────────────────────────────────────────────────────────────────────────
// Point d'entrée
// ────────────────────────────────────────────────────────────────────────

$installer = new Installer();
exit($installer->run());

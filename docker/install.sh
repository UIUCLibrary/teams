#!/usr/bin/env bash
set -euo pipefail

OMEKA_PATH="/var/www/omeka-s"
cd "$OMEKA_PATH"

export OMEKA_DB_HOST="${OMEKA_DB_HOST:-db}"
export OMEKA_DB_PORT="${OMEKA_DB_PORT:-3306}"
export OMEKA_DB_NAME="${OMEKA_DB_NAME:-omeka}"
export OMEKA_DB_USER="${OMEKA_DB_USER:-omeka}"
export OMEKA_DB_PASSWORD="${OMEKA_DB_PASSWORD:-omeka}"
export OMEKA_SERVER_URL="${OMEKA_SERVER_URL:-http://localhost:8080}"
export TEAMS_ADMIN_NAME="${TEAMS_ADMIN_NAME:-Admin}"
export TEAMS_ADMIN_EMAIL="${TEAMS_ADMIN_EMAIL:-admin@example.com}"
export TEAMS_ADMIN_PASSWORD="${TEAMS_ADMIN_PASSWORD:-TeamsTestPass123!}"

cat > config/local.config.php <<'PHP'
<?php
return [
    'logger' => [
        'log' => false,
        'priority' => \Laminas\Log\Logger::NOTICE,
    ],
    'http_client' => [
        'sslcapath' => null,
        'sslcafile' => null,
    ],
    'cli' => [
        'phpcli_path' => null,
    ],
    'thumbnails' => [
        'types' => [
            'large' => ['constraint' => 800],
            'medium' => ['constraint' => 200],
            'square' => ['constraint' => 200],
        ],
    ],
    'translator' => [
        'locale' => 'en_US',
    ],
    'service_manager' => [
        'aliases' => [
            'Omeka\\File\\Store' => 'Omeka\\File\\Store\\Local',
            'Omeka\\File\\Thumbnailer' => 'Omeka\\File\\Thumbnailer\\Gd',
        ],
    ],
];
PHP

cat > config/database.ini <<EOF_DB
user     = "${OMEKA_DB_USER}"
password = "${OMEKA_DB_PASSWORD}"
dbname   = "${OMEKA_DB_NAME}"
host     = "${OMEKA_DB_HOST}"
port     = "${OMEKA_DB_PORT}"
EOF_DB

php -d xdebug.mode=off <<'PHP'
<?php
declare(strict_types=1);

$host = getenv('OMEKA_DB_HOST') ?: 'db';
$port = (int) (getenv('OMEKA_DB_PORT') ?: 3306);
$db = getenv('OMEKA_DB_NAME') ?: 'omeka';
$user = getenv('OMEKA_DB_USER') ?: 'omeka';
$password = getenv('OMEKA_DB_PASSWORD') ?: 'omeka';

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
$attempts = 60;
while ($attempts-- > 0) {
    try {
        new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        fwrite(STDOUT, "MySQL is ready.\n");
        exit(0);
    } catch (Throwable $e) {
        if ($attempts === 0) {
            fwrite(STDERR, "Timed out waiting for MySQL: {$e->getMessage()}\n");
            exit(1);
        }
        sleep(2);
    }
}
PHP

php -d xdebug.mode=off <<'PHP'
<?php
declare(strict_types=1);

use Laminas\Uri\Http;
use Omeka\Mvc\Application;

require 'bootstrap.php';

$application = Application::init(require OMEKA_PATH . '/application/config/application.config.php');
$services = $application->getServiceManager();
$services->get('Router')->setRequestUri(new Http(getenv('OMEKA_SERVER_URL') ?: 'http://localhost:8080'));

$status = $services->get('Omeka\Status');
if ($status->isInstalled()) {
    fwrite(STDOUT, "Omeka S is already installed.\n");
    exit(0);
}

$installer = $services->get('Omeka\Installer');
$installer->registerVars('Omeka\\Installation\\Task\\CreateFirstUserTask', [
    'name' => getenv('TEAMS_ADMIN_NAME') ?: 'Admin',
    'email' => getenv('TEAMS_ADMIN_EMAIL') ?: 'admin@example.com',
    'password-confirm' => [
        'password' => getenv('TEAMS_ADMIN_PASSWORD') ?: 'TeamsTestPass123!',
    ],
]);
$installer->registerVars('Omeka\\Installation\\Task\\AddDefaultSettingsTask', [
    'administrator_email' => getenv('TEAMS_ADMIN_EMAIL') ?: 'admin@example.com',
    'installation_title' => 'Teams Integration Test',
    'time_zone' => 'UTC',
    'locale' => 'en_US',
]);

if (!$installer->install()) {
    foreach ($installer->getErrors() as $error) {
        fwrite(STDERR, (string) $error . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "Omeka S installation complete.\n");
PHP

php -d xdebug.mode=off <<'PHP'
<?php
declare(strict_types=1);

use Laminas\Uri\Http;
use Omeka\Entity\Module as ModuleEntity;
use Omeka\Mvc\Application;

require 'bootstrap.php';
require_once OMEKA_PATH . '/modules/Teams/Module.php';

$application = Application::init(require OMEKA_PATH . '/application/config/application.config.php');
$services = $application->getServiceManager();
$services->get('Router')->setRequestUri(new Http(getenv('OMEKA_SERVER_URL') ?: 'http://localhost:8080'));

$entityManager = $services->get('Omeka\EntityManager');
$connection = $services->get('Omeka\Connection');
$moduleRepository = $entityManager->getRepository(ModuleEntity::class);
$moduleEntity = $moduleRepository->findOneBy(['id' => 'Teams']);

$teamTableExists = (bool) $connection->fetchOne("SHOW TABLES LIKE 'team'");

if (!$moduleEntity) {
    if (!$teamTableExists) {
        $module = new Teams\Module();
        $module->install($services);
        fwrite(STDOUT, "Teams module schema installed.\n");
    }

    $ini = parse_ini_file(OMEKA_PATH . '/modules/Teams/config/module.ini');
    $moduleEntity = new ModuleEntity();
    $moduleEntity->setId('Teams');
    $moduleEntity->setVersion($ini['version'] ?? 'unknown');
    $moduleEntity->setIsActive(true);
    $entityManager->persist($moduleEntity);
    $entityManager->flush();
    fwrite(STDOUT, "Teams module activated.\n");
    exit(0);
}

if (!$moduleEntity->isActive()) {
    $moduleEntity->setIsActive(true);
    $entityManager->flush();
    fwrite(STDOUT, "Teams module re-activated.\n");
    exit(0);
}

fwrite(STDOUT, "Teams module is already active.\n");
PHP

php -d xdebug.mode=off <<'PHP'
<?php
declare(strict_types=1);

use Omeka\Mvc\Application;
use Omeka\Module\Manager as ModuleManager;

require 'bootstrap.php';

$application = Application::init(require OMEKA_PATH . '/application/config/application.config.php');
$services = $application->getServiceManager();
$module = $services->get('Omeka\ModuleManager')->getModule('Teams');

if (!$module || $module->getState() !== ModuleManager::STATE_ACTIVE) {
    fwrite(STDERR, "Teams module is not active after installation.\n");
    exit(1);
}

fwrite(STDOUT, "Verified Teams module is active.\n");
PHP

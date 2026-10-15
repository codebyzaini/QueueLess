<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Database Connection Manager (PDO)
 *
 * Securely loads credentials from outside the web document root
 * (/etc/queueless/database.php) or from a local developer override
 * (config/database.local.php).
 *
 * Adheres to zero-credential exposure principles:
 * - Credentials are never committed to version control.
 * - Detailed SQL errors are logged to the system error log, never shown to visitors.
 * - Reusable singleton PDO instance avoids connection exhaustion.
 */

declare(strict_types=1);

/**
 * Global holder for database connection error diagnostics (sanitized for display).
 */
$GLOBALS['queueless_db_error'] = null;

/**
 * Retrieve the database configuration array from a protected private file.
 *
 * @return array{host: string, port: int|string, database: string, username: string, password: string, charset?: string}|null
 */
function load_database_config(): ?array {
    // Primary secure production path on Ubuntu (outside Apache document root)
    $primaryPath = '/etc/queueless/database.php';

    // Secondary local development override (ignored by Git)
    $localPath = __DIR__ . '/database.local.php';

    $chosenPath = null;
    if (file_exists($primaryPath) && is_readable($primaryPath)) {
        $chosenPath = $primaryPath;
    } elseif (file_exists($localPath) && is_readable($localPath)) {
        $chosenPath = $localPath;
    }

    if ($chosenPath === null) {
        $msg = "Database configuration file not found or unreadable. Expected '/etc/queueless/database.php' or 'config/database.local.php'.";
        error_log("[QueueLess] Configuration Error: " . $msg);
        $GLOBALS['queueless_db_error'] = "Database configuration is missing. Please review the setup guide in docs/SETUP_UBUNTU.md.";
        return null;
    }

    $config = require $chosenPath;

    if (!is_array($config)) {
        error_log("[QueueLess] Configuration Error: Config file at '{$chosenPath}' did not return an array.");
        $GLOBALS['queueless_db_error'] = "Invalid database configuration format.";
        return null;
    }

    // Validate presence of required keys
    $requiredKeys = ['host', 'database', 'username', 'password'];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $config)) {
            error_log("[QueueLess] Configuration Error: Missing required key '{$key}' in database config.");
            $GLOBALS['queueless_db_error'] = "Incomplete database configuration.";
            return null;
        }
    }

    return $config;
}

/**
 * Obtain a shared PDO connection instance.
 *
 * @return PDO|null Returns active PDO instance, or null if connection cannot be established.
 */
function get_db_connection(): ?PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $config = load_database_config();
    if ($config === null) {
        return null;
    }

    $host    = (string)$config['host'];
    $port    = isset($config['port']) ? (int)$config['port'] : 3306;
    $dbname  = (string)$config['database'];
    $user    = (string)$config['username'];
    $pass    = (string)$config['password'];
    $charset = isset($config['charset']) ? (string)$config['charset'] : 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $GLOBALS['queueless_db_error'] = null;
        return $pdo;
    } catch (PDOException $e) {
        // Log technical details safely to Apache error log
        error_log("[QueueLess] Database Connection Failure: " . $e->getMessage());

        // Set safe, user-friendly diagnostic message without exposing credentials or DSN
        $GLOBALS['queueless_db_error'] = "Unable to connect to the database server. Please verify MySQL service status and credentials in /etc/queueless/database.php.";
        return null;
    }
}

/**
 * Retrieve the last connection error message (if any), sanitized for user display.
 *
 * @return string|null
 */
function get_db_connection_error(): ?string {
    return $GLOBALS['queueless_db_error'] ?? null;
}

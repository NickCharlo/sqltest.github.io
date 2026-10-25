<?php
/**
 * TaskFlow — Database Configuration
 *
 * Update the constants below to match your local MySQL setup.
 * Typically works out-of-the-box with XAMPP / MAMP defaults.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'taskflow');
define('DB_USER', 'root');
define('DB_PASS', '');          // default XAMPP password is empty
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a PDO connection with sensible defaults.
 * Throws PDOException on failure so callers can handle it.
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}

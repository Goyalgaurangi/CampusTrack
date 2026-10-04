<?php
declare(strict_types=1);

/**
 * One connection shared by the whole app. Environment values override the
 * XAMPP defaults, so the same project can be used in local development.
 */
function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $name = getenv('DB_NAME') ?: 'campustrack';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $port = getenv('DB_PORT') ?: '3306';
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    try {
        $connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        error_log('CampusTrack database connection failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('CampusTrack could not connect to MySQL. Start MySQL in XAMPP, create/import the campustrack database, then refresh this page.');
    }

    return $connection;
}
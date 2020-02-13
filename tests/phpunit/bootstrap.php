<?php

declare(strict_types=1);

use Dibi\Connection;

require __DIR__ . '/../../vendor/autoload.php';

// Create connection to db
$dns = sprintf(
    'Driver=Cloudera ODBC Driver for Apache Hive 64-bit;Host=%s;Port=%s;Schema=%s;AuthMech=3',
    getenv('HIVE_DB_HOST'),
    getenv('HIVE_DB_PORT'),
    getenv('HIVE_DB_DATABASE'),
);
$connection = new Connection([
    'driver' => 'odbc',
    'dsn' => $dns,
    'username' => getenv('HIVE_DB_USER'),
    'password' => getenv('HIVE_DB_PASSWORD'),
]);

// Test connections
try {
    $connection->query('SHOW TABLES');
} catch (\Throwable $e) {
    throw new RuntimeException('boostrap.php: Cannot connect to Hive DB.', 0, $e);
}

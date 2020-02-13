<?php

declare(strict_types=1);

use Dibi\Connection;
use Dibi\DriverException;
use Keboola\DbExtractor\Extractor;
use Keboola\DbExtractor\HiveOdbcDriver;

require __DIR__ . '/../../vendor/autoload.php';

// Create connections
$connection = new Connection([
    'driver' => HiveOdbcDriver::class,
    'dsn' => Extractor\Hive::createConnectionDns(
        (string) getenv('HIVE_DB_HOST'),
        (int) getenv('HIVE_DB_PORT'),
        (string) getenv('HIVE_DB_DATABASE'),
    ),
    'username' => getenv('HIVE_DB_USER'),
    'password' => getenv('HIVE_DB_PASSWORD'),
]);


// Wait for test data, see docker/hive-server/custom-init.sh
$maxRetries = 30;
$i = 0;
echo 'boostrap.php: Waiting for testing data ...';
while (true) {
    $i++;

    try {
        $connection->query('SELECT * FROM `sales` LIMIT 1')->fetch();
        echo " OK\n";
        break;
    } catch (DriverException $e) {
        if ($i > $maxRetries) {
            throw new RuntimeException('boostrap.php: Cannot connect to Hive DB: ' . $e->getMessage(), 0, $e);
        }

        echo '.';
        sleep(1);
    }
}

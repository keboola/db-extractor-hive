<?php

declare(strict_types=1);

use Keboola\CommonExceptions\UserExceptionInterface;
use Keboola\DbExtractor\Connection\HiveOdbcConnection;
use Keboola\DbExtractor\Connection\HiveDnsFactory;
use Keboola\DbExtractorConfig\Configuration\ValueObject\DatabaseConfig;
use Psr\Log\NullLogger;

require __DIR__ . '/../../vendor/autoload.php';

// Wait for test data, see docker/hive-server/custom-init.sh
$maxRetries = 60;
$i = 0;
echo 'boostrap.php: Waiting for testing data ...';
while (true) {
    $i++;

    try {
        $dbConfig = DatabaseConfig::fromArray([
            'host' => (string) getenv('HIVE_DB_HOST'),
            'port' =>(int) getenv('HIVE_DB_PORT'),
            'user' => (string) getenv('HIVE_DB_USER'),
            'database' => (string) getenv('HIVE_DB_DATABASE'),
            '#password' => (string) getenv('HIVE_DB_PASSWORD'),
        ]);
        $dnsFactory = new HiveDnsFactory();
        $connection = new HiveOdbcConnection(
            new NullLogger(),
            $dnsFactory->create($dbConfig),
            $dbConfig->getUsername(),
            $dbConfig->getPassword(),
        );

        // Query
        $connection->query('SELECT * FROM `sales` LIMIT 1')->fetch();

        echo " OK\n";
        break;
    } catch (UserExceptionInterface $e) {
        if ($i > $maxRetries) {
            throw new RuntimeException('boostrap.php: Cannot connect to Hive DB: ' . $e->getMessage(), 0, $e);
        }

        echo '.';
        sleep(1);
    }
}

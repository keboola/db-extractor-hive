<?php

declare(strict_types=1);

use Keboola\DbExtractor\Connection\HiveOdbcConnectionFactory;
use Keboola\CommonExceptions\UserExceptionInterface;
use Keboola\DbExtractor\Configuration\HiveDatabaseConfig;
use Keboola\DbExtractor\Configuration\HiveSslNode;
use Psr\Log\NullLogger;

require __DIR__ . '/../../vendor/autoload.php';

function waitForHive(array $dbConfigArray): void
{
    // Wait for test data, see docker/hive-server/custom-init.sh
    $maxRetries = 30;
    $i = 0;
    echo sprintf('boostrap.php: Waiting for testing data on host "%s" ...', $dbConfigArray['host']);
    while (true) {
        $i++;

        try {
            $dbConfig = HiveDatabaseConfig::fromArray($dbConfigArray);
            $connectionFactory = new HiveOdbcConnectionFactory();
            $connection = $connectionFactory->create(new NullLogger(), $dbConfig);
            $connection->query('SELECT * FROM `sales` LIMIT 1')->fetch();

            echo " OK\n";
            break;
        } catch (UserExceptionInterface $e) {
            if ($i > $maxRetries) {
                throw new RuntimeException(
                    sprintf(
                        'boostrap.php: Cannot connect to Hive DB "%s": %s',
                        $dbConfigArray['host'],
                        $e->getMessage()
                    ),
                    0,
                    $e
                );
            }

            echo '.';
            sleep(1);
        }
    }
}

// Wait for Hive LDAP instance
waitForHive([
    'authType' => 'password',
    'host' => (string) getenv('HIVE_DB_LDAP_HOST'),
    'port' =>(int) getenv('HIVE_DB_LDAP_PORT'),
    'user' => (string) getenv('HIVE_DB_LDAP_USER'),
    'database' => (string) getenv('HIVE_DB_LDAP_DATABASE'),
    '#password' => (string) getenv('HIVE_DB_LDAP_PASSWORD'),
]);

// Wait for Hive Kerberos instance
waitForHive([
    'authType' => 'kerberos',
    'host' => (string) getenv('HIVE_DB_KERBEROS_HOST'),
    'port' =>(int) getenv('HIVE_DB_KERBEROS_PORT'),
    'database' => (string) getenv('HIVE_DB_KERBEROS_DATABASE'),
    'kerberos' => [
        'principal' => (string) getenv('HIVE_DB_KERBEROS_PRINCIPAL'),
        'config' => (string) file_get_contents((string) getenv('HIVE_DB_KERBEROS_KRB5_CONF_PATH')),
        '#keytab' => (string) file_get_contents((string) getenv('HIVE_DB_KERBEROS_KEYTAB_PATH')),
    ],
    'ssl' => [
        'enabled' => true,
        'ca' => (string) file_get_contents((string) getenv('HIVE_DB_KERBEROS_SSL_CERT_JKS_PATH')),
        'caFileType' => HiveSslNode::CA_FILE_TYPE_JKS,
    ],
]);

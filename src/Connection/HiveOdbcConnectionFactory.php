<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Keboola\DbExtractor\Adapter\ODBC\OdbcConnection;
use Keboola\DbExtractor\Configuration\HiveDatabaseConfig;
use Keboola\DbExtractor\Configuration\HiveDbNode;
use Psr\Log\LoggerInterface;

class HiveOdbcConnectionFactory
{
    public function create(
        LoggerInterface $logger,
        HiveDatabaseConfig $dbConfig,
        bool $isSyncAction = false
    ): HiveOdbcConnection {
        $dsnFactory = new HiveDsnFactory();
        $dsn = $dsnFactory->create($logger, $dbConfig);

        $username = '';
        $password = '';
        if ($dbConfig->getAuthType() === HiveDbNode::AUTH_TYPE_PASSWORD) {
            $username = $dbConfig->getUsername();
            $password = $dbConfig->getPassword();
        }

        $connectRetries = $isSyncAction ? 1 : OdbcConnection::CONNECT_DEFAULT_MAX_RETRIES;
        return new HiveOdbcConnection(
            $logger,
            $dsn,
            $username,
            $password,
            null,
            $connectRetries
        );
    }
}

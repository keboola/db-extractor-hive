<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Keboola\DbExtractor\Adapter\ODBC\OdbcConnection;
use Keboola\DbExtractor\Configuration\HiveDatabaseConfig;
use Psr\Log\LoggerInterface;

class HiveOdbcConnectionFactory
{
    public function create(
        LoggerInterface $logger,
        HiveDatabaseConfig $dbConfig,
        bool $isSyncAction = false
    ): HiveOdbcConnection {
        $certManager = new HiveCertManager($dbConfig);
        $connectRetries = $isSyncAction ? 1 : OdbcConnection::CONNECT_DEFAULT_MAX_RETRIES;
        return new HiveOdbcConnection(
            $logger,
            $dbConfig,
            $certManager,
            $connectRetries
        );
    }
}

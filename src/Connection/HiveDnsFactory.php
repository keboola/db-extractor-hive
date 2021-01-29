<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Keboola\DbExtractorConfig\Configuration\ValueObject\DatabaseConfig;

class HiveDnsFactory
{
    public const ODBC_DRIVER_NAME = 'Cloudera ODBC Driver for Apache Hive 64-bit';

    public function create(DatabaseConfig $dbConfig): string
    {
        return sprintf(
            'Driver=%s;Host=%s;Port=%s;Schema=%s;AuthMech=3;UseNativeQuery=1',
            self::ODBC_DRIVER_NAME,
            $dbConfig->getHost(),
            $dbConfig->getPort(),
            $dbConfig->getDatabase(),
        );
    }
}

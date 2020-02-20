<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Dibi\Drivers\PdoDriver;
use Dibi\Reflector;

/**
 * This driver is used together with db-extractor-common based on PDO.
 * You must use HiveOdbcDriver to load metadata (eg. list of tables),
 * because it cannot be done by SQL: eg. SHOW TABLES (not supported by Hive ODBC driver).
 * See getReflector() method.
 */
class HivePdoOdbcDriver extends PdoDriver
{
    use HiveEscapingTrait;

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $config['dsn'] = 'odbc:' . $config['dsn'];
        parent::__construct($config);

        // Don't prefix columns in result with table name, ... eg. 'price', NOT 'product.price'
        $this->query('set hive.resultset.use.unique.column.names=false');
    }

    public function getReflector(): Reflector
    {
        $odbcDriver = new HiveOdbcDriver($this->config);
        return new HiveOdbcReflector($odbcDriver);
    }
}

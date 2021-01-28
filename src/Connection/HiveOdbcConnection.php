<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Keboola\DbExtractor\Adapter\ODBC\OdbcConnection;

class HiveOdbcConnection extends OdbcConnection
{
    protected function connect(): void
    {
        parent::connect();

        // Don't prefix columns in result with table name, ... eg. 'price', NOT 'product.price'
        $this->query('set hive.resultset.use.unique.column.names=false');
    }
}

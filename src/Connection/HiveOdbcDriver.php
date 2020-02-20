<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Dibi\Drivers\OdbcDriver;

/**
 * This driver is used to load a list of tables through a call to odbc_tables().
 * List of tables cannot be loaded from Hive DB by SQL: SHOW TABLES.
 */
class HiveOdbcDriver extends OdbcDriver
{
    use HiveEscapingTrait;
}

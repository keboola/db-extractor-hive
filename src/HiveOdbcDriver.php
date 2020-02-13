<?php

declare(strict_types=1);

namespace Keboola\DbExtractor;

use Dibi\Drivers\OdbcDriver;

class HiveOdbcDriver extends OdbcDriver
{
    public function escapeIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }
}

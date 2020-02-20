<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

trait HiveEscapingTrait
{
    public function escapeIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }
}

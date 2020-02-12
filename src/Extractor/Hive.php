<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Extractor;

use PDO;

class Hive extends Extractor
{
    /**
     * @inheritDoc
     */
    public function createConnection(array $params)
    {
        // TODO: Implement createConnection() method.
    }

    /**
     * @inheritDoc
     */
    public function testConnection()
    {
        // TODO: Implement testConnection() method.
    }

    /**
     * @inheritDoc
     */
    public function getTables(?array $tables = null): array
    {
        // TODO: Implement getTables() method.
    }

    public function simpleQuery(array $table, array $columns = array()): string
    {
        // TODO: Implement simpleQuery() method.
    }

    public function getMaxOfIncrementalFetchingColumn(array $table): ?string
    {
        // TODO: Implement getMaxOfIncrementalFetchingColumn() method.
    }

    public function validateIncrementalFetching(array $table, string $columnName, ?int $limit = null): void
    {
        // TODO: Implement validateIncrementalFetching() method.
    }
}

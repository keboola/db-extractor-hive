<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Extractor;

use Dibi\Connection;
use Keboola\DbExtractor\Connection\HiveConnectionFactory;
use Keboola\DbExtractorLogger\Logger;
use Keboola\Datatype\Definition\GenericStorage;
use Keboola\DbExtractor\Exception\UserException;
use Keboola\DbExtractor\TableResultFormat\Table;
use Keboola\DbExtractor\TableResultFormat\TableColumn;

class Hive extends Extractor
{
    use DibiSupportExtractorTrait;

    public const INCREMENTAL_TYPES = ['INTEGER', 'NUMERIC', 'FLOAT', 'TIMESTAMP', 'DATE'];

    /** @var Connection */
    protected $db;

    private HiveConnectionFactory $connectionFactory;

    public function __construct(array $parameters, array $state = [], ?Logger $logger = null)
    {
        $this->connectionFactory = new HiveConnectionFactory();
        parent::__construct($parameters, $state, $logger);
    }

    public function createConnection(array $params): Connection
    {
        return $this->connectionFactory->createConnection($params);
    }

    public function testConnection(): void
    {
        $this->executePreparedQuery(['SELECT 1'], 'Test connection error');
    }

    public function getTables(?array $tablesDef = null): array
    {
        // $tables is a array in format [['tableName' => ..., 'schema' => ...], ...]
        // See parent class (package db-extractor-common)
        $allowedNames = $tablesDef ? array_map(fn($def) => $def['tableName'], $tablesDef): null;

        $databaseName = $this->db->getDatabaseInfo()->name;
        $reflector = $this->db->getDriver()->getReflector();
        $tables = $reflector->getTables();

        /** @var Table[] $tableDefs */
        $tableDefs = [];

        foreach ($tables as $table) {
            $tableName = $table['name'];
            if ($allowedNames && !in_array($tableName, $allowedNames, true)) {
                // skip if name is not in allowed names
                continue;
            }

            $tableFormat = new Table();
            $tableFormat
                ->setName($tableName)
                ->setSchema($databaseName);
            $tableDefs[] = $tableFormat;

            $columns = $reflector->getColumns($tableName);
            foreach ($columns as $column) {
                // Hive DB doesn't support PK, FK, NOT NULL,...
                // See: https://issues.apache.org/jira/browse/HIVE-6905
                $baseType = new GenericStorage($column['nativetype'], ['length' => $column['size']]);
                $columnFormat = new TableColumn();
                $columnFormat
                    ->setName($column['name'])
                    ->setType($baseType->getBasetype())
                    ->setLength($baseType->getLength());

                $tableFormat->addColumn($columnFormat);
            }
        }

        return array_map(fn(Table $item) => $item->getOutput(), $tableDefs);
    }

    public function simpleQuery(array $table, array $columns = []): string
    {
        $query = [];
        $query[] = empty($columns) ? 'SELECT *' : 'SELECT %n';
        $query[] = $columns ?: '';
        $query[] = 'FROM %n.%n';
        $query[] = $table['schema'];
        $query[] = $table['tableName'];

        $query = array_merge($query, $this->getIncrementalQueryParts());

        if ($this->incrementalFetching) {
            $query[] = 'ORDER BY %n ASC';
            $query[] = $this->incrementalFetching['column'];

            if ($this->hasIncrementalLimit()) {
                $query[] = 'LIMIT %i';
                $query[] = $this->incrementalFetching['limit'];
            }
        }

        return $this->db->translate($query);
    }

    public function validateIncrementalFetching(array $table, string $columnName, ?int $limit = null): void
    {
        $tableInfo = $this->getTables([$table])[0] ?? null;
        if (!$tableInfo) {
            throw new UserException(sprintf('Table "%s" not found.', $table['tableName']));
        }

        $column = current(array_filter($tableInfo['columns'], fn($item) => $item['name'] === $columnName));
        if (!$column) {
            throw new UserException(sprintf('Incremental fetching column "%s" not found.', $columnName));
        }

        $datatype = new GenericStorage($column['type']);
        if (!in_array($datatype->getBasetype(), self::INCREMENTAL_TYPES, true)) {
            throw new UserException(sprintf(
                'Unexpected type "%s" of incremental fetching column "%s". Expected types: %s.',
                $column['type'],
                $columnName,
                implode(', ', self::INCREMENTAL_TYPES),
            ));
        }

        $this->incrementalFetching['column'] = $columnName;
        if ($limit) {
            $this->incrementalFetching['limit'] = $limit;
        }
    }

    public function getMaxOfIncrementalFetchingColumn(array $table): ?string
    {
        // $table is a array in format ['tableName' => ..., 'schema' => ...]
        // See parent class (package db-extractor-common)
        $query = [];
        $query[] = 'SELECT MAX(%n) AS %n FROM %n.%n';
        $query[] = $this->incrementalFetching['column'];
        $query[] = 'max_value';
        $query[] = $table['schema'];
        $query[] = $table['tableName'];

        $result = $this
            ->executePreparedQuery($query, 'Fetching incremental max value error')
            ->fetch();

        return $result['max_value'] ?? null;
    }

    protected function getIncrementalQueryParts(): array
    {
        $query = [];

        if ($this->incrementalFetching) {
            if (isset($this->state['lastFetchedRow'])) {
                $query[] = 'WHERE %n >= %s';
                $query[] = $this->incrementalFetching['column'];
                $query[] = $this->state['lastFetchedRow'];
            }
        }

        return $query;
    }
}

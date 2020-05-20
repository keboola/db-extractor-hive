<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Extractor;

use Keboola\DbExtractor\Connection\HiveConnectionFactory;
use Keboola\Datatype\Definition\GenericStorage;
use Keboola\DbExtractor\Exception\UserException;
use Keboola\DbExtractorConfig\Configuration\ValueObject\ExportConfig;
use Psr\Log\LoggerInterface;

class Hive extends BaseExtractor
{
    use DibiSupportExtractorTrait;

    public const INCREMENTAL_TYPES = ['INTEGER', 'NUMERIC', 'FLOAT', 'TIMESTAMP', 'DATE'];

    /** @var Connection */
    protected $db;

    private HiveConnectionFactory $connectionFactory;

    public function __construct(array $parameters, array $state, LoggerInterface $logger)
    {
        $this->connectionFactory = new HiveConnectionFactory();
        parent::__construct($parameters, $state, $logger);
    }

    public function getMetadataProvider(): MetadataProvider
    {
        // TODO: Implement getMetadataProvider() method.
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

    public function simpleQuery(ExportConfig $exportConfig): string
    {
        $query = [];
        $query[] = $exportConfig->hasColumns() ? 'SELECT %n' : 'SELECT *';
        $query[] = $exportConfig->hasColumns() ? $exportConfig->getColumns() : '';
        $query[] = 'FROM %n.%n';
        $query[] = $exportConfig->getTable()->getSchema();
        $query[] = $exportConfig->getTable()->getName();

        $query = array_merge($query, $this->getIncrementalQueryParts($exportConfig));

        if ($exportConfig->isIncrementalFetching()) {
            $query[] = 'ORDER BY %n ASC';
            $query[] = $exportConfig->getIncrementalFetchingColumn();

            if ($exportConfig->hasIncrementalFetchingLimit()) {
                $query[] = 'LIMIT %i';
                $query[] = $exportConfig->getIncrementalFetchingLimit();
            }
        }

        return $this->db->translate($query);
    }

    public function validateIncrementalFetching(ExportConfig $exportConfig): void
    {
        $table = $this->getMetadataProvider()->getTable($exportConfig->getTable());
        $column = $table->getColumns()->getByName($exportConfig->getIncrementalFetchingColumn());

        $datatype = new GenericStorage($column->getType());
        if (!in_array($datatype->getBasetype(), self::INCREMENTAL_TYPES, true)) {
            throw new UserException(sprintf(
                'Unexpected type "%s" of incremental fetching column "%s". Expected types: %s.',
                $column->getType(),
                $column->getName(),
                implode(', ', self::INCREMENTAL_TYPES),
            ));
        }
    }

    public function getMaxOfIncrementalFetchingColumn(ExportConfig $exportConfig): ?string
    {
        $query = [];
        $query[] = 'SELECT MAX(%n) AS %n FROM %n.%n';
        $query[] = $exportConfig->getIncrementalFetchingColumn();
        $query[] = 'max_value';
        $query[] = $exportConfig->getTable()->getSchema();
        $query[] = $exportConfig->getTable()->getName();

        $result = $this
            ->executePreparedQuery($query, 'Fetching incremental max value error')
            ->fetch();

        return $result['max_value'] ?? null;
    }

    protected function getIncrementalQueryParts(ExportConfig $exportConfig): array
    {
        $query = [];

        if ($exportConfig->isIncrementalFetching()) {
            if (isset($this->state['lastFetchedRow'])) {
                $query[] = 'WHERE %n >= %s';
                $query[] = $exportConfig->getIncrementalFetchingColumn();
                $query[] = $this->state['lastFetchedRow'];
            }
        }

        return $query;
    }
}

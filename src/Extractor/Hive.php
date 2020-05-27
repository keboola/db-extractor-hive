<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Extractor;

use Keboola\DbExtractor\TableResultFormat\Exception\ColumnNotFoundException;
use Dibi\Connection;
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
        return new HiveMetadataProvider($this->db);
    }

    public function createConnection(array $params): Connection
    {
        return $this->connectionFactory->createConnection($params);
    }

    public function testConnection(): void
    {
        $this->executePreparedQuery(['SELECT 1'], 'Test connection error');
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
        try {
            $column = $table->getColumns()->getByName($exportConfig->getIncrementalFetchingColumn());
        } catch (ColumnNotFoundException $e) {
            throw new UserException(sprintf(
                'Incremental fetching column "%s" not found.',
                $exportConfig->getIncrementalFetchingColumn()
            ), 0, $e);
        }

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

<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Extractor;

use InvalidArgumentException;
use Keboola\Datatype\Definition\BaseType;
use Keboola\Datatype\Definition\GenericStorage;
use Keboola\DbExtractor\Adapter\ExportAdapter;
use Keboola\DbExtractor\Adapter\Metadata\MetadataProvider;
use Keboola\DbExtractor\Adapter\ODBC\OdbcConnection;
use Keboola\DbExtractor\Adapter\ODBC\OdbcExportAdapter;
use Keboola\DbExtractor\Adapter\ODBC\OdbcNativeMetadataProvider;
use Keboola\DbExtractor\Adapter\Query\DefaultQueryFactory;
use Keboola\DbExtractor\Configuration\HiveDatabaseConfig;
use Keboola\DbExtractor\Connection\HiveOdbcConnectionFactory;
use Keboola\DbExtractor\Exception\UserException;
use Keboola\DbExtractor\TableResultFormat\Exception\ColumnNotFoundException;
use Keboola\DbExtractor\TableResultFormat\Metadata\ValueObject\Column;
use Keboola\DbExtractorConfig\Configuration\ValueObject\DatabaseConfig;
use Keboola\DbExtractorConfig\Configuration\ValueObject\ExportConfig;

class Hive extends BaseExtractor
{
    public const INCREMENTAL_TYPES = [
        'BIGINT',
        'SMALLINT',
        'TINYINT',
        'INT',
        'NUMERIC',
        'DECIMAL',
        'FLOAT',
        'DOUBLE',
        'TIMESTAMP',
        'DATE',
    ];

    protected OdbcConnection $connection;

    public function testConnection(): void
    {
        $this->connection->testConnection();
    }

    protected function createConnection(DatabaseConfig $dbConfig): void
    {
        if (!$dbConfig instanceof HiveDatabaseConfig) {
            throw new InvalidArgumentException('Expected HiveDatabaseConfig.');
        }

        $factory = new HiveOdbcConnectionFactory();
        $this->connection = $factory->create($this->logger, $dbConfig, $this->isSyncAction());
    }

    protected function createExportAdapter(): ExportAdapter
    {
        $queryFactory = new DefaultQueryFactory($this->state);
        $resultWriter = new HiveResultWriter($this->state);
        return new OdbcExportAdapter(
            $this->logger,
            $this->connection,
            $queryFactory,
            $resultWriter,
            $this->dataDir,
            $this->state,
        );
    }

    public function createMetadataProvider(): MetadataProvider
    {
        return new OdbcNativeMetadataProvider($this->connection);
    }

    public function validateIncrementalFetching(ExportConfig $exportConfig): void
    {
        $column = $this->getIncrementalFetchingColumn($exportConfig);

        $datatype = new GenericStorage($column->getType());
        if (!in_array($datatype->getType(), self::INCREMENTAL_TYPES, true)) {
            throw new UserException(sprintf(
                'Unexpected type "%s" of incremental fetching column "%s". Expected types: %s.',
                $column->getType(),
                $column->getName(),
                implode(', ', self::INCREMENTAL_TYPES),
            ));
        }
    }

    /**
     * Detects the resolver basetype ("TIMESTAMP" | "INTEGER" | "NUMERIC" | "FLOAT") of the incremental
     * fetching column. Overrides the default-null hook in db-extractor-common's BaseExtractor, opting
     * Hive into the incremental fetching window/lookback feature (the type is needed to resolve the
     * relative/absolute bounds). Reuses the same metadata lookup as validateIncrementalFetching().
     *
     * GenericStorage maps a Hive DATE column to the "DATE" basetype, which the window/lookback resolver
     * does not support (it handles only TIMESTAMP/INTEGER/NUMERIC/FLOAT). Rather than assume Hive
     * compares a DATE column correctly against the resolver's "Y-m-d H:i:s" literal — which is not
     * verified — DATE returns null here, so window/lookback reports "not supported by this extractor"
     * for a DATE column while plain watermark keeps working. It can be enabled later once a DATE
     * comparison is verified against the live Hive harness. Every other type in self::INCREMENTAL_TYPES
     * maps to a supported basetype.
     */
    public function getIncrementalFetchingColumnType(ExportConfig $exportConfig): ?string
    {
        $datatype = new GenericStorage($this->getIncrementalFetchingColumn($exportConfig)->getType());
        $basetype = $datatype->getBasetype();

        return $basetype === BaseType::DATE ? null : $basetype;
    }

    private function getIncrementalFetchingColumn(ExportConfig $exportConfig): Column
    {
        $table = $this->getMetadataProvider()->getTable($exportConfig->getTable());
        try {
            return $table->getColumns()->getByName($exportConfig->getIncrementalFetchingColumn());
        } catch (ColumnNotFoundException $e) {
            throw new UserException(sprintf(
                'Incremental fetching column "%s" not found.',
                $exportConfig->getIncrementalFetchingColumn(),
            ), 0, $e);
        }
    }

    protected function getMaxOfIncrementalFetchingColumn(ExportConfig $exportConfig): ?string
    {
        $sql = sprintf(
            'SELECT MAX(%s) as %s FROM %s.%s',
            $this->connection->quoteIdentifier($exportConfig->getIncrementalFetchingColumn()),
            $this->connection->quoteIdentifier($exportConfig->getIncrementalFetchingColumn()),
            $this->connection->quoteIdentifier($exportConfig->getTable()->getSchema()),
            $this->connection->quoteIdentifier($exportConfig->getTable()->getName()),
        );
        $result = $this->connection->query($sql, $exportConfig->getMaxRetries())->fetchAll();
        return $result ? $result[0][$exportConfig->getIncrementalFetchingColumn()] : null;
    }

    protected function createDatabaseConfig(array $data): DatabaseConfig
    {
        return HiveDatabaseConfig::fromArray($data);
    }
}

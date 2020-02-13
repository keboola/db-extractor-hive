<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Extractor;

use Dibi\Connection;
use Keboola\Csv\CsvFile;
use Keboola\Datatype\Definition\Exception\InvalidLengthException;
use Keboola\Datatype\Definition\GenericStorage;
use Keboola\DbExtractor\DbRetryProxy;
use Keboola\DbExtractor\Exception\ApplicationException;
use Keboola\DbExtractor\Exception\DeadConnectionException;
use Keboola\DbExtractor\Exception\UserException;
use Keboola\DbExtractor\TableResultFormat\Table;
use Keboola\DbExtractor\TableResultFormat\TableColumn;

class Hive extends Extractor
{
    public const INCREMENT_TYPE_NUMERIC = 'numeric';
    public const INCREMENT_TYPE_TIMESTAMP = 'timestamp';
    public const INCREMENT_TYPE_DATE = 'date';
    public const NUMERIC_BASE_TYPES = ['INTEGER', 'NUMERIC', 'FLOAT'];

    private const DEFAULT_PORT = 10000;

    /**
     * @inheritDoc
     */
    public function createConnection(array $params)
    {
        // check params
        foreach (['host', 'database', 'user', '#password'] as $r) {
            if (!array_key_exists($r, $params)) {
                throw new UserException(sprintf('Parameter %s is missing.', $r));
            }
        }

        $dsn = sprintf(
            'Driver=Cloudera ODBC Driver for Apache Hive 64-bit;Host=%s;Port=%s;Schema=%s;AuthMech=3',
            $params['host'],
            isset($params['port']) ? $params['port'] : self::DEFAULT_PORT,
            $params['database'],
        );

        return new Connection([
            'driver' => 'odbc',
            'dsn' => $dsn,
            'username' => $params['user'],
            'password' => $params['#password'],
        ]);
    }

    /**
     * @inheritDoc
     */
    public function testConnection()
    {
        $this->runRetriableQuery('SELECT 1', 'Test connection error', 'query');
    }

    /**
     * @inheritDoc
     */
    public function getTables(?array $tables = null): array
    {
        $sql = 'SHOW TABLES';
        $arr = $this->runRetriableQuery($sql, 'Show tables error');

        /** @var Table[] $tableDefs */
        $tableDefs = [];
        foreach ($arr as $table) {
            $tableFormat = new Table();
            $tableFormat
                ->setName($table->name)
                ->setSchema('');

            $sql = 'SHOW COLUMN STATS ' . $table->name;

            $columns = $this->runRetriableQuery($sql, 'Get table columns info error');

            foreach ($columns as $column) {
                $columnType = $column->Type;
                $metadataOptions = [];
                preg_match('/(.*)\((.*)\)/', $columnType, $matches);
                if (isset($matches[1])) {
                    $columnType = $matches[1];
                }
                if (isset($matches[2])) {
                    $metadataOptions['length'] = $matches[2];
                }
                $baseType = new GenericStorage($columnType, $metadataOptions);
                $columnFormat = new TableColumn();
                $columnFormat
                    ->setName($column->Column)
                    ->setNullable(($column->{'#Nulls'} === '-1') ? false : true)
                    ->setType($baseType->getBasetype())
                    ->setLength($baseType->getLength());

                $tableFormat->addColumn($columnFormat);
            }
            $tableDefs[] = $tableFormat;
        }
        array_walk($tableDefs, function (Table &$item): void {
            $item = $item->getOutput();
        });
        return array_values($tableDefs);
    }

    public function simpleQuery(array $table, array $columns = array()): string
    {
        if (count($columns) > 0) {
            $query = sprintf(
                'SELECT %s FROM %s.%s',
                implode(', ', array_map(function ($column): string {
                    return $this->quoteIdentifier($column);
                }, $columns)),
                $this->quoteIdentifier($table['schema']),
                $this->quoteIdentifier($table['tableName'])
            );
        } else {
            $query = sprintf(
                'SELECT * FROM %s.%s',
                $this->quoteIdentifier($table['schema']),
                $this->quoteIdentifier($table['tableName'])
            );
        }

        $incrementalAddon = $this->getIncrementalQueryAddon();
        if ($incrementalAddon) {
            $query .= $incrementalAddon;
        }

        if ($this->hasIncrementalLimit()) {
            $query .= sprintf(
                ' ORDER BY %s LIMIT %d',
                $this->incrementalFetching['column'],
                $this->incrementalFetching['limit']
            );
        }
        return $query;
    }

    public function getMaxOfIncrementalFetchingColumn(array $table): ?string
    {
        if (isset($this->incrementalFetching['limit']) && $this->incrementalFetching['limit'] > 0) {
            $fullsql = sprintf(
                'SELECT %s FROM %s.%s',
                $this->incrementalFetching['column'],
                $table['schema'],
                $table['tableName']
            );

            $fullsql .= $this->getIncrementalQueryAddon();

            $countRows = count(
                $this->db->query(
                    sprintf(
                        '%s LIMIT %s',
                        $fullsql,
                        $this->incrementalFetching['limit']
                    )
                )->fetchAll()
            );
            $fullsql .= sprintf(
                ' ORDER BY %s LIMIT %s OFFSET %s',
                $this->incrementalFetching['column'],
                1,
                min($this->incrementalFetching['limit'], $countRows) - 1
            );
        } else {
            $fullsql = sprintf(
                'SELECT MAX(%s) as %s FROM %s.%s',
                $this->quoteIdentifier($this->incrementalFetching['column']),
                $this->quoteIdentifier($this->incrementalFetching['column']),
                $this->quoteIdentifier($table['schema']),
                $this->quoteIdentifier($table['tableName'])
            );
        }
        $result = $this->runRetriableQuery($fullsql, 'Fetching incremental max value error');
        if (count($result) > 0) {
            return (string) $result[0][$this->incrementalFetching['column']];
        }
        return null;
    }

    public function validateIncrementalFetching(array $table, string $columnName, ?int $limit = null): void
    {
        $tables = $this->getTables();
        $tableName = $table['tableName'];
        $tables = array_values(array_filter($tables, function ($item) use ($tableName) {
            return $item['name'] === $tableName;
        }));
        $columns = array_values(array_filter($tables[0]['columns'], function ($item) use ($columnName) {
            return $item['name'] === $columnName;
        }));

        try {
            $datatype = new GenericStorage($columns[0]['type']);
            if (in_array($datatype->getBasetype(), self::NUMERIC_BASE_TYPES)) {
                $this->incrementalFetching['column'] = $columnName;
                $this->incrementalFetching['type'] = self::INCREMENT_TYPE_NUMERIC;
            } elseif ($datatype->getBasetype() === 'TIMESTAMP') {
                $this->incrementalFetching['column'] = $columnName;
                $this->incrementalFetching['type'] = self::INCREMENT_TYPE_TIMESTAMP;
            } elseif ($datatype->getBasetype() === 'DATE') {
                $this->incrementalFetching['column'] = $columnName;
                $this->incrementalFetching['type'] = self::INCREMENT_TYPE_DATE;
            } else {
                throw new UserException('invalid incremental fetching column type');
            }
        } catch (InvalidLengthException | UserException $exception) {
            throw new UserException(
                sprintf(
                    'Column [%s] specified for incremental fetching is not a numeric or timestamp type column',
                    $columnName
                )
            );
        }

        if ($limit) {
            $this->incrementalFetching['limit'] = $limit;
        }
    }

    public function export(array $table): array
    {
        $outputTable = $table['outputTable'];
        $csv = $this->createOutputCsv($outputTable);

        $maxValue = null;
        if (!isset($table['query']) || $table['query'] === '') {
            $query = $this->simpleQuery($table['table'], $table['columns']);
            if (isset($this->incrementalFetching)) {
                $maxValue = $this->getMaxOfIncrementalFetchingColumn($table['table']);
            }
        } else {
            $query = $table['query'];
        }

        $this->logger->info('Exporting to ' . $outputTable);

        try {
            $countRows = $this->executeQueryToCsv($query, $csv);
        } catch (\Exception $e) {
            throw new UserException('DB query failed: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new UserException('DB query failed: ' . $e->getMessage());
        }

        if ($this->createManifest($table) === false) {
            throw new ApplicationException('Unable to create manifest', 0, null, [
                'table' => $table,
            ]);
        }
        $output = [
            'outputTable' => $outputTable,
            'rows' => $countRows,
        ];

        // output state
        if ($maxValue) {
            $output['state']['lastFetchedRow'] = $maxValue;
        }
        return $output;
    }

    protected function executeQueryToCsv(string $query, CsvFile $csv): int
    {
        $firstRow = $this->runRetriableQuery($query, 'Fetch first row error', 'fetch');
        if (!$firstRow) {
            $this->logger->warn('Query returned empty result. Nothing was imported.');
            return 0;
        }
        // write header
        $csv->writeRow(array_keys($firstRow));

        $fetchRows = $this->runRetriableQuery($query, 'Fetching data error');
        foreach ($fetchRows as $row) {
            $csv->writeRow((array) $row);
        }
        // +1 is a first row
        return count($fetchRows);
    }

    private function runRetriableQuery(string $query, string $errorMessage = '', string $type = 'fetchAll'): array
    {
        $retryProxy = new DbRetryProxy(
            $this->logger,
            DbRetryProxy::DEFAULT_MAX_TRIES,
            [\PDOException::class, \ErrorException::class, \Throwable::class]
        );

        try {
            return $retryProxy->call(function () use ($query, $type): array {
                try {
                    /** @var array $result */
                    $result = (array) $this->db->{$type}($query);
                    return $result;
                } catch (\Throwable $e) {
                    $this->tryReconnect();
                    throw $e;
                }
            });
        } catch (\Throwable $exception) {
            throw new UserException($errorMessage . ': ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function tryReconnect(): void
    {
        try {
            $this->isAlive();
        } catch (DeadConnectionException $deadConnectionException) {
            $reconnectionRetryProxy = new DbRetryProxy(
                $this->logger,
                self::DEFAULT_MAX_TRIES,
                null,
                1000
            );

            try {
                $this->db = $reconnectionRetryProxy->call(function () {
                    return $this->createConnection($this->getDbParameters());
                });
            } catch (\Throwable $reconnectException) {
                throw new UserException(
                    'Unable to reconnect to the database: ' . $reconnectException->getMessage(),
                    $reconnectException->getCode(),
                    $reconnectException
                );
            }
        }
    }

    private function getIncrementalQueryAddon(): ?string
    {
        $incrementalAddon = null;
        if ($this->incrementalFetching) {
            if (isset($this->state['lastFetchedRow'])) {
                $incrementalAddon = sprintf(
                    ' WHERE %s >= %s',
                    $this->incrementalFetching['column'],
                    $this->shouldQuoteComparison($this->incrementalFetching['type'])
                        ? $this->quote($this->state['lastFetchedRow'])
                        : $this->state['lastFetchedRow']
                );
            }
        }
        return $incrementalAddon;
    }

    private function shouldQuoteComparison(string $type): bool
    {
        if ($type === self::INCREMENT_TYPE_NUMERIC) {
            return false;
        }
        return true;
    }

    private function quote(string $str): string
    {
        $q = '"';
        return ($q . str_replace("$q", "\\$q", $str) . $q);
    }

    private function quoteIdentifier(string $str): string
    {
        $q = '`';
        return ($q . str_replace("$q", "\\$q", $str) . $q);
    }
}

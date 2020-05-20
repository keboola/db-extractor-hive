<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Extractor;

use Dibi;
use Dibi\Connection;
use Keboola\Csv\CsvFile;
use Keboola\Csv\Exception as CsvException;
use Keboola\DbExtractor\Exception\ApplicationException;
use Keboola\DbExtractor\Exception\DeadConnectionException;
use Psr\Log\LoggerInterface;
use Throwable;
use Dibi\Result;
use Keboola\DbExtractor\DbRetryProxy;
use Keboola\DbExtractor\Exception\UserException;

/**
 * This trait solves compatibility problem:
 * - Extractor class from db-extractor-common depends on PDO
 * - PDO ODBC driver (DIBI -> PDO -> ODBC -> DB) doesn't work with special types (MAP, ARRAY, ...) -> it returns NULL
 * - ODBC driver (DIBI -> ODBC -> DB) works with special types
 * - ... therefore must be used ODBC driver and fixed compatibility with db-extractor-common package
 */
trait DibiSupportExtractorTrait
{
    protected LoggerInterface $logger;

    /** @var Connection */
    protected $db;

    public function export(array $table): array
    {
        $outputTable = $table['outputTable'];

        $this->logger->info('Exporting to ' . $outputTable);

        $isAdvancedQuery = true;
        if (array_key_exists('table', $table) && !array_key_exists('query', $table)) {
            $isAdvancedQuery = false;
            $query = $this->simpleQuery($table['table'], $table['columns']);
        } else {
            $query = $table['query'];
        }
        $maxValue = null;
        if ($this->canFetchMaxIncrementalValueSeparately($isAdvancedQuery)) {
            $maxValue = $this->getMaxOfIncrementalFetchingColumn($table['table']);
        }

        $maxTries = isset($table['retries']) ? (int) $table['retries'] : self::DEFAULT_MAX_TRIES;

        $proxy = new DbRetryProxy($this->logger, $maxTries, [DeadConnectionException::class, \ErrorException::class]);

        try {
            $result = $proxy->call(function () use ($query, $maxTries, $outputTable, $isAdvancedQuery) {
                $stmt = $this->executeDibiQuery($query, $maxTries); // <<<<<<<<< MODIFIED
                $csv = $this->createOutputCsv($outputTable);
                $result = $this->writeToCsvFromDibi($stmt, $csv, $isAdvancedQuery);  // <<<<<<<<< MODIFIED
                $this->isAlive();
                return $result;
            });
        } catch (CsvException $e) {
            throw new ApplicationException('Failed writing CSV File: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (Dibi\Exception $e) {
            throw $this->handleDbError($e, $table, $maxTries);
        } catch (\ErrorException $e) {
            throw $this->handleDbError($e, $table, $maxTries);
        } catch (DeadConnectionException $e) {
            throw $this->handleDbError($e, $table, $maxTries);
        }
        if ($result['rows'] > 0) {
            $this->createManifest($table);
        } else {
            $this->logger->warn(
                sprintf(
                    'Query returned empty result. Nothing was imported to [%s]',
                    $table['outputTable']
                )
            );
        }

        $output = [
            'outputTable' => $outputTable,
            'rows' => $result['rows'],
        ];
        // output state
        if (isset($this->incrementalFetching['column'])) {
            if ($maxValue) {
                $output['state']['lastFetchedRow'] = $maxValue;
            } elseif (!empty($result['lastFetchedRow'])) {
                $output['state']['lastFetchedRow'] = $result['lastFetchedRow'];
            }
        }
        return $output;
    }

    protected function writeToCsvFromDibi(Result $result, CsvFile $csv, bool $includeHeader = true): array
    {
        $output = [];

        $resultRow = $result->fetch(); // <<<<<<< MODIFIED
        $resultRowArray = $resultRow ? $resultRow->toArray() : null;

        if (!empty($resultRowArray)) {
            // write header and first line
            if ($includeHeader) {
                $csv->writeRow(array_keys($resultRowArray));
            }
            $csv->writeRow($resultRowArray);

            // write the rest
            $numRows = 1;
            $lastRow = $resultRowArray;

            while ($resultRow = $result->fetch()) { // <<<<<<< MODIFIED
                $resultRowArray = $resultRow->toArray();
                $csv->writeRow($resultRowArray);
                $lastRow = $resultRowArray;
                $numRows++;
            }
            $result->free(); // <<<<<<< MODIFIED

            if (isset($this->incrementalFetching['column'])) {
                if (!array_key_exists($this->incrementalFetching['column'], $lastRow)) {
                    throw new UserException(
                        sprintf(
                            'The specified incremental fetching column %s not found in the table',
                            $this->incrementalFetching['column']
                        )
                    );
                }
                $output['lastFetchedRow'] = $lastRow[$this->incrementalFetching['column']];
            }
            $output['rows'] = $numRows;
            return $output;
        }
        // no rows found.  If incremental fetching is turned on, we need to preserve the last state
        if (isset($this->incrementalFetching['column']) && isset($this->state['lastFetchedRow'])) {
            $output = $this->state;
        }
        $output['rows'] = 0;
        return $output;
    }

    protected function executePreparedQuery(array $args, ?string $errorMessage = null): Result
    {
        try {
            $query = (string) $this->db->translate(...$args);
            return $this->executeDibiQuery($query, DbRetryProxy::DEFAULT_MAX_TRIES);
        } catch (\Throwable $exception) {
            if ($errorMessage) {
                throw new UserException($errorMessage . ': ' . $exception->getMessage(), 0, $exception);
            }

            throw $exception;
        }
    }

    protected function executeDibiQuery(string $query, ?int $maxTries): Result
    {
        $proxy = new DbRetryProxy($this->logger, $maxTries);

        return $proxy->call(function () use ($query) {
            try {
                return $this->db->query($query);
            } catch (Throwable $e) {
                try {
                    $this->db = $this->createConnection($this->getDbParameters());
                } catch (Throwable $e) {
                }
                throw $e;
            }
        });
    }
}

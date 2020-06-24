<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Extractor;

use ErrorException;
use Dibi\Connection;
use Dibi\Exception as DibiException;
use Keboola\Csv\CsvWriter;
use Keboola\Csv\Exception as CsvException;
use Keboola\DbExtractor\Exception\ApplicationException;
use Keboola\DbExtractor\Exception\DeadConnectionException;
use Keboola\DbExtractorConfig\Configuration\ValueObject\ExportConfig;
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

    public function export(ExportConfig $exportConfig): array
    {
        if ($exportConfig->isIncrementalFetching()) {
            $this->validateIncrementalFetching($exportConfig);
            $maxValue = $this->canFetchMaxIncrementalValueSeparately($exportConfig) ?
                $this->getMaxOfIncrementalFetchingColumn($exportConfig) : null;
        } else {
            $maxValue = null;
        }

        $this->logger->info('Exporting to ' . $exportConfig->getOutputTable());
        $query = $exportConfig->hasQuery() ? $exportConfig->getQuery() : $this->simpleQuery($exportConfig);
        $proxy = new DbRetryProxy(
            $this->logger,
            $exportConfig->getMaxRetries(),
            [DeadConnectionException::class, ErrorException::class]
        );

        try {
            $result = $proxy->call(function () use ($query, $exportConfig) {
                $stmt = $this->executeDibiQuery($query, $exportConfig->getMaxRetries()); // <<<<<<<<< MODIFIED
                $csv = $this->createOutputCsv($exportConfig->getOutputTable());
                $result = $this->writeToCsvFromDibi($stmt, $csv, $exportConfig);  // <<<<<<<<< MODIFIED
                $this->isAlive();
                return $result;
            });
        } catch (CsvException $e) {
            throw new ApplicationException('Failed writing CSV File: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (\PDOException | \ErrorException | DeadConnectionException | DibiException $e) {
            throw $this->handleDbError($e, $exportConfig->getMaxRetries(), $exportConfig->getOutputTable());
        }

        if ($result['rows'] > 0) {
            $this->createManifest($exportConfig);
        } else {
            @unlink($this->getOutputFilename($exportConfig->getOutputTable())); // no rows, no file
            $this->logger->warning(sprintf(
                'Query returned empty result. Nothing was imported to [%s]',
                $exportConfig->getOutputTable()
            ));
        }

        $output = [
            'outputTable' => $exportConfig->getOutputTable(),
            'rows' => $result['rows'],
        ];

        // output state
        if ($exportConfig->isIncrementalFetching()) {
            if ($maxValue) {
                $output['state']['lastFetchedRow'] = $maxValue;
            } elseif (!empty($result['lastFetchedRow'])) {
                $output['state']['lastFetchedRow'] = $result['lastFetchedRow'];
            }
        }
        return $output;
    }

    protected function writeToCsvFromDibi(Result $result, CsvWriter $csv, ExportConfig $exportConfig): array
    {
        // With custom query are no metadata in manifest, so header must be present
        $includeHeader = $exportConfig->hasQuery();
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
                $resultRowArray = $resultRow->toArray(); // <<<<<<< MODIFIED
                $csv->writeRow($resultRowArray);
                $lastRow = $resultRowArray;
                $numRows++;
            }
            $result->free(); // <<<<<<< MODIFIED

            if ($exportConfig->isIncrementalFetching()) {
                $incrementalColumn = $exportConfig->getIncrementalFetchingConfig()->getColumn();
                if (!array_key_exists($incrementalColumn, $lastRow)) {
                    throw new UserException(
                        sprintf(
                            'The specified incremental fetching column %s not found in the table',
                            $incrementalColumn
                        )
                    );
                }
                $output['lastFetchedRow'] = $lastRow[$incrementalColumn];
            }
            $output['rows'] = $numRows;
            return $output;
        }
        // no rows found.  If incremental fetching is turned on, we need to preserve the last state
        if ($exportConfig->isIncrementalFetching() && isset($this->state['lastFetchedRow'])) {
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

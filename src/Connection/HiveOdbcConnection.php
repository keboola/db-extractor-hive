<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Keboola\DbExtractor\Adapter\Exception\OdbcException;
use Keboola\DbExtractor\Adapter\ODBC\OdbcConnection;
use Keboola\DbExtractor\Adapter\ODBC\OdbcQueryResult;
use Keboola\DbExtractor\Adapter\ValueObject\QueryResult;
use Keboola\DbExtractor\Configuration\HiveDatabaseConfig;
use Keboola\DbExtractor\Configuration\HiveDbNode;
use Psr\Log\LoggerInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\RetryProxy;
use SqlFormatter;
use Throwable;

class HiveOdbcConnection extends OdbcConnection
{
    private HiveCertManager $certManager;

    public function __construct(
        LoggerInterface $logger,
        HiveDatabaseConfig $dbConfig,
        HiveCertManager $certManager,
        int $connectMaxRetries,
    ) {
        // We will save the reference to the certification manager.
        // Method HiveCertManager::__destruct deletes temp certificates from disk.
        $this->certManager = $certManager;
        $dsnFactory = new HiveDsnFactory();
        $dsn = $dsnFactory->create($logger, $dbConfig, $certManager);

        $username = '';
        $password = '';
        if ($dbConfig->getAuthType() === HiveDbNode::AUTH_TYPE_PASSWORD) {
            $username = $dbConfig->getUsername();
            $password = $dbConfig->getPassword();
        }

        parent::__construct($logger, $dsn, $username, $password, null, $connectMaxRetries);
    }

    protected function connect(): void
    {
        parent::connect();

        // Don't prefix columns in result with table name, ... eg. 'price', NOT 'product.price'
        $this->query('set hive.resultset.use.unique.column.names=false');
    }

    protected function createRetryProxy(int $maxRetries): RetryProxy
    {
        $retryPolicy = new HiveRetryPolicy($maxRetries, $this->getExpectedExceptionClasses());
        $backoffPolicy = new ExponentialBackOffPolicy(1000);
        return new RetryProxy($retryPolicy, $backoffPolicy, $this->logger);
    }

    /**
     * @return mixed - returned value from $processor
     */
    public function queryAndProcess(string $query, int $maxRetries, callable $processor): mixed
    {
        return parent::queryAndProcess(SqlFormatter::removeComments($query), $maxRetries, $processor);
    }

    protected function doQuery(string $query): QueryResult
    {
        try {
            /** @var resource|false $stmt */
            $stmt = @odbc_exec($this->connection, $query);
        } catch (Throwable $e) {
            throw new OdbcException($e->getMessage(), $e->getCode(), $e);
        }

        // "odbc_exec" can generate warning, if "set_error_handler" is not set, so we are checking it manually
        if ($stmt === false) {
            throw new OdbcException(odbc_errormsg($this->connection) . ' ' . odbc_error($this->connection));
        }

        $queryMetadata = $this->getQueryMetadata($query, $stmt);
        $queryMetadata->getColumns();
        return new OdbcQueryResult($query, $queryMetadata, $stmt);
    }
}

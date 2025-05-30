<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Keboola\DbExtractor\Configuration\HiveDatabaseConfig;
use Keboola\DbExtractor\Configuration\HiveDbNode;
use Keboola\DbExtractor\Exception\UserException;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

class HiveDsnFactory
{
    public const ODBC_DRIVER_NAME = 'Cloudera ODBC Driver for Apache Hive 64-bit';


    public function create(
        LoggerInterface $logger,
        HiveDatabaseConfig $dbConfig,
        HiveCertManager $certManager,
    ): string {
        $parameters = [];
        $parameters['Driver'] = self::ODBC_DRIVER_NAME;
        $parameters['Host'] = $dbConfig->getHost();
        $parameters['Port'] = $dbConfig->getPort();
        $parameters['Schema'] = $dbConfig->getDatabase();
        $parameters['UseNativeQuery'] = '1';
        $parameters['DefaultStringColumnLength'] = '16777216';
        $parameters['DefaultVarcharColumnLength'] = '16777216';
        $parameters['BinaryColumnLength'] = '16777216';
        $parameters['UseUnicodeSqlCharacterTypes'] = '1';
        $parameters['KeepAlive'] = '1';
        $parameters['RowsFetchedPerBlock'] = $dbConfig->getBatchSize();

        if ($dbConfig->getThriftTransport() !== null) {
            $parameters['ThriftTransport'] = $dbConfig->getThriftTransport();
        }

        if ($dbConfig->getHttpPath() !== null) {
            $parameters['HttpPath'] = $dbConfig->getHttpPath();
        }

        if ($dbConfig->isVerboseLoggingEnabled()) {
            (new Filesystem())->mkdir('/var/log/cloudera-odbc');
            $parameters['LogLevel'] = '6';
            $parameters['LogPath'] = '/var/log/cloudera-odbc/';
        }

        // Connect through
        if ($dbConfig->isConnectThroughEnabled()) {
            $realUser = (string) getenv('KBC_REALUSER');
            if ($realUser) {
                $logger->info(sprintf('Connect through is enabled, DelegationUID = "%s".', $realUser));
                $parameters['DelegationUID'] = $realUser;
            } else {
                throw new UserException(
                    'Connect through is enabled, but "KBC_REALUSER" environment variable is not set.',
                );
            }
        }

        // Auth type
        switch ($dbConfig->getAuthType()) {
            case HiveDbNode::AUTH_TYPE_PASSWORD:
                $parameters['AuthMech'] = 3;
                break;

            case HiveDbNode::AUTH_TYPE_KERBEROS:
                [$serviceName, $host, $realm] = self::parsePrincipal($dbConfig->getKrb5ServicePrincipal());
                $helper = new KerberosHelper($logger, $dbConfig);
                $helper->initKerberos();
                $parameters['AuthMech'] = 1;
                $parameters['KrbHostFQDN'] = $host;
                $parameters['KrbServiceName'] = $serviceName;
                $parameters['KrbRealm'] = $realm;
                break;

            default:
                throw new LogicException('Unexpected auth type.');
        }

        // SSL
        $parameters = array_merge($parameters, $certManager->getDsnParameters());

        // Generate DNS
        $dsn = '';
        foreach ($parameters as $key => $value) {
            $dsn .= "$key=$value;";
        }

        return $dsn;
    }

    public static function parsePrincipal(string $principal): array
    {
        if (!preg_match('~^([^/@]+)/([^/@]+)@(.+)$~', $principal, $m)) {
            throw new UserException(sprintf(
                'Unexpected format of the Kerberos principal. Expected "service/host@EXAMPLE.COM". Given "%s".',
                $principal,
            ));
        };

        $serviceName = $m[1];
        $host = $m[2];
        $realm = $m[3];
        return [$serviceName, $host, $realm];
    }
}

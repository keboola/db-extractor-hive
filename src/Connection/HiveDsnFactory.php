<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Keboola\DbExtractor\Exception\UserException;
use LogicException;
use Keboola\DbExtractor\Configuration\HiveDatabaseConfig;
use Keboola\DbExtractor\Configuration\HiveDbNode;
use Psr\Log\LoggerInterface;

class HiveDsnFactory
{
    public const ODBC_DRIVER_NAME = 'Cloudera ODBC Driver for Apache Hive 64-bit';


    public function create(
        LoggerInterface $logger,
        HiveDatabaseConfig $dbConfig,
        HiveCertManager $certManager
    ): string {

        $parameters = [];
        $parameters['Driver'] = self::ODBC_DRIVER_NAME;
        $parameters['Host'] = $dbConfig->getHost();
        $parameters['Port'] = $dbConfig->getPort();
        $parameters['Schema'] = $dbConfig->getDatabase();
        $parameters['UseNativeQuery'] = '1';

        // Auth type
        switch ($dbConfig->getAuthType()) {
            case HiveDbNode::AUTH_TYPE_PASSWORD:
                $parameters['AuthMech'] = 3;
                break;

            case HiveDbNode::AUTH_TYPE_KERBEROS:
                [$serviceName, $host] = self::parsePrincipal($dbConfig->getKrb5Principal());
                $helper = new KerberosHelper($logger, $dbConfig);
                $helper->initKerberos();
                $parameters['AuthMech'] = 1;
                $parameters['KrbHostFQDN'] = $host;
                $parameters['KrbServiceName'] = $serviceName;
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
                $principal
            ));
        };

        $serviceName = $m[1];
        $host = $m[2];
        return [$serviceName, $host];
    }
}

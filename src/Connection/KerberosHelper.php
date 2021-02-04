<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Keboola\DbExtractor\Configuration\HiveDatabaseConfig;
use Keboola\DbExtractor\Exception\UserException;
use Symfony\Component\Process\Process;

class KerberosHelper
{
    private LoggerInterface $logger;

    private HiveDatabaseConfig $dbConfig;

    public function __construct(LoggerInterface $logger, HiveDatabaseConfig $dbConfig)
    {
        $this->logger = $logger;
        $this->dbConfig = $dbConfig;
    }

    public function initKerberos(): void
    {
        if (self::isInitialized()) {
            // Already initialized, nothing to do
            return;
        }

        self::doInit();

        if (!self::isInitialized()) {
            throw new UserException('Kerberos authentication failed. Command "klist" failed.');
        }

        $this->logger->info('Kerberos authentication successful.');
    }

    public function cleanupKerberos(): void
    {
        $process = $this->runProcess(['kdestroy']);
        if ($process->getExitCode() !== 0) {
            throw new RuntimeException(sprintf(
                '. Command "kdestroy" failed %s; %s',
                $process->getOutput(),
                $process->getErrorOutput()
            ));
        }
    }

    private function doInit(): void
    {
        $this->logger->info(sprintf(
            'Starting Kerberos "kinit" authentication as "%s".',
            $this->dbConfig->getKrb5KinitPrincipal()
        ));

        $process = $this->runProcess([
            'kinit',
            '-kt',
            $this->writeKeytabFile(),
            $this->dbConfig->getKrb5KinitPrincipal(),
        ]);

        if ($process->getExitCode() !== 0) {
            throw new UserException(sprintf(
                'Kerberos authentication failed. Command "kinit" was not successful: %s; %s',
                $process->getOutput(),
                $process->getErrorOutput()
            ));
        }
    }

    private function isInitialized(): bool
    {
        $process = $this->runProcess(['klist']);
        return $process->getExitCode() === 0;
    }

    private function writeKeytabFile(): string
    {
        $krb5KeytabPath = (string) getenv('KRB5_KEYTAB');
        if (!$krb5KeytabPath) {
            throw new RuntimeException('KRB5_KEYTAB env variable must be set.');
        }
        file_put_contents($krb5KeytabPath, $this->dbConfig->getKrb5Keytab());
        return $krb5KeytabPath;
    }

    private function runProcess(array $command): Process
    {
        $krb5ConfPath = (string) getenv('KRB5_CONFIG');
        if (!$krb5ConfPath) {
            throw new RuntimeException('KRB5_CONFIG env variable must be set.');
        }

        file_put_contents($krb5ConfPath, $this->dbConfig->getKrb5Config());
        $process = new Process($command, null, ['KRB5_CONFIG' => $krb5ConfPath]);
        $process->run();
        return $process;
    }
}

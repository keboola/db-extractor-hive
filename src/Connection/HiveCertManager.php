<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Keboola\DbExtractor\Configuration\HiveDatabaseConfig;
use Keboola\DbExtractor\Configuration\HiveSslConnectionConfig;
use Keboola\DbExtractor\Configuration\HiveSslNode;
use Keboola\DbExtractor\Exception\UserException;
use Keboola\Temp\Temp;
use LogicException;
use SplFileInfo;
use Symfony\Component\Process\Process;

class HiveCertManager
{
    private ?HiveSslConnectionConfig $sslConfig;

    private Temp $temp;

    private ?string $pathToPemCaBundle = null;

    public function __construct(HiveDatabaseConfig $dbConfig)
    {
        $this->sslConfig = $dbConfig->hasSSLConnection() ? $dbConfig->getSslConnectionConfig() : null;
        $this->temp = new Temp();
    }

    public function __destruct()
    {
        // Clear all written certificates
        $this->temp->remove();
    }

    public function getDsnParameters(): array
    {
        $parameters = [];

        if ($this->sslConfig) {
            $parameters['SSL'] = 1;
            $parameters['AllowSelfSignedServerCert'] = $this->sslConfig->isVerifyServerCert() ? 0 : 1;
            $parameters['CAIssuedCertNamesMismatch'] = $this->sslConfig->isIgnoreCertificateCn() ? 1 : 0;
            $pemFilePath = $this->getPathToPemCaBundle();
            if ($pemFilePath) {
                $parameters['TrustedCerts'] = $pemFilePath;
            }
        }

        return $parameters;
    }

    protected function getPathToPemCaBundle(): ?string
    {
        if (!$this->sslConfig || !$this->sslConfig->hasCa()) {
            return null;
        }

        if (!$this->pathToPemCaBundle) {
            $this->pathToPemCaBundle = $this->generateCaPemBundle();
        }

        return $this->pathToPemCaBundle;
    }

    protected function generateCaPemBundle(): ?string
    {
        if (!$this->sslConfig || !$this->sslConfig->hasCa()) {
            return null;
        }

        if (empty($this->sslConfig->getCa())) {
            throw new UserException('CA certificate bundle is empty.');
        }

        $pemFile = $this->temp->createFile('ca-bundle.pem');

        switch ($this->sslConfig->getCaFileType()) {
            case HiveSslNode::CA_FILE_TYPE_PEM:
                file_put_contents($pemFile->getPathname(), $this->sslConfig->getCa());
                return $pemFile->getPathname();

            case HiveSslNode::CA_FILE_TYPE_JKS:
                $jksFile = $this->temp->createFile('ca-bundle.jks');
                file_put_contents($jksFile->getPathname(), $this->sslConfig->getCa());
                $this->convertCaJksToPem($jksFile, $pemFile);
                return $pemFile->getPathname();

            default:
                throw new LogicException(sprintf(
                    'Unexpected "caFileType" = "%s".',
                    $this->sslConfig->getCaFileType(),
                ));
        }
    }

    protected function convertCaJksToPem(SplFileInfo $jksFile, SplFileInfo $pemFile): void
    {
        // Convert JKS -> PEM, output is written to STDOUT
        $convertProcess = new Process([
            'bash',
            '-c',
            sprintf(
                'set -o pipefail; set -o errexit; '.
                'echo "" | keytool -list -storetype JKS -keystore "%s" -rfc',
                $jksFile->getPathname(),
            ),
        ]);

        if ($convertProcess->run() !== 0) {
            throw new UserException(sprintf(
                'Cannot convert CA certificate bundle from JKS to PEM format: %s %s',
                $convertProcess->getOutput(),
                $convertProcess->getErrorOutput(),
            ));
        }

        // Save PEM
        file_put_contents($pemFile->getPathname(), $convertProcess->getOutput());
        if ($pemFile->getSize() === 0) {
            throw new UserException('Cannot convert CA certificate bundle from JKS to PEM format.');
        }

        // Cleanup PEM
        $cleanupProcess = Process::fromShellCommandline(sprintf(
            'sed -ne "/-BEGIN CERTIFICATE-/,/-END CERTIFICATE-/p" -i "%s"',
            $jksFile->getPathname(),
        ));
        $cleanupProcess->mustRun();
    }
}

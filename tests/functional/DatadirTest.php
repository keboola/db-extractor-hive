<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\FunctionalTests;

use Keboola\DatadirTests\EnvVarProcessor;
use Keboola\DbExtractor\Tests\Traits\CleanupKerberosTrait;
use Throwable;
use Symfony\Component\Finder\Finder;
use Keboola\DatadirTests\DatadirTestCase;
use Keboola\DatadirTests\DatadirTestsProviderInterface;

class DatadirTest extends DatadirTestCase
{
    use CleanupKerberosTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupKerberos();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupKerberos();
    }

    protected function createEnvVarProcessor(): EnvVarProcessor
    {
        return new class extends EnvVarProcessor {
            public function getEnv(string $var): string
            {
                switch ($var) {
                    case 'HIVE_DB_KERBEROS_KEYTAB_ENCODED':
                        $keytabContent = (string) file_get_contents((string) getenv('HIVE_DB_KERBEROS_KEYTAB_PATH'));
                        return  (string) base64_encode($keytabContent);

                    case 'HIVE_DB_KERBEROS_KRB5_CONF':
                        return (string) file_get_contents((string) getenv('HIVE_DB_KERBEROS_KRB5_CONF_PATH'));

                    case 'HIVE_DB_KERBEROS_SSL_CERT_JKS':
                        $jksContent = (string) file_get_contents((string) getenv('HIVE_DB_KERBEROS_SSL_CERT_JKS_PATH'));
                        return  (string) base64_encode($jksContent);

                    case 'HIVE_DB_KERBEROS_SSL_CERT_PEM':
                        return  (string) file_get_contents((string) getenv('HIVE_DB_KERBEROS_SSL_CERT_PEM_PATH'));
                }

                return parent::getEnv($var);
            }
        };
    }


    /**
     * @return DatadirTestsProviderInterface[]
     */
    protected function getDataProviders(): array
    {
        return [
            new DatadirTestsProvider($this->getTestFileDir()),
        ];
    }

    public function assertDirectoryContentsSame(string $expected, string $actual): void
    {
        $this->prettifyAllManifests($actual);
        parent::assertDirectoryContentsSame($expected, $actual);
    }

    protected function prettifyAllManifests(string $actual): void
    {
        foreach ($this->findManifests($actual . '/tables') as $file) {
            $this->prettifyJsonFile((string) $file->getRealPath());
        }
    }

    protected function prettifyJsonFile(string $path): void
    {
        $json = (string) file_get_contents($path);
        try {
            file_put_contents($path, (string) json_encode(json_decode($json), JSON_PRETTY_PRINT));
        } catch (Throwable $e) {
            // If a problem occurs, preserve the original contents
            file_put_contents($path, $json);
        }
    }

    protected function findManifests(string $dir): Finder
    {
        $finder = new Finder();
        return $finder->files()->in($dir)->name(['~.*\.manifest~']);
    }
}

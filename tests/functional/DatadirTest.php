<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\FunctionalTests;

use Keboola\DatadirTests\DatadirTestCase;
use Keboola\DatadirTests\DatadirTestsProviderInterface;
use Keboola\DatadirTests\EnvVarProcessor;
use Keboola\DatadirTests\Exception\DatadirTestsException;
use Keboola\DbExtractor\Tests\Traits\CleanupKerberosTrait;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Throwable;

class DatadirTest extends DatadirTestCase
{
    use CleanupKerberosTrait;

    private ?string $kbcRealuser = null;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('KBC_COMPONENT_RUN_MODE=run');
        $this->cleanupKerberos();

        // Clear KBC_REALUSER env
        $this->kbcRealuser = null;

        // Test dir, eg. "/code/tests/functional/full-load-ok"
        $testProjectDir = $this->getTestFileDir() . '/' . $this->dataName();

        // Load setUp.php file - used to init database state
        $setUpPhpFile = $testProjectDir . '/setUp.php';
        if (file_exists($setUpPhpFile)) {
            // Get callback from file and check it
            $initCallback = require $setUpPhpFile;
            if (!is_callable($initCallback)) {
                throw new RuntimeException(sprintf('File "%s" must return callback!', $setUpPhpFile));
            }

            // Invoke callback
            $initCallback($this);
        }
    }

    public function setKbcRealUser(?string $realUser): void
    {
        $this->kbcRealuser = $realUser;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupKerberos();
    }


    protected function runScript(string $datadirPath, ?string $runId = null): Process
    {
        $fs = new Filesystem();

        $script = $this->getScript();
        if (!$fs->exists($script)) {
            throw new DatadirTestsException(sprintf(
                'Cannot open script file "%s"',
                $script,
            ));
        }

        $runCommand = [
            'php',
            $script,
        ];
        $runProcess = new Process($runCommand);
        $runProcess->setEnv([
            'KBC_DATADIR' => $datadirPath,
            'KBC_REALUSER' => (String) $this->kbcRealuser,
        ]);
        $runProcess->setTimeout(0.0);
        $runProcess->run();
        return $runProcess;
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

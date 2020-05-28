<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\FunctionalTests;

use Keboola\DatadirTests\AbstractDatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;
use Keboola\DatadirTests\DatadirTestsProviderInterface;
use Symfony\Component\Process\Process;

class DatadirTest extends AbstractDatadirTestCase
{
    /**
     * @return DatadirTestsProviderInterface[]
     */
    protected function getDataProviders(): array
    {
        return [
            new DatadirTestsProvider($this->getTestFileDir()),
        ];
    }

    /**
     * @dataProvider provideDatadirSpecifications
     */
    public function testDatadir(DatadirTestSpecificationInterface $specification): void
    {
        $tempDatadir = $this->getTempDatadir($specification);

        // Replace environment variables in config.json
        $configPath = $tempDatadir->getTmpFolder() . '/config.json';
        if (file_exists($configPath)) {
            $config = file_get_contents($configPath);
            $config = preg_replace_callback('~\$\{([^{}]+)\}~', fn($m) => getenv($m[1]), $config);
            file_put_contents($configPath, $config);
        }

        $process = $this->runScript($tempDatadir->getTmpFolder());

        $this->assertMatchesSpecification($specification, $process, $tempDatadir->getTmpFolder());
    }

    protected function assertMatchesSpecification(
        DatadirTestSpecificationInterface $specification,
        Process $runProcess,
        string $tempDatadir
    ): void {
        if ($specification->getExpectedReturnCode() !== null) {
            $this->assertProcessReturnCode($specification->getExpectedReturnCode(), $runProcess);
        } else {
            $this->assertNotSame(0, $runProcess->getExitCode(), 'Exit code should have been non-zero');
        }
        if ($specification->getExpectedStdout() !== null) {
            // Match format, not exact same
            $this->assertStringMatchesFormat(
                trim($specification->getExpectedStdout()),
                trim($runProcess->getOutput()),
                'Failed asserting stdout output'
            );
        }
        if ($specification->getExpectedStderr() !== null) {
            // Match format, not exact same
            $this->assertStringMatchesFormat(
                trim($specification->getExpectedStderr()),
                trim($runProcess->getErrorOutput()),
                'Failed asserting stderr output'
            );
        }
        if ($specification->getExpectedOutDirectory() !== null) {
            $this->assertDirectoryContentsSame(
                $specification->getExpectedOutDirectory(),
                $tempDatadir . '/out'
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests\Traits;

use Keboola\Component\JsonHelper;
use Keboola\DbExtractor\HiveApplication;
use Psr\Log\LoggerInterface;

trait CreateApplicationTrait
{
    protected ?string $dataDir;

    public function createApplication(
        array $config,
        LoggerInterface $logger,
        ?string $dataFolder = null,
    ): HiveApplication {
        $dataFolder = $dataFolder ?? $this->dataDir ?? '/data';

        JsonHelper::writeFile($dataFolder . '/config.json', $config);

        return new HiveApplication($logger);
    }
}

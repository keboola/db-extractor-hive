<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests\Traits;

use Keboola\Component\JsonHelper;
use Keboola\DbExtractor\HiveApplication;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;

trait CreateApplicationTrait
{
    protected ?string $dataDir;

    public function createApplication(
        array $config,
        LoggerInterface $logger,
        ?string $dataFolder = null,
        ?HandlerInterface $logHandler = null
    ): HiveApplication {
        $dataFolder = $dataFolder ?? $this->dataDir ?? '/data';
        $handler = new TestHandler();

        if ($logHandler) {
            $logger->pushHandler($handler);
        }

        JsonHelper::writeFile($dataFolder . '/config.json', $config);

        return new HiveApplication($logger);
    }
}

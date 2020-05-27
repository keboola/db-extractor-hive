<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests\Traits;

use Keboola\Component\Logger;
use Keboola\DbExtractor\HiveApplication;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\TestHandler;

trait CreateApplicationTrait
{
    protected ?string $dataDir;

    public function createApplication(
        array $config,
        ?string $dataFolder = null,
        array $state = [],
        ?HandlerInterface $logHandler = null
    ): HiveApplication {
        $dataFolder = $dataFolder ?? $this->dataDir ?? '/data';
        $handler = new TestHandler();
        $logger = new Logger();

        if ($logHandler) {
            $logger->pushHandler($handler);
        }

        return new HiveApplication($config, $logger, $state, $dataFolder);
    }
}

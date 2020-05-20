<?php

declare(strict_types=1);

namespace Keboola\DbExtractor;

use Keboola\DbExtractor\Configuration\HiveActionConfigRowDefinition;
use Keboola\DbExtractor\Configuration\HiveConfigRowDefinition;
use Keboola\DbExtractor\Exception\ApplicationException;
use Keboola\DbExtractorConfig\Config;
use Psr\Log\LoggerInterface;

class HiveApplication extends Application
{
    public function __construct(array $config, LoggerInterface $logger, array $state = [], string $dataDir = '/data/')
    {
        $config['parameters']['data_dir'] = $dataDir;
        $config['parameters']['extractor_class'] = 'Hive';

        parent::__construct($config, $logger, $state);
    }

    protected function buildConfig(array $config): void
    {
        if ($this->isRowConfiguration($config)) {
            $this->config = $this['action'] === 'run' ?
                new Config($config, new HiveConfigRowDefinition()) :
                new Config($config, new HiveActionConfigRowDefinition());
        } else {
            throw new ApplicationException('Old config format is not supported. Please, use row configuration.');
        }
    }
}

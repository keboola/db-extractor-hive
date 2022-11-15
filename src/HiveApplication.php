<?php

declare(strict_types=1);

namespace Keboola\DbExtractor;

use Keboola\DbExtractor\Configuration\HiveActionConfigRowDefinition;
use Keboola\DbExtractor\Configuration\HiveConfigRowDefinition;
use Keboola\DbExtractor\Configuration\HiveDbNode;
use Keboola\DbExtractor\Exception\ApplicationException;
use Keboola\DbExtractorConfig\Config;

class HiveApplication extends Application
{
    protected function loadConfig(): void
    {
        $config = $this->getRawConfig();
        $action = $config['action'] ?? 'run';

        $config['parameters']['extractor_class'] = 'Hive';
        $config['parameters']['data_dir'] = $this->getDataDir();

        $dbNode = new HiveDbNode();
        if ($this->isRowConfiguration($config)) {
            $this->config = $action === 'run' ?
                new Config($config, new HiveConfigRowDefinition($dbNode)) :
                new Config($config, new HiveActionConfigRowDefinition($dbNode));
        } else {
            throw new ApplicationException('Old config format is not supported. Please, use row configuration.');
        }
    }
}

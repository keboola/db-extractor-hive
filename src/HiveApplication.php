<?php

declare(strict_types=1);

namespace Keboola\DbExtractor;

use Keboola\DbExtractor\Configuration\HiveDbNode;
use Keboola\DbExtractor\Configuration\HiveSslNode;
use Keboola\DbExtractor\Exception\ApplicationException;
use Keboola\DbExtractorConfig\Config;
use Keboola\DbExtractorConfig\Configuration\ActionConfigRowDefinition;
use Keboola\DbExtractorConfig\Configuration\ConfigRowDefinition;
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
        $dbNode = new HiveDbNode();
        if ($this->isRowConfiguration($config)) {
            $this->config = $this['action'] === 'run' ?
                new Config($config, new ConfigRowDefinition($dbNode)) :
                new Config($config, new ActionConfigRowDefinition($dbNode));
        } else {
            throw new ApplicationException('Old config format is not supported. Please, use row configuration.');
        }
    }
}

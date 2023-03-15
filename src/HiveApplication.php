<?php

declare(strict_types=1);

namespace Keboola\DbExtractor;

use Keboola\DbExtractor\Configuration\HiveActionConfigRowDefinition;
use Keboola\DbExtractor\Configuration\HiveConfigRowDefinition;
use Keboola\DbExtractor\Configuration\HiveDbNode;
use Keboola\DbExtractor\Exception\ApplicationException;
use Keboola\DbExtractorConfig\Config;
use Symfony\Component\Filesystem\Filesystem;

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

    protected function run(): void
    {
        parent::run();

        if ($this->getConfig()->getParameters()['db']['verboseLogging']) {
            $this->writeVerboseLogsToArtifacts();
        }
    }

    protected function writeVerboseLogsToArtifacts(): void
    {
        $fs = new Filesystem();
        $artifactsPath = sprintf('%s/artifacts/out/current/', $this->getDataDir());
        $fs->mkdir($artifactsPath);
        $fs->mirror('/var/log/cloudera-odbc/', $artifactsPath);
    }
}

<?php

declare(strict_types=1);

namespace Keboola\DbExtractor;

use Keboola\DbExtractorLogger\Logger;

class HiveApplication extends Application
{
    public function __construct(array $config, Logger $logger, array $state = [], string $dataDir = '/data/')
    {
        $config['parameters']['data_dir'] = $dataDir;
        $config['parameters']['extractor_class'] = 'Hive';

        parent::__construct($config, $logger, $state);
    }
}

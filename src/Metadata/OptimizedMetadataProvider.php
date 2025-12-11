<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Metadata;

use Keboola\DbExtractor\Adapter\Metadata\MetadataProvider;
use Keboola\DbExtractor\TableResultFormat\Metadata\ValueObject\Table;
use Keboola\DbExtractor\TableResultFormat\Metadata\ValueObject\TableCollection;
use Keboola\DbExtractorConfig\Configuration\ValueObject\InputTable;

/**
 * Metadata provider wrapper that optimizes for sync actions by disabling column loading
 */
class OptimizedMetadataProvider implements MetadataProvider
{
    private MetadataProvider $innerProvider;
    private bool $isSyncAction;

    public function __construct(MetadataProvider $innerProvider, bool $isSyncAction)
    {
        $this->innerProvider = $innerProvider;
        $this->isSyncAction = $isSyncAction;
    }

    public function listTables(array $whitelist = [], bool $loadColumns = true): TableCollection
    {
        // For sync actions, disable column loading by default to avoid timeouts
        if ($this->isSyncAction) {
            $loadColumns = false;
        }

        return $this->innerProvider->listTables($whitelist, $loadColumns);
    }

    public function getTable(InputTable $table): Table
    {
        return $this->innerProvider->getTable($table);
    }
}

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
    private bool $hasExplicitListColumns;

    public function __construct(
        MetadataProvider $innerProvider,
        bool $isSyncAction,
        bool $hasExplicitListColumns,
    ) {
        $this->innerProvider = $innerProvider;
        $this->isSyncAction = $isSyncAction;
        $this->hasExplicitListColumns = $hasExplicitListColumns;
    }

    public function listTables(array $whitelist = [], bool $loadColumns = true): TableCollection
    {
        // For sync actions, disable column loading by default to avoid timeouts,
        // but respect any explicit listColumns setting from the config.
        if ($this->isSyncAction && !$this->hasExplicitListColumns) {
            $loadColumns = false;
        }

        return $this->innerProvider->listTables($whitelist, $loadColumns);
    }

    public function getTable(InputTable $table): Table
    {
        return $this->innerProvider->getTable($table);
    }
}

<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Configuration;

use Keboola\DbExtractorConfig\Configuration\ActionConfigRowDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class HiveActionConfigRowDefinition extends ActionConfigRowDefinition
{
    protected function getRootDefinition(TreeBuilder $treeBuilder): ArrayNodeDefinition
    {
        $rootNode = parent::getRootDefinition($treeBuilder);
        $rootNode
            ->beforeNormalization()
            ->always(function (array $root): array {
                return ConfigUtils::mergeParameters($root);
            });

        return $rootNode;
    }
}

<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Configuration;

use Keboola\DbExtractorConfig\Configuration\NodeDefinition\SslNode;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class HiveSslNode extends SslNode
{
    public function init(NodeBuilder $nodeBuilder): void
    {
        $this->addEnabledNode($nodeBuilder);
        $this->addVerifyServerCertNode($nodeBuilder);
    }

    protected function addVerifyServerCertNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->booleanNode('verifyServerCert')->defaultTrue();
    }
}

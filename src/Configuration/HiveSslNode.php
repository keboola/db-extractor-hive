<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Configuration;

use Keboola\DbExtractorConfig\Configuration\NodeDefinition\SslNode;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class HiveSslNode extends SslNode
{
    public const CA_FILE_TYPE_PEM = 'pem';
    public const CA_FILE_TYPE_JKS = 'jks';

    public function init(NodeBuilder $nodeBuilder): void
    {
        $this->addEnabledNode($nodeBuilder);
        $this->addCaNode($nodeBuilder);
        $this->addCaFileTypeNode($nodeBuilder);
        $this->addVerifyServerCertNode($nodeBuilder);
        $this->addIgnoreCertificateCn($nodeBuilder);
        $this->beforeNormalization()->always(function (array $v): array {
            // CA can be encrypted, because JKS format may contain private keys.
            if (isset($v['#ca'])) {
                $v['ca'] = $v['#ca'];
                unset($v['#ca']);
            }

            return $v;
        });
        $this->validate()->always(function (array $v): array {

            // Base64 decode
            $caFileType = $v['caFileType'] ?? self::CA_FILE_TYPE_PEM;
            if ($caFileType === HiveSslNode::CA_FILE_TYPE_JKS && isset($v['ca'])) {
                $v['ca'] = ConfigUtils::base64Decode(
                    $v['ca'],
                    'db.ssl.ca'
                );
            }

            return $v;
        });
    }

    protected function addCaFileTypeNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder
            ->enumNode('caFileType')
            ->values([self::CA_FILE_TYPE_PEM, self::CA_FILE_TYPE_JKS])
            ->defaultValue(self::CA_FILE_TYPE_PEM);
    }

    protected function addVerifyServerCertNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->booleanNode('verifyServerCert')->defaultTrue();
    }
}

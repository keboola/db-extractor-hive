<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Configuration;

use Keboola\DbExtractorConfig\Configuration\NodeDefinition\DbNode;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class HiveDbNode extends DbNode
{
    public const AUTH_TYPE_PASSWORD = 'password';
    public const AUTH_TYPE_KERBEROS = 'kerberos';

    public function __construct()
    {
        parent::__construct(null, new HiveSslNode(), null);
    }

    protected function init(NodeBuilder $builder): void
    {
        $this->addAuthType($builder);
        $this->addHostNode($builder);
        $this->addPortNode($builder);
        $this->addDatabaseNode($builder);
        $this->addUserNode($builder);
        $this->addPasswordNode($builder);
        $this->addKerberosNode($builder);
        $this->addSshNode($builder);
        $this->addSslNode($builder);
        $this->addConnectThrough($builder);

        $this->validate()->always(function (array $v): array {
            // User and password keys are required for the authType = password
            if ($v['authType'] === self::AUTH_TYPE_PASSWORD) {
                if (empty($v['user']) || empty($v['#password'])) {
                    throw new InvalidConfigurationException(sprintf(
                        'Keys "db.user" and "db.#password" must be configured for the "authType" = "%s".',
                        self::AUTH_TYPE_PASSWORD
                    ));
                }

                if (!empty($v['kerberos'])) {
                    throw new InvalidConfigurationException(sprintf(
                        'Key "db.kerberos" is not expected for "authType" = "%s".',
                        self::AUTH_TYPE_PASSWORD
                    ));
                }
            }

            // Kerberos key is required for the authType = kerberos
            if ($v['authType'] === self::AUTH_TYPE_KERBEROS) {
                if (empty($v['kerberos'])) {
                    throw new InvalidConfigurationException(sprintf(
                        'Key "db.kerberos" must be configured for the "authType" = "%s".',
                        self::AUTH_TYPE_KERBEROS
                    ));
                }

                if (!empty($v['user']) || !empty($v['#password'])) {
                    throw new InvalidConfigurationException(sprintf(
                        'Keys "db.user" and "db.#password" are not expected for "authType" = "%s".',
                        self::AUTH_TYPE_KERBEROS
                    ));
                }
            }

            // Base64 decode keytab
            if (isset($v['kerberos']['#keytab'])) {
                $v['kerberos']['#keytab'] = ConfigUtils::base64Decode(
                    $v['kerberos']['#keytab'],
                    'db.kerberos.#keytab'
                );
            }

            return $v;
        });
    }

    protected function addHostNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('host')->isRequired();
    }

    protected function addPortNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('port')->defaultValue(10000);
    }

    protected function addDatabaseNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('database')->cannotBeEmpty()->isRequired();
    }

    protected function addAuthType(NodeBuilder $builder): void
    {
        $builder
            ->enumNode('authType')
            ->values([
                self::AUTH_TYPE_PASSWORD,
                self::AUTH_TYPE_KERBEROS,
            ])
            ->defaultValue(self::AUTH_TYPE_PASSWORD);
    }

    protected function addUserNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('user');
    }

    protected function addPasswordNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('#password');
    }

    protected function addKerberosNode(NodeBuilder $builder): void
    {
        $builder
            ->arrayNode('kerberos')
            ->children()
                ->scalarNode('principal')->isRequired()->end()
                ->scalarNode('config')->isRequired()->end()
                ->scalarNode('#keytab')->isRequired()->end();
    }

    protected function addConnectThrough(NodeBuilder $builder): void
    {
        $builder->booleanNode('connectThrough')->defaultFalse();
    }
}

<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests;

use Keboola\CommonExceptions\UserExceptionInterface;
use Keboola\DbExtractor\Tests\Traits\CreateApplicationTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class IncrementalFetchTest extends TestCase
{
    use CreateApplicationTrait;

    protected function setUp(): void
    {
        $this->dataDir = '/data';
        putenv('KBC_DATADIR='. $this->dataDir);
        parent::setUp();
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testUserError(string $expectedMsg, array $parameters): void
    {
        $config = $this->getConfig();
        $config['parameters'] = $parameters + $config['parameters'];

        $this->expectException(UserExceptionInterface::class);
        $this->expectExceptionMessage($expectedMsg);
        $this->createApplication($config, new NullLogger())->execute();
    }

    public function invalidConfigProvider(): array
    {
        return [
            'missing column' => [
                'Incremental fetching column "foo" not found.',
                [
                    'incrementalFetchingColumn' => 'foo',
                ],
            ],
            'unexpected column type' => [
                'Unexpected type "VARCHAR" of incremental fetching column "string_col".',
                [
                    'incrementalFetchingColumn' => 'string_col',
                ],
            ],
            'missing table' => [
                'Table with name "bar" and schema "default" not found.',
                [
                    'incrementalFetchingColumn' => 'double_col',
                    'table' => [
                        'tableName' => 'bar',
                        'schema' => 'default',
                    ],
                ],
            ],
        ];
    }

    private function getConfig(): array
    {
        return [
           'parameters' => [
               'db' => [
                   'host' => getenv('HIVE_DB_LDAP_HOST'),
                   'port' => (int) getenv('HIVE_DB_LDAP_PORT'),
                   'database' => getenv('HIVE_DB_LDAP_DATABASE'),
                   'user' => getenv('HIVE_DB_LDAP_USER'),
                   '#password' => getenv('HIVE_DB_LDAP_PASSWORD'),
               ],
               'action' => 'run',
               'enabled'=> true,
               'id' => 0,
               'name' => 'incremental',
               'table' => [
                   'tableName' => 'incremental',
                   'schema' => 'default',
               ],
               'outputTable' => 'in.c-main.incremental',
               'primaryKey' => ['id'],
               'incremental' => true,
           ],
        ];
    }
}

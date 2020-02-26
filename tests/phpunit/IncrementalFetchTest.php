<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests;

use Keboola\DbExtractor\Exception\UserException;
use Keboola\DbExtractor\Tests\Traits\CreateApplicationTrait;
use PHPUnit\Framework\TestCase;

class IncrementalFetchTest extends TestCase
{
    use CreateApplicationTrait;

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testUserError(string $expectedMsg, array $parameters): void
    {
        $config = $this->getConfig();
        $config['parameters'] = $parameters + $config['parameters'];
        $this->expectException(UserException::class);
        $this->expectExceptionMessage($expectedMsg);
        $this->createApplication($config)->run();
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
                'Unexpected type "STRING" of incremental fetching column "string_col". ' .
                'Expected types: INTEGER, NUMERIC, FLOAT, TIMESTAMP, DATE.',
                [
                    'incrementalFetchingColumn' => 'string_col',
                ],
            ],
            'missing table' => [
                'Table "bar" not found.',
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
                   'host' => getenv('HIVE_DB_HOST'),
                   'port' => (int) getenv('HIVE_DB_PORT'),
                   'database' => getenv('HIVE_DB_DATABASE'),
                   'user' => getenv('HIVE_DB_USER'),
                   '#password' => getenv('HIVE_DB_PASSWORD'),
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

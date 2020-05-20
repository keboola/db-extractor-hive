<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests;

use Keboola\DbExtractor\Tests\Traits\CreateApplicationTrait;
use PHPUnit\Framework\TestCase;

class GetTablesTest extends TestCase
{
    use CreateApplicationTrait;

    public function testGetTablesAction(): void
    {
        $config = $this->getConfig();
        $config['action'] = 'getTables';
        $result = $this->createApplication($config)->run();
        $expected = $this->getExpectedMetadata();
        $this->assertEquals($expected, $result);
    }

    private function getExpectedMetadata(): array
    {
        // Hive DB doesn't support PK, FK, NOT NULL,...
        // See: https://issues.apache.org/jira/browse/HIVE-6905
        return [
            'status' => 'success',
            'tables' => [
                [
                    'name' => 'incremental',
                    'schema' => 'default',
                    'columns' => [
                        [
                            'name' => 'id',
                            'type' => 'INTEGER',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'timestamp_col',
                            'type' => 'TIMESTAMP',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'date_col',
                            'type' => 'DATE',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'float_col',
                            'type' => 'FLOAT',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'double_col',
                            'type' => 'FLOAT',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'string_col',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                    ],
                ],
                [
                    'name' => 'internal',
                    'schema' => 'default',
                    'columns' => [
                        [
                            'name' => 'product_name',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'price',
                            'type' => 'FLOAT',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'comment',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                    ],
                ],
                [
                    'name' => 'sales',
                    'schema' => 'default',
                    'columns' => [
                        [
                            'name' => 'usergender',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'usercity',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'usersentiment',
                            'type' => 'INTEGER',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'zipcode',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'sku',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'createdat',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'category',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'price',
                            'type' => 'FLOAT',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'county',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'countycode',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'userstate',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'categorygroup',
                            'type' => 'STRING',
                            'primaryKey' => false,
                        ],
                    ],
                ],
                [
                    'name' => 'special_types',
                    'schema' => 'default',
                    'columns' => [
                        [
                            'name' => 'id',
                            'type' => 'INTEGER',
                            'primaryKey' => false,

                        ],
                        [
                            'name' => 'bin',
                            'type' => 'STRING',
                            'primaryKey' => false,

                        ],
                        [
                            'name' => 'map',
                            'type' => 'STRING',
                            'primaryKey' => false,

                        ],
                        [
                            'name' => 'array',
                            'type' => 'STRING',
                            'primaryKey' => false,

                        ],
                        [
                            'name' => 'union',
                            'type' => 'STRING',
                            'primaryKey' => false,

                        ],
                        [
                            'name' => 'struct',
                            'type' => 'STRING',
                            'primaryKey' => false,

                        ],
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
            ],
        ];
    }
}

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
                            'sanitizedName' => 'id',
                            'type' => 'INTEGER',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'timestamp_col',
                            'sanitizedName' => 'timestamp_col',
                            'type' => 'TIMESTAMP',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'date_col',
                            'sanitizedName' => 'date_col',
                            'type' => 'DATE',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'float_col',
                            'sanitizedName' => 'float_col',
                            'type' => 'FLOAT',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'double_col',
                            'sanitizedName' => 'double_col',
                            'type' => 'FLOAT',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'string_col',
                            'sanitizedName' => 'string_col',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                    ],
                ],
                [
                    'name' => 'internal',
                    'schema' => 'default',
                    'columns' => [
                        [
                            'name' => 'product_name',
                            'sanitizedName' => 'product_name',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'price',
                            'sanitizedName' => 'price',
                            'type' => 'FLOAT',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'comment',
                            'sanitizedName' => 'comment',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                    ],
                ],
                [
                    'name' => 'sales',
                    'schema' => 'default',
                    'columns' => [
                        [
                            'name' => 'usergender',
                            'sanitizedName' => 'usergender',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'usercity',
                            'sanitizedName' => 'usercity',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'usersentiment',
                            'sanitizedName' => 'usersentiment',
                            'type' => 'INTEGER',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'zipcode',
                            'sanitizedName' => 'zipcode',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'sku',
                            'sanitizedName' => 'sku',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'createdat',
                            'sanitizedName' => 'createdat',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'category',
                            'sanitizedName' => 'category',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'price',
                            'sanitizedName' => 'price',
                            'type' => 'FLOAT',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'county',
                            'sanitizedName' => 'county',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'countycode',
                            'sanitizedName' => 'countycode',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'userstate',
                            'sanitizedName' => 'userstate',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
                        ],
                        [
                            'name' => 'categorygroup',
                            'sanitizedName' => 'categorygroup',
                            'type' => 'STRING',
                            'primaryKey' => false,
                            'uniqueKey' => false,
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

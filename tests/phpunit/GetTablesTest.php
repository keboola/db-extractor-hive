<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests;

use Keboola\DbExtractor\Tests\Traits\CreateApplicationTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GetTablesTest extends TestCase
{
    use CreateApplicationTrait;

    protected function setUp(): void
    {
        $this->dataDir = '/data';
        putenv('KBC_DATADIR='. $this->dataDir);
        parent::setUp();
    }

    public function testGetTablesAction(): void
    {
        $config = $this->getConfig();
        $config['action'] = 'getTables';

        ob_start();
        $this->createApplication($config, new NullLogger())->execute();
        $result = json_decode((string) ob_get_contents(), true);
        ob_end_clean();

        $expected = $this->getExpectedMetadataFull();

        $this->assertEquals($expected, $result);
    }

    public function testGetTablesActionOnlyTables(): void
    {
        $config = $this->getConfig();
        $config['action'] = 'getTables';
        $config['parameters']['tableListFilter'] = [];
        $config['parameters']['tableListFilter']['listColumns'] = false;

        ob_start();
        $this->createApplication($config, new NullLogger())->execute();
        $result = json_decode((string) ob_get_contents(), true);
        ob_end_clean();

        $expected = $this->getExpectedMetadataOnlyTables();
        $this->assertEquals($expected, $result);
    }

    public function testGetTablesActionColumnsForOneTable(): void
    {
        $config = $this->getConfig();
        $config['action'] = 'getTables';
        $config['parameters']['tableListFilter'] = [];
        $config['parameters']['tableListFilter']['listColumns'] = true;
        $config['parameters']['tableListFilter']['tablesToList'] = [
            [
                'tableName' => 'internal',
                'schema' => 'default',
            ],
        ];
        ob_start();
        $this->createApplication($config, new NullLogger())->execute();
        $result = json_decode((string) ob_get_contents(), true);
        ob_end_clean();

        $expected = $this->getExpectedMetadataOneTable();
        $this->assertEquals($expected, $result);
    }


    private function getExpectedMetadataFull(): array
    {
        // Hive DB doesn't support PK, FK, NOT NULL,...
        // See: https://issues.apache.org/jira/browse/HIVE-6905
        return [
            'status' => 'success',
            'tables' => [
                [
                    'name' => 'chars',
                    'schema' => 'default',
                    'columns' => [
                        [
                            'name' => 'text1',
                            'type' => 'VARCHAR',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'text2',
                            'type' => 'VARCHAR',
                            'primaryKey' => false,
                        ],
                    ],
                ],
                [
                    'name' => 'incremental',
                    'schema' => 'default',
                    'columns' => [
                        [
                            'name' => 'id',
                            'type' => 'INT',
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
                            'type' => 'DOUBLE',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'string_col',
                            'type' => 'VARCHAR',
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
                            'type' => 'DOUBLE',
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
                            'type' => 'VARCHAR',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'usercity',
                            'type' => 'VARCHAR',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'usersentiment',
                            'type' => 'INT',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'zipcode',
                            'type' => 'VARCHAR',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'sku',
                            'type' => 'VARCHAR',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'createdat',
                            'type' => 'VARCHAR',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'category',
                            'type' => 'VARCHAR',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'price',
                            'type' => 'FLOAT',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'county',
                            'type' => 'VARCHAR',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'countycode',
                            'type' => 'VARCHAR',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'userstate',
                            'type' => 'VARCHAR',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'categorygroup',
                            'type' => 'VARCHAR',
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
                            'type' => 'INT',
                            'primaryKey' => false,

                        ],
                        [
                            'name' => 'bin',
                            'type' => 'BINARY',
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

    private function getExpectedMetadataOnlyTables(): array
    {
        return [
            'status' => 'success',
            'tables' => [
                [
                    'name' => 'chars',
                    'schema' => 'default',
                ],
                [
                    'name' => 'incremental',
                    'schema' => 'default',
                ],
                [
                    'name' => 'internal',
                    'schema' => 'default',
                ],
                [
                    'name' => 'sales',
                    'schema' => 'default',
                ],
                [
                    'name' => 'special_types',
                    'schema' => 'default',
                ],
            ],
        ];
    }

    private function getExpectedMetadataOneTable(): array
    {
        return [
            'status' => 'success',
            'tables' => [
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
                            'type' => 'DOUBLE',
                            'primaryKey' => false,
                        ],
                        [
                            'name' => 'comment',
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
                    'host' => getenv('HIVE_DB_LDAP_HOST'),
                    'port' => (int) getenv('HIVE_DB_LDAP_PORT'),
                    'database' => getenv('HIVE_DB_LDAP_DATABASE'),
                    'user' => getenv('HIVE_DB_LDAP_USER'),
                    '#password' => getenv('HIVE_DB_LDAP_PASSWORD'),
                ],
            ],
        ];
    }
}

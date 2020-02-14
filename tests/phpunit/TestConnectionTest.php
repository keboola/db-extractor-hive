<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests;

use Keboola\DbExtractor\Exception\UserException;
use Keboola\DbExtractor\Tests\Traits\CreateApplicationTrait;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class TestConnectionTest extends TestCase
{
    use CreateApplicationTrait;

    protected function tearDown(): void
    {
        # Close SSH tunnel if created
        $process = new Process(['sh', '-c', 'pgrep ssh | xargs -r kill']);
        $process->mustRun();
    }

    /**
     * @dataProvider validConfigProvider
     */
    public function testSuccessfullyConnection(array $config): void
    {
        $config['action'] = 'testConnection';
        $app = $this->createApplication($config);
        $result = $app->run();
        $this->assertEquals(['status' => 'success'], $result);
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testFailedConnection(string $expectedExceptionMessage, array $config): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $config['action'] = 'testConnection';
        $app = $this->createApplication($config);
        $app->run();
    }

    public function validConfigProvider(): array
    {
        return [
            'valid-config' => [
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_HOST'),
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'valid-config-ssh' => [
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('SSH_DB_HOST'),
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                            'ssh' => [
                                'enabled' => true,
                                'sshHost' => getenv('SSH_HOST'),
                                'sshPort' => (int) getenv('SSH_PORT'),
                                'user' => getenv('SSH_USER'),
                                'keys' => [
                                    'public' => getenv('SSH_PUBLIC_KEY'),
                                    '#private'=> getenv('SSH_PRIVATE_KEY'),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function invalidConfigProvider(): array
    {
        return [
            'invalid-host' => [
                "Connection failed: 'Error connecting to DB: [Cloudera][Hardy] (34) Error from server:" .
                ' Could not resolve host for client socket.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'invalid-host.local',
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-port' => [
                'Connection failed: \'Error connecting to DB:' .
                ' [Cloudera][Hardy] (34) Error from server: connect() failed: Connection refused.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_HOST'),
                            'port' => 12345,
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-database' => [
                'Connection failed: \'Error connecting to DB:' .
                ' [Cloudera][Hardy] (68) Error returned trying to set notfound as the initial database',
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_HOST'),
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => 'notFound',
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-user' => [
                'Connection failed: \'Error connecting to DB:' .
                ' [Cloudera][ThriftExtension] (2) Error occured during authentication.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_HOST'),
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => 'invalidUser',
                            '#password' => getenv('HIVE_DB_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-password' => [
                'Connection failed: \'Error connecting to DB:' .
                ' [Cloudera][ThriftExtension] (2) Error occured during authentication.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_HOST'),
                            'port' => (int) getenv('HIVE_DB_PORT'),
                            'database' => getenv('HIVE_DB_DATABASE'),
                            'user' => getenv('HIVE_DB_USER'),
                            '#password' => 'invalidPassword',
                        ],
                    ],
                ],
            ],
        ];
    }
}

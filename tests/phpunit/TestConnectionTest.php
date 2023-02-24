<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests;

use Keboola\DbExtractor\Adapter\Exception\UserException;
use Keboola\DbExtractor\Tests\Traits\CreateApplicationTrait;
use Keboola\DbExtractor\Tests\Traits\SshKeysTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;

class TestConnectionTest extends TestCase
{
    use CreateApplicationTrait;
    use SshKeysTrait;

    protected function setUp(): void
    {
        $this->dataDir = '/data';
        putenv('KBC_DATADIR='. $this->dataDir);
        parent::setUp();
    }

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

        ob_start();
        $this->createApplication($config, new NullLogger())->execute();
        $result = json_decode((string) ob_get_contents(), true);
        ob_end_clean();

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
        $this->createApplication($config, new NullLogger())->execute();
    }

    public function validConfigProvider(): array
    {
        return [
            'valid-config' => [
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_LDAP_HOST'),
                            'port' => (int) getenv('HIVE_DB_LDAP_PORT'),
                            'database' => getenv('HIVE_DB_LDAP_DATABASE'),
                            'user' => getenv('HIVE_DB_LDAP_USER'),
                            '#password' => getenv('HIVE_DB_LDAP_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'valid-config-ssh' => [
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('SSH_DB_HOST'),
                            'port' => (int) getenv('HIVE_DB_LDAP_PORT'),
                            'database' => getenv('HIVE_DB_LDAP_DATABASE'),
                            'user' => getenv('HIVE_DB_LDAP_USER'),
                            '#password' => getenv('HIVE_DB_LDAP_PASSWORD'),
                            'ssh' => [
                                'enabled' => true,
                                'sshHost' => getenv('SSH_HOST'),
                                'sshPort' => (int) getenv('SSH_PORT'),
                                'user' => getenv('SSH_USER'),
                                'keys' => [
                                    'public' => $this->getPublicKey(),
                                    '#private'=> $this->getPrivateKey(),
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
                'Error connecting to DB: [Cloudera][DriverSupport] (1110) ' .
                'Unexpected response received from server. ' .
                'Please ensure the server host and port specified for the connection are correct ' .
                'and confirm if SSL should be enabled for the connection.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'invalid-host.local',
                            'port' => (int) getenv('HIVE_DB_LDAP_PORT'),
                            'database' => getenv('HIVE_DB_LDAP_DATABASE'),
                            'user' => getenv('HIVE_DB_LDAP_USER'),
                            '#password' => getenv('HIVE_DB_LDAP_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-port' => [
                'Error connecting to DB: [Cloudera][DriverSupport] (1110) ' .
                'Unexpected response received from server. ' .
                'Please ensure the server host and port specified for the connection are correct ' .
                'and confirm if SSL should be enabled for the connection.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_LDAP_HOST'),
                            'port' => 12345,
                            'database' => getenv('HIVE_DB_LDAP_DATABASE'),
                            'user' => getenv('HIVE_DB_LDAP_USER'),
                            '#password' => getenv('HIVE_DB_LDAP_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-database' => [
                'Error connecting to DB: [Cloudera][Hardy] (68) Error returned trying to set notfound as the initial ' .
                'database: [Cloudera][Hardy] (80) Syntax or semantic analysis error thrown in server while executing ' .
                'query. Error message from server: Error while compiling statement: FAILED: SemanticException [Error ' .
                '10072]: Database does not exist: notfound; Also tried quoting the database name `notfound` but the ' .
                'query failed with the following error: [Cloudera][Hardy] (80) Syntax or semantic analysis error ' .
                'thrown in server while executing query. S1000',
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_LDAP_HOST'),
                            'port' => (int) getenv('HIVE_DB_LDAP_PORT'),
                            'database' => 'notFound',
                            'user' => getenv('HIVE_DB_LDAP_USER'),
                            '#password' => getenv('HIVE_DB_LDAP_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-user' => [
                'Error connecting to DB: [Cloudera][ThriftExtension] (2) ' .
                'Error occured during authentication.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_LDAP_HOST'),
                            'port' => (int) getenv('HIVE_DB_LDAP_PORT'),
                            'database' => getenv('HIVE_DB_LDAP_DATABASE'),
                            'user' => 'invalidUser',
                            '#password' => getenv('HIVE_DB_LDAP_PASSWORD'),
                        ],
                    ],
                ],
            ],
            'invalid-password' => [
                'Error connecting to DB: [Cloudera][ThriftExtension] (2) ' .
                'Error occured during authentication.',
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_LDAP_HOST'),
                            'port' => (int) getenv('HIVE_DB_LDAP_PORT'),
                            'database' => getenv('HIVE_DB_LDAP_DATABASE'),
                            'user' => getenv('HIVE_DB_LDAP_USER'),
                            '#password' => 'invalidPassword',
                        ],
                    ],
                ],
            ],
        ];
    }
}

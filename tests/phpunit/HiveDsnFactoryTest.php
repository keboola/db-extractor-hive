<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests;

use Keboola\DbExtractor\Configuration\HiveDatabaseConfig;
use Keboola\DbExtractor\Connection\HiveCertManager;
use Keboola\DbExtractor\Connection\HiveDsnFactory;
use Keboola\DbExtractor\Exception\UserException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

class HiveDsnFactoryTest extends TestCase
{
    public function testParsePrincipalValid(): void
    {
        [$actualServiceName, $actualHost, $realm] = HiveDsnFactory::parsePrincipal('service/host@EXAMPLE.COM');
        Assert::assertSame('service', $actualServiceName);
        Assert::assertSame('host', $actualHost);
        Assert::assertSame('EXAMPLE.COM', $realm);
    }

    public function testParsePrincipalInvalid1(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage(
            'Unexpected format of the Kerberos principal. '.
            'Expected "service/host@EXAMPLE.COM". Given "host@EXAMPLE.COM".',
        );
        HiveDsnFactory::parsePrincipal('host@EXAMPLE.COM');
    }

    public function testParsePrincipalInvalid2(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage(
            'Unexpected format of the Kerberos principal. ' .
            'Expected "service/host@EXAMPLE.COM". Given "service///host@EXAMPLE.COM".',
        );
        HiveDsnFactory::parsePrincipal('service///host@EXAMPLE.COM');
    }

    public function testParsePrincipalInvalid3(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage(
            'Unexpected format of the Kerberos principal. ' .
            'Expected "service/host@EXAMPLE.COM". Given "".',
        );
        HiveDsnFactory::parsePrincipal('');
    }

    /**
     * @dataProvider getValidConfigs
     */
    public function testValid(array $dbConfigArray, string $expected): void
    {
        $logger = new TestLogger();
        $dsnFactory = new HiveDsnFactory();
        $dbConfig = HiveDatabaseConfig::fromArray($dbConfigArray);
        $actual = $dsnFactory->create($logger, $dbConfig, new HiveCertManager($dbConfig));
        Assert::assertSame($expected, $actual);
    }

    public function getValidConfigs(): iterable
    {
        yield 'password-auth' => [
            [
                'host' => 'test-host.com',
                'port' => '123',
                'database' => 'my-db',
                'authType' => 'password',
                'user' => 'usr',
                '#password' => '123',
            ],
            'Driver=Cloudera ODBC Driver for Apache Hive 64-bit;Host=test-host.com;Port=123;' .
            'Schema=my-db;UseNativeQuery=1;DefaultStringColumnLength=65536;DefaultVarcharColumnLength=65536;'.
            'BinaryColumnLength=65536;UseUnicodeSqlCharacterTypes=1;KeepAlive=1;RowsFetchedPerBlock=10000;AuthMech=3;',
        ];

        yield 'kerberos-auth' => [
                [
                'host' => 'test-host.com',
                'port' => '123',
                'database' => 'my-db',
                'authType' => 'kerberos',
                'kerberos' => [
                    'kinitPrincipal' => 'init/localhost',
                    'servicePrincipal' => 'service/localhost@EXAMPLE.COM',
                    'config' => '...',
                    '#keytab' => '...',
                ],
                ],
                'Driver=Cloudera ODBC Driver for Apache Hive 64-bit;Host=test-host.com;Port=123;Schema=my-db;'.
                'UseNativeQuery=1;DefaultStringColumnLength=65536;DefaultVarcharColumnLength=65536;'.
                'UseUnicodeSqlCharacterTypes=1;KeepAlive=1;RowsFetchedPerBlock=10000;AuthMech=1;'.
                'BinaryColumnLength=65536;KrbHostFQDN=localhost;KrbServiceName=service;KrbRealm=EXAMPLE.COM;',
        ];

        yield 'batch size' => [
            [
                'host' => 'test-host.com',
                'port' => '123',
                'database' => 'my-db',
                'authType' => 'password',
                'user' => 'user',
                '#password' => 'pass',
                'batchSize' => 3000,
            ],
            'Driver=Cloudera ODBC Driver for Apache Hive 64-bit;Host=test-host.com;Port=123;Schema=my-db;'.
            'UseNativeQuery=1;DefaultStringColumnLength=65536;DefaultVarcharColumnLength=65536;'.
            'BinaryColumnLength=65536;UseUnicodeSqlCharacterTypes=1;KeepAlive=1;RowsFetchedPerBlock=3000;AuthMech=3;',
        ];

        yield 'verbose logging' => [
            [
                'host' => 'test-host.com',
                'port' => '123',
                'database' => 'my-db',
                'authType' => 'password',
                'user' => 'user',
                '#password' => 'pass',
                'verboseLogging' => true,
            ],
            'Driver=Cloudera ODBC Driver for Apache Hive 64-bit;Host=test-host.com;Port=123;Schema=my-db;'.
            'UseNativeQuery=1;DefaultStringColumnLength=65536;DefaultVarcharColumnLength=65536;'.
            'BinaryColumnLength=65536;UseUnicodeSqlCharacterTypes=1;KeepAlive=1;RowsFetchedPerBlock=10000;LogLevel=6;'.
            'LogPath=/var/log/cloudera-odbc/;AuthMech=3;',
        ];

        yield 'thrift transport and http path' => [
            [
                'host' => 'test-host.com',
                'port' => '123',
                'database' => 'my-db',
                'authType' => 'password',
                'user' => 'user',
                '#password' => 'pass',
                'thriftTransport' => 2,
                'httpPath' => 'gateway/XXXXX/hive',
            ],
            'Driver=Cloudera ODBC Driver for Apache Hive 64-bit;Host=test-host.com;Port=123;Schema=my-db;'.
            'UseNativeQuery=1;DefaultStringColumnLength=65536;DefaultVarcharColumnLength=65536;'.
            'BinaryColumnLength=65536;UseUnicodeSqlCharacterTypes=1;KeepAlive=1;RowsFetchedPerBlock=10000;'.
            'ThriftTransport=2;HttpPath=gateway/XXXXX/hive;AuthMech=3;',
        ];
    }

    public function getValidPrincipals(): iterable
    {
        yield [
            'service/host@EXAMPLE.COM',
            'service',
            'host',
        ];

        yield [
            'host@EXAMPLE.COM',
            'service',
            'host',
        ];
    }
}

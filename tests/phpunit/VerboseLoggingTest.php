<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests;

use Keboola\DbExtractor\Tests\Traits\CreateApplicationTrait;
use Keboola\DbExtractor\Tests\Traits\SshKeysTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Finder\Finder;

class VerboseLoggingTest extends TestCase
{
    use CreateApplicationTrait;
    use SshKeysTrait;

    protected function setUp(): void
    {
        $this->dataDir = '/data';
        putenv('KBC_DATADIR='. $this->dataDir);
        parent::setUp();
    }

    /**
     * @dataProvider validConfigProvider
     */
    public function testVerboseLogging(array $config): void
    {
        $this->createApplication($config, new NullLogger())->execute();

        $finder = new Finder();
        $searchDirectory = sprintf('%s/artifacts/out/current', $this->dataDir);
        $finder->files()->in($searchDirectory)->name('/clouderaodbcdriverforapachehive_connection_\d+\.log$/');
        $filesArray = iterator_to_array($finder);
        /** @var \Symfony\Component\Finder\SplFileInfo|false $file */
        $file = reset($filesArray);

        if ($file) {
            $logPath = $file->getRealPath();
            if ($logPath !== false) {
                $this->assertNotEmpty(file_get_contents($logPath));
            } else {
                $this->fail('Log file in artifacts not found');
            }
        } else {
            $this->fail('Log file in artifacts not found');
        }
    }

    public function validConfigProvider(): array
    {
        return [
            'logging enable' => [
                [
                    'parameters' => [
                        'db' => [
                            'host' => getenv('HIVE_DB_LDAP_HOST'),
                            'port' => (int) getenv('HIVE_DB_LDAP_PORT'),
                            'database' => getenv('HIVE_DB_LDAP_DATABASE'),
                            'user' => getenv('HIVE_DB_LDAP_USER'),
                            '#password' => getenv('HIVE_DB_LDAP_PASSWORD'),
                            'verboseLogging' => true,
                        ],
                        'query' => 'SELECT * FROM sales LIMIT 10',
                        'outputTable' => 'in.c-main.sales',
                        'enabled' => true,
                        'id' => 0,
                        'name' => 'sales',
                        'primaryKey' => [],
                    ],
                ],
            ],
        ];
    }
}

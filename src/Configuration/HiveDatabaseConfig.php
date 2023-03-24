<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Configuration;

use Keboola\DbExtractorConfig\Configuration\ValueObject\DatabaseConfig;
use Keboola\DbExtractorConfig\Exception\PropertyNotSetException;

class HiveDatabaseConfig extends DatabaseConfig
{
    private string $authType;

    private ?string $krb5KinitPrincipal;

    private ?string $krb5ServicePrincipal;

    private ?string $krb5Config;

    private ?string $krb5Keytab;

    private bool $connectThrough;

    private ?int $thriftTransport;

    private ?string $httpPath;

    private int $batchSize;

    private bool $verboseLogging;

    public static function fromArray(array $data): self
    {
        $sslEnabled = !empty($data['ssl']) && !empty($data['ssl']['enabled']);

        return new self(
            $data['host'],
            $data['port'] ? (string) $data['port'] : null,
            $data['user'] ?? '',
            $data['#password'] ?? '',
            $data['database'] ?? null,
            null,
            $sslEnabled ? HiveSslConnectionConfig::fromArray($data['ssl']) : null,
            $data['authType'],
            $data['kerberos']['kinitPrincipal'] ?? null,
            $data['kerberos']['servicePrincipal'] ?? null,
            $data['kerberos']['config'] ?? null,
            $data['kerberos']['#keytab'] ?? null,
            $data['connectThrough'] ?? false,
            $data['thriftTransport'] ?? null,
            $data['httpPath'] ?? null,
            $data['batchSize'] ?? 10000,
            $data['verboseLogging'] ?? false,
        );
    }

    public function __construct(
        string $host,
        ?string $port,
        string $username,
        string $password,
        ?string $database,
        ?string $schema,
        ?HiveSslConnectionConfig $sslConnectionConfig,
        string $authType,
        ?string $krb5KInitPrincipal,
        ?string $krb5ServicePrincipal,
        ?string $krb5Config,
        ?string $krb5Keytab,
        bool $connectThrough,
        ?int $thriftTransport,
        ?string $httpPath,
        int $batchSize,
        bool $verboseLogging
    ) {
        parent::__construct($host, $port, $username, $password, $database, $schema, $sslConnectionConfig, []);
        $this->authType = $authType;
        $this->krb5KinitPrincipal = $krb5KInitPrincipal;
        $this->krb5ServicePrincipal = $krb5ServicePrincipal;
        $this->krb5Config = $krb5Config;
        $this->krb5Keytab = $krb5Keytab;
        $this->connectThrough = $connectThrough;
        $this->thriftTransport = $thriftTransport;
        $this->httpPath = $httpPath;
        $this->batchSize = $batchSize;
        $this->verboseLogging = $verboseLogging;
    }

    public function getSslConnectionConfig(): HiveSslConnectionConfig
    {
        /** @var HiveSslConnectionConfig $sslConfig */
        $sslConfig = parent::getSslConnectionConfig();
        return $sslConfig;
    }

    public function getAuthType(): string
    {
        return $this->authType;
    }

    public function getUsername(): string
    {
        $username = parent::getUsername();
        if (!$username) {
            throw new PropertyNotSetException('Property "username" is not set.');
        }

        return $username;
    }

    public function getPassword(): string
    {
        $password = parent::getPassword();
        if (!$password) {
            throw new PropertyNotSetException('Property "password" is not set.');
        }

        return $password;
    }

    public function hasKrb5KinitPrincipal(): bool
    {
        return $this->krb5KinitPrincipal !== null;
    }

    public function getKrb5KinitPrincipal(): string
    {
        if (!$this->krb5KinitPrincipal) {
            throw new PropertyNotSetException('Property "krb5KinitPrincipal" is not set.');
        }
        return $this->krb5KinitPrincipal;
    }

    public function hasKrb5ServicePrincipal(): bool
    {
        return $this->krb5ServicePrincipal !== null;
    }

    public function getKrb5ServicePrincipal(): string
    {
        if (!$this->krb5ServicePrincipal) {
            throw new PropertyNotSetException('Property "krb5ServicePrincipal" is not set.');
        }
        return $this->krb5ServicePrincipal;
    }

    public function hasKrb5Conf(): bool
    {
        return $this->krb5Config !== null;
    }

    public function getKrb5Config(): string
    {
        if (!$this->krb5Config) {
            throw new PropertyNotSetException('Property "krb5Config" is not set.');
        }

        return $this->krb5Config;
    }

    public function hasKrb5Keytab(): bool
    {
        return $this->krb5Keytab !== null;
    }

    public function getKrb5Keytab(): string
    {
        if (!$this->krb5Keytab) {
            throw new PropertyNotSetException('Property "krb5Keytab" is not set.');
        }

        return $this->krb5Keytab;
    }

    public function isConnectThroughEnabled(): bool
    {
        return $this->connectThrough;
    }

    public function getThriftTransport(): ?int
    {
        return $this->thriftTransport;
    }

    public function getHttpPath(): ?string
    {
        return $this->httpPath;
    }

    public function getBatchSize(): string
    {
        return (string) $this->batchSize;
    }

    public function isVerboseLoggingEnabled(): bool
    {
        return $this->verboseLogging;
    }
}

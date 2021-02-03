<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Configuration;

use Keboola\DbExtractorConfig\Configuration\ValueObject\DatabaseConfig;
use Keboola\DbExtractorConfig\Exception\PropertyNotSetException;

class HiveDatabaseConfig extends DatabaseConfig
{
    private string $authType;

    private ?string $krb5Principal;

    private ?string $krb5Config;

    private ?string $krb5Keytab;

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
            $data['kerberos']['principal'] ?? null,
            $data['kerberos']['config'] ?? null,
            $data['kerberos']['#keytab'] ?? null,
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
        ?string $krb5Principal,
        ?string $krb5Config,
        ?string $krb5Keytab
    ) {
        parent::__construct($host, $port, $username, $password, $database, $schema, $sslConnectionConfig);
        $this->authType = $authType;
        $this->krb5Principal = $krb5Principal;
        $this->krb5Config = $krb5Config;
        $this->krb5Keytab = $krb5Keytab;
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

    public function hasKrb5Principal(): bool
    {
        return $this->krb5Principal !== null;
    }

    public function getKrb5Principal(): string
    {
        if (!$this->krb5Principal) {
            throw new PropertyNotSetException('Property "krb5Principal" is not set.');
        }
        return $this->krb5Principal;
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
}

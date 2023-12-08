<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Configuration;

use Keboola\DbExtractorConfig\Configuration\ValueObject\SSLConnectionConfig;
use Keboola\DbExtractorConfig\Exception\PropertyNotSetException;

class HiveSslConnectionConfig extends SSLConnectionConfig
{
    private ?string $caFileType;

    public static function fromArray(array $data): HiveSslConnectionConfig
    {
        return new self(
            null,
            null,
            $data['ca'] ?? null,
            $data['caFileType'] ?? null,
            null,
            $data['verifyServerCert'] ?? true,
            $data['ignoreCertificateCn'] ?? false,
        );
    }

    public function __construct(
        ?string $key,
        ?string $cert,
        ?string $ca,
        ?string $caFileType,
        ?string $cipher,
        bool $verifyServerCert,
        bool $ignoreCertificateCn,
    ) {
        $this->caFileType = $caFileType;
        parent::__construct($key, $cert, $ca, $cipher, $verifyServerCert, $ignoreCertificateCn);
    }

    public function hasCaFileType(): bool
    {
        return $this->caFileType !== null;
    }

    public function getCaFileType(): string
    {
        if ($this->caFileType === null) {
            throw new PropertyNotSetException('Property "caFileType" is not set.');
        }
        return $this->caFileType;
    }
}

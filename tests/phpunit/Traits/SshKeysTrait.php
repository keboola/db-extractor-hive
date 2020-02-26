<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests\Traits;

trait SshKeysTrait
{
    public function getPrivateKey(): string
    {
        return (string) file_get_contents('/root/.ssh/id_rsa');
    }

    public function getPublicKey(): string
    {
        return (string) file_get_contents('/root/.ssh/id_rsa.pub');
    }
}

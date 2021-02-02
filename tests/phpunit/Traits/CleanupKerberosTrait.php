<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Tests\Traits;

use Symfony\Component\Process\Process;

trait CleanupKerberosTrait
{
    public function cleanupKerberos(): void
    {
        // Clear Kerberos login
        Process::fromShellCommandline('kdestroy')->mustRun();
    }
}

<?php

declare(strict_types=1);

use Keboola\CommonExceptions\UserExceptionInterface;
use Keboola\Component\Logger;
use Keboola\DbExtractor\HiveApplication;

require __DIR__ . '/../vendor/autoload.php';

$logger = new Logger();
try {
    $app = new HiveApplication($logger);
    $app->execute();
    exit(0);
} catch (Throwable $e) {
    $logger->critical(
        get_class($e) . ':' . $e->getMessage(),
        [
            'errFile' => $e->getFile(),
            'errLine' => $e->getLine(),
            'errCode' => $e->getCode(),
            'errTrace' => $e->getTraceAsString(),
            'errPrevious' => is_object($e->getPrevious()) ? get_class($e->getPrevious()) : '',
        ],
    );

    // Copy odbc logs to file output
    $sourceDir = '/var/log/hive-odbc';
    $destDir   = '/data/out/files';

    // Make sure the destination exists
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            $logger->critical(sprintf('ODBC logs error: Directory "%s" was not created', $destDir));
        }
    }

    // Find all .log files in the source directory
    $logFiles = glob($sourceDir . DIRECTORY_SEPARATOR . '*.log');
    if ($logFiles === false) {
        $logger->critical(sprintf('Failed to read log directory: %s', $sourceDir));
    }
    $logger->info(sprintf('Found %d log files in %s', count($logFiles), $sourceDir));

    // Copy each log file
    foreach ($logFiles as $filePath) {
        $fileName = basename($filePath);
        $target = $destDir . DIRECTORY_SEPARATOR . $fileName;

        if (!copy($filePath, $target)) {
            $logger->critical(sprintf('Failed to copy file from %s to %s', $filePath, $target));
        } else {
            // Optionally, you can unlink($filePath) here if you want to move instead of copy
            // unlink($filePath);
            echo "Copied: $fileName\n";
        }
    }

    exit(0);
}

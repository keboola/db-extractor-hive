<?php

declare(strict_types=1);

use Keboola\DbExtractor\FunctionalTests\DatadirTest;

return function (DatadirTest $test): void {
    $certPath = (string) getenv('HIVE_DB_KERBEROS_SSL_CERT_JKS_PATH');
    $dir = (string) getenv('BUNDLED_FILES_PATH');
    copy($certPath, $dir . '/my-certs.jks');
};

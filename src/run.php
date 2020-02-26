<?php

declare(strict_types=1);

use Keboola\DbExtractor\Exception\UserException;
use Keboola\DbExtractorLogger\Logger;
use Keboola\DbExtractor\HiveApplication;
use Monolog\Handler\NullHandler;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

require __DIR__ . '/../vendor/autoload.php';

$logger = new Logger('ex-db-hive');
$jsonDecode = new JsonDecode([JsonDecode::ASSOCIATIVE => true]);
$jsonEncode = new JsonEncode();
$runAction = true;

try {
    $dataFolder = getenv('KBC_DATADIR') === false ? '/data/' : (string) getenv('KBC_DATADIR');
    if (file_exists($dataFolder . '/config.json')) {
        $config = $jsonDecode->decode(
            (string) file_get_contents($dataFolder . '/config.json'),
            JsonEncoder::FORMAT
        );
    } else {
        throw new UserException('Configuration file not found.');
    }

    // get the state
    $inputStateFile = $dataFolder . '/in/state.json';
    if (file_exists($inputStateFile)) {
        $inputState = $jsonDecode->decode(
            (string) file_get_contents($inputStateFile),
            JsonEncoder::FORMAT
        );
    } else {
        $inputState = [];
    }

    $app = new HiveApplication($config, $logger, $inputState, $dataFolder);

    if ($app['action'] !== 'run') {
        $app['logger']->setHandlers(array(new NullHandler(Logger::INFO)));
        $runAction = false;
    }

    $result = $app->run();

    if (!$runAction) {
        echo $jsonEncode->encode($result, JsonEncoder::FORMAT);
    } else {
        if (!empty($result['state'])) {
            // write state
            $outputStateFile = $dataFolder . '/out/state.json';
            file_put_contents($outputStateFile, $jsonEncode->encode($result['state'], JsonEncoder::FORMAT));
        }
    }
    $app['logger']->log('info', 'Extractor finished successfully.');
    exit(0);
} catch (UserException $e) {
    $logger->error($e->getMessage());
    exit(1);
} catch (\Throwable $e) {
    $logger->critical(
        get_class($e) . ':' . $e->getMessage(),
        [
            'errFile' => $e->getFile(),
            'errLine' => $e->getLine(),
            'errCode' => $e->getCode(),
            'errTrace' => $e->getTraceAsString(),
            'errPrevious' => $e->getPrevious() ? get_class($e->getPrevious()) : '',
        ]
    );
    exit(2);
}

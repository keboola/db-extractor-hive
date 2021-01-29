<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Extractor;

use InvalidArgumentException;
use Keboola\Csv\CsvWriter;
use Keboola\DbExtractor\Adapter\ODBC\OdbcQueryResult;
use Keboola\DbExtractor\Adapter\ResultWriter\DefaultResultWriter;
use Keboola\DbExtractor\Adapter\ValueObject\ExportResult;
use Keboola\DbExtractor\Adapter\ValueObject\QueryResult;
use Keboola\DbExtractorConfig\Configuration\ValueObject\ExportConfig;

class HiveResultWriter extends DefaultResultWriter
{
    private array $binaryColumns;

    public function writeToCsv(QueryResult $result, ExportConfig $exportConfig, string $csvFilePath): ExportResult
    {
        if (!$result instanceof OdbcQueryResult) {
            throw new InvalidArgumentException('Expected OdbcQueryResult');
        }
        $this->processFieldTypes($result);
        return parent::writeToCsv($result, $exportConfig, $csvFilePath);
    }

    protected function writeRow(array $row, CsvWriter $csvWriter): void
    {
        // Convert binary columns to base64.
        foreach ($this->binaryColumns as $name) {
            $row[$name] = base64_encode($row[$name]);
        }

        parent::writeRow($row, $csvWriter);
    }

    private function processFieldTypes(OdbcQueryResult $result): void
    {
        $resource = $result->getResource();
        $count = odbc_num_fields($resource);
        $binaryColumns = [];
        for ($index=1; $index<=$count; $index++) {
            $type = odbc_field_type($resource, $index);
            if ($type === 'BINARY') {
                $binaryColumns[] = odbc_field_name($resource, $index);
            }
        }

        // Save the list of binary columns so we can encode them to base64.
        $this->binaryColumns = $binaryColumns;
    }
}

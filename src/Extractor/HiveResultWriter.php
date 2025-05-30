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
            if (isset($row[$name]) && $row[$name] !== null) {
                $row[$name] = base64_encode($row[$name]);
            }
        }

        // Sanitize all string values to ensure valid UTF-8
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                // 1. Check if it's valid UTF-8
                if (!$this->isValidUtf8($value)) {
                    // 2. Try to convert from other encodings
                    $sanitized = $this->sanitizeUtf8($value);

                    // 3. If still invalid, encode as base64 and mark it
                    if (!$this->isValidUtf8($sanitized)) {
                        $row[$key] = base64_encode($value);
                    } else {
                        $row[$key] = $sanitized;
                    }
                }
            }
        }

        parent::writeRow($row, $csvWriter);
    }

    /**
     * Check if a string is valid UTF-8
     */
    private function isValidUtf8(string $string): bool
    {
        return mb_check_encoding($string, 'UTF-8');
    }

    /**
     * Attempt to sanitize a string to valid UTF-8
     */
    private function sanitizeUtf8(string $string): string
    {
        // Method 1: Try detecting and converting the encoding
        $encodings = ['UTF-8', 'ASCII', 'ISO-8859-1', 'ISO-8859-15', 'Windows-1252'];
        foreach ($encodings as $encoding) {
            if (mb_check_encoding($string, $encoding)) {
                return mb_convert_encoding($string, 'UTF-8', $encoding);
            }
        }

        // Method 2: Remove or replace invalid characters
        $string = iconv('UTF-8', 'UTF-8//IGNORE', $string);

        // Method 3: If all else fails, use mb_convert_encoding with a fallback encoding
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
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

    public function hasCsvHeader(ExportConfig $exportConfig): bool
    {
        return false;
    }
}

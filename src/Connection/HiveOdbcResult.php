<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Dibi\Drivers\OdbcResult;
use Dibi\Type;

class HiveOdbcResult extends OdbcResult
{
    /** @var resource */
    private $resultSet;
    /**
     * @param  resource  $resultSet
     */
    public function __construct($resultSet)
    {
        $this->resultSet = $resultSet;
        parent::__construct($resultSet);
    }

    public function unescapeBinary(string $value): string
    {
        return base64_encode($value);
    }

    public function getResultColumns(): array
    {
        $count = odbc_num_fields($this->resultSet);
        $columns = [];
        for ($i = 1; $i <= $count; $i++) {
            $nativeType = odbc_field_type($this->resultSet, $i);
            $type = $nativeType;

            // Don't convert date types to object
            if ($nativeType === 'BINARY') {
                $type = Type::BINARY;
            } else {
                $type = Type::TEXT;
            }

            $columns[] = [
                'name' => odbc_field_name($this->resultSet, $i),
                'table' => null,
                'fullname' => odbc_field_name($this->resultSet, $i),
                'type' => $type,
                'nativetype' => $nativeType,
            ];
        }

        return $columns;
    }
}

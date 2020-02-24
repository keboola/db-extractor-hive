<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Dibi;

class HiveOdbcResult extends Dibi\Drivers\OdbcResult
{
    private const KEEP_TEXT_TYPES = [Dibi\Type::DATE, Dibi\Type::DATETIME, Dibi\Type::TIME, Dibi\Type::TIME_INTERVAL];

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

    public function getResultColumns(): array
    {
        $count = odbc_num_fields($this->resultSet);
        $columns = [];
        for ($i = 1; $i <= $count; $i++) {
            $nativeType = odbc_field_type($this->resultSet, $i);
            $type = $nativeType;

            // Don't convert date types to object
            if (in_array($nativeType, self::KEEP_TEXT_TYPES, true)) {
                $type = Dibi\Type::TEXT;
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

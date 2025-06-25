<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Extractor;

use ErrorException;
use Iterator;
use Keboola\DbExtractor\Adapter\Exception\ApplicationException;
use Keboola\DbExtractor\Adapter\ODBC\OdbcQueryResult;
use Keboola\DbExtractor\Adapter\ValueObject\QueryMetadata;
use Keboola\DbExtractor\Adapter\ValueObject\QueryResult;

class HiveOdbcQueryResult extends OdbcQueryResult implements QueryResult
{
    /**
     * @return array|null
     * @throws ApplicationException
     */
    public function fetch(): ?array
    {
        /** @var array|null $row */
        $row = null;
        $numCols = odbc_num_fields($this->stmt);

        odbc_longreadlen($this->stmt, 134217728);
        odbc_binmode($this->stmt, ODBC_BINMODE_RETURN);

        if (odbc_fetch_row($this->stmt)) {
            for ($col = 1; $col <= $numCols; $col++) {
                // grab the column name for a nice associative key
                $name = odbc_field_name($this->stmt, $col);
                // fetch the value (will stream even large/unknown‐length columns)
                try {
                    $row[$name] = odbc_result($this->stmt, $col);
                } catch (ErrorException $e) {
                    $length = odbc_field_len($this->stmt, $col);
                    $type = odbc_field_type($this->stmt, $col);

                    throw new ApplicationException(sprintf(
                        'Error fetching column "%s [%s (%s)]": %s',
                        $name,
                        $type,
                        $length,
                        $e->getMessage(),
                    ), 0, $e);
                }
            }
        }

        return $row;
    }
}

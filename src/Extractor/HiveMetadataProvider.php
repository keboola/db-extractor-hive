<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Extractor;

use UnexpectedValueException;
use Dibi\Connection;
use Keboola\Datatype\Definition\GenericStorage;
use Keboola\DbExtractor\Exception\InvalidArgumentException;
use Keboola\DbExtractor\TableResultFormat\Metadata\Builder\MetadataBuilder;
use Keboola\DbExtractor\TableResultFormat\Metadata\ValueObject\Table;
use Keboola\DbExtractor\TableResultFormat\Metadata\ValueObject\TableCollection;
use Keboola\DbExtractorConfig\Configuration\ValueObject\InputTable;

class HiveMetadataProvider implements MetadataProvider
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function getTable(InputTable $table): Table
    {
        return $this
            ->listTables([$table])
            ->getByNameAndSchema($table->getName(), $table->getSchema());
    }

    public function listTables(array $whitelist = [], bool $loadColumns = true): TableCollection
    {
        if ($loadColumns === false) {
            throw new UnexpectedValueException(
                'Metadata cannot be loaded without columns. Not implemented.'
            );
        }

        $databaseName = $this->db->getDatabaseInfo()->name;
        $whitelistTableNames = array_map(function (InputTable $table) use ($databaseName) {
            if ($table->getSchema() !== $databaseName) {
                throw new InvalidArgumentException(sprintf(
                    'Table "%s"."%s" is not from used database.',
                    $table->getSchema(),
                    $table->getName()
                ));
            }
            return $table->getName();
        }, $whitelist);

        $reflector = $this->db->getDriver()->getReflector();
        $tables = $reflector->getTables();

        $builder = MetadataBuilder::create();
        foreach ($tables as $table) {
            $tableName = $table['name'];
            if ($whitelist && !in_array($tableName, $whitelistTableNames, true)) {
                // skip if name is not in allowed names
                continue;
            }

            // Table metadata
            $tableBuilder = $builder
                ->addTable()
                ->setName($tableName)
                ->setSchema($databaseName);

            // Column metadata
            foreach ($reflector->getColumns($tableName) as $column) {
                // Hive DB doesn't support PK, FK, NOT NULL,...
                // See: https://issues.apache.org/jira/browse/HIVE-6905
                $baseType = new GenericStorage($column['nativetype'], ['length' => $column['size']]);
                $tableBuilder
                    ->addColumn()
                    ->setName($column['name'])
                    ->setType($baseType->getBasetype())
                    ->setLength($baseType->getLength());
            }
        }

        return $builder->build();
    }
}

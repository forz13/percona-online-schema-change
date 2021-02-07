<?php

namespace forz13\perconaOnlineSchemaChange;

use RuntimeException;
use yii\db\Connection;

/**
 * Trait PerconaOnlineSchemaChangeTrait
 * @property-read Connection $db
 */
trait PerconaOnlineSchemaChangeTrait
{
    /**
     * Run query
     *
     * @param string $tableName - data base table name, e.g: tbl_order
     * @param string $command - DML query without 'ALTER TABLE `some table`' expression, e.g: 'ADD COLUMN test000
     *     INT(10)'
     * @param array $options - options to how execute script:
     *                       [
     *                       'alterForeignKeysMethod' => PerconaOnlineSchemaChange::ALTER_FOREIGN_KEY_AUTO,
     *                       'debug'                  => false,
     *                       'execute'                => false,
     *                       ]
     *                       if do not pass this parameter, then the default options
     *
     * @return bool
     * @throws RuntimeException
     * @throws \yii\base\InvalidConfigException
     */
    public function schemaChange($tableName, $command, array $options = null)
    {
        if ($options === null) {
            $options = [
                'alterForeignKeysMethod' => PerconaOnlineSchemaChange::ALTER_FOREIGN_KEY_DROP_SWAP,
                'execute'                => true,
                'debug'                  => false,
            ];
        }

        try {
            $this->getSchemaChanger()->run($tableName, $command, $options);

            return true;
        } catch (RuntimeException $ex) {
            echo $ex->getMessage() . PHP_EOL;

            return false;
        }
    }

    /**
     * @param            $table
     * @param            $column
     * @param            $type
     * @param array|null $options
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function addColumn($table, $column, $type, array $options = null)
    {
        if ($options === null) {
            $options = [
                'alterForeignKeysMethod' => PerconaOnlineSchemaChange::ALTER_FOREIGN_KEY_DROP_SWAP,
                'execute'                => true,
                'debug'                  => false,
            ];
        }

        $tableName = $this->db->schema->getRawTableName($table);
        $command   = "ADD COLUMN $column " . $this->db->getQueryBuilder()->getColumnType($type);

        $this->getSchemaChanger()->run($tableName, $command, $options);
    }

    /**
     * @param            $table
     * @param            $column
     * @param array|null $options
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function dropColumn($table, $column, array $options = null)
    {
        if ($options === null) {
            $options = [
                'alterForeignKeysMethod' => PerconaOnlineSchemaChange::ALTER_FOREIGN_KEY_DROP_SWAP,
                'execute'                => true,
                'debug'                  => false,
            ];
        }

        $tableName = $this->db->schema->getRawTableName($table);
        $command   = "DROP COLUMN $column";

        $this->getSchemaChanger()->run($tableName, $command, $options);
    }

    /**
     * @param string $name
     * @param string $table
     * @param string|array $columns
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    public function addIndex($name, $table, $columns)
    {
        $tableName = $this->db->schema->getRawTableName($table);
        $columns = $this->buildColumns($columns);

        return $this->schemaChange($tableName, "ADD INDEX $name ($columns)");
    }

    /**
     * @param string $name
     * @param string $table
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    public function dropIndex($name, $table)
    {
        $tableName = $this->db->schema->getRawTableName($table);

        return $this->schemaChange($tableName, "DROP INDEX $name");
    }

    /**
     * Processes columns and properly quotes them if necessary.
     * It will join all columns into a string with comma as separators.
     * @param string|array $columns the columns to be processed
     * @return string the processing result
     */
    private function buildColumns($columns)
    {
        if (!is_array($columns)) {
            if (strpos($columns, '(') !== false) {
                return $columns;
            } else {
                $columns = preg_split('/\s*,\s*/', $columns, -1, PREG_SPLIT_NO_EMPTY);
            }
        }

        return is_array($columns) ? implode(', ', $columns) : $columns;
    }

    /**
     * @return PerconaOnlineSchemaChange
     * @throws \yii\base\InvalidConfigException
     */
    private function getSchemaChanger()
    {
        /** @var PerconaOnlineSchemaChange $changer */
        $changer = \Yii::$app->get('perconaOnlineSchemaChange');

        return $changer;
    }
}

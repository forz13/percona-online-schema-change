<?php


namespace forz13\perconaOnlineSchemaChange;


use yii\base\BaseObject;
use RuntimeException;
use Yii;
use yii\db\Connection;
use yii\redis\Cache;


/**
 * Class PerconaOnlineSchemaChange
 * PHP wrapper for Percona tool: pt-online-schema-change
 * @see  https://www.percona.com/doc/percona-toolkit/3.0/pt-online-schema-change.html
 */
class PerconaOnlineSchemaChange extends BaseObject
{
    /**
     * Automatically determine which method is best. The tool uses rebuild_constraints if possible
     * (see the description of that method for details), and if not, then it uses drop_swap.
     */
    const  ALTER_FOREIGN_KEY_AUTO = 'auto';

    /**
     * This method uses ALTER TABLE to drop and re-add foreign key constraints that reference the new table.
     * This is the preferred technique, unless one or more of the “child” tables is so large that the ALTER
     * would take too long. The tool determines that by comparing the number of rows in the child table to the rate
     * at which the tool is able to copy rows from the old table to the new table. If the tool estimates that the
     * child table can be altered in less time than the --chunk-time, then it will use this technique. For purposes
     * of estimating the time required to alter the child table, the tool multiplies the row-copying rate
     * by --chunk-size-limit, because MySQL’s ALTER TABLE is typically much faster than the external
     * process of copying rows.
     *
     * Due to a limitation in MySQL, foreign keys will not have the same names after the ALTER that they
     * did prior to it. The tool has to rename the foreign key when it redefines it, which adds a leading
     * underscore to the name. In some cases, MySQL also automatically renames indexes required for the foreign key.
     */
    const  ALTER_FOREIGN_KEY_REBUILD_CONSTRAINTS = 'rebuild_constraints';

    /**
     * Disable foreign key checks (FOREIGN_KEY_CHECKS=0), then drop the original table before renaming the new table
     * into its place. This is different from the normal method of swapping the old and new table, which uses an atomic
     * RENAME that is undetectable to client applications.
     *
     * This method is faster and does not block, but it is riskier for two reasons. First, for a short time between
     * dropping the original table and renaming the temporary table, the table to be altered simply does not exist,
     * and queries against it will result in an error. Secondly, if there is an error and the new table cannot be
     * renamed into the place of the old one, then it is too late to abort, because the old table is gone permanently.
     *
     * This method forces --no-swap-tables and --no-drop-old-table.
     */
    const  ALTER_FOREIGN_KEY_DROP_SWAP = 'drop_swap';

    /**
     * This method is like drop_swap without the “swap”. Any foreign keys that referenced the original table will
     * now reference a nonexistent table. This will typically cause foreign key violations that are visible in
     * SHOW ENGINE INNODB STATUS
     */
    const  ALTER_FOREIGN_KEY_NONE = 'none';

    private $util;
    private $utilRunner;
    public $dbHost;
    public $dbName;
    public $dbPort;
    public $dbUser;
    public $dbPassword;
    public $charset = 'utf8';

    /**
     * @var array
     */
    public $defaultOptions = [
        'alterForeignKeysMethod' => self::ALTER_FOREIGN_KEY_DROP_SWAP,
        'execute'                => false,
        'debug'                  => false,
    ];

    /**
     * @var array
     */
    protected $options = [];

    public function init()
    {
        $this->util       = 'pt-online-schema-change';
        $this->utilRunner = '/usr/bin/perl';
        parent::init();
    }

    /**
     * @return array
     */
    protected function alterForeignKeysMethods()
    {
        return [
            self::ALTER_FOREIGN_KEY_AUTO,
            self::ALTER_FOREIGN_KEY_REBUILD_CONSTRAINTS,
            self::ALTER_FOREIGN_KEY_DROP_SWAP,
            self::ALTER_FOREIGN_KEY_NONE,
        ];
    }


    /**
     * Run query
     * @param  string  $table  - data base table name, e.g: tbl_order
     * @param  string  $query  - DML query without 'ALTER TABLE `some table`' expression, e.g: 'ADD COLUMN test000 INT(10)'
     * @param  array  $options  - options to how execute script:
     *                       [
     *                       'alterForeignKeysMethod' => PerconaOnlineSchemaChange::ALTER_FOREIGN_KEY_AUTO,
     *                       'debug'                  => false,
     *                       'execute'                => false,
     *                       ]
     *                       if do not pass this parameter, then the default options
     * @return bool
     * @throws RuntimeException
     */
    public function run($table, $query, array $options = [])
    {
        $this->beforeRun();
        $this->options = array_merge($this->defaultOptions, $options);
        $output        = [];
        $result        = 255;
        $command       = $this->getCommand($query, $table, $this->options);
        exec($command, $output, $result);
        foreach ($output as $row) {
            echo $row.PHP_EOL;
        }
        if ($result === 0) {
            return true;
        }
        throw new RuntimeException('Migration script failed with code: '.$result);
    }

    /**
     * Get script command
     * @param  string  $query
     * @param  string  $table
     * @param  array  $options
     * @return string
     */
    private function getCommand($query, $table, array $options)
    {
        $scriptFullPath = __DIR__.'/'.$this->util;
        $command        = $this->utilRunner.' '.$scriptFullPath.
                          ' h='.$this->dbHost.
                          ',P='.$this->dbPort.
                          ',u='.$this->dbUser.
                          ',p='.$this->dbPassword.
                          ',A='.$this->charset.
                          ',D='.$this->dbName.
                          ',t='.$table.
                          ' --alter "'.$query.'"'.
                          ' --alter-foreign-keys-method='.$options['alterForeignKeysMethod'];

        if ($options['execute']) {
            $command .= ' --execute';
        }
        if ($options['debug']) {
            $command = 'PTDEBUG=1 '.$command;
        }
        return $command;
    }


    /**
     * Switch off mysql schema cache
     * Close mysql db connection (will be open auto if need)
     * Close cache redis connection (will be open auto if need)
     */
    private function beforeRun()
    {
        \Yii::$app->db->enableSchemaCache = false;
        if (Yii::$app->db instanceof Connection) {
            Yii::$app->db->close();
        }
        if (Yii::$app->cache instanceof Cache) {
            Yii::$app->cache->redis->close();
        }
    }


}

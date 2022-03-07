PerconaOnlineSchemaChange component - PHP wrapper for Percona's pt-online-schema-change utility -
designed to perform Alter table without table locks.



Using this component, you will need to write alter table on large tables in Yii migrations.
You need to use non-transactional methods (up()/down()) as migration can
run for a long time, and the transaction may timeout.

```php
   public function up(){
        
      /**
      * @var $changer PerconaOnlineSchemaChange
      */
      $changer = \Yii::$app->perconaOnlineSchemaChange;
      try{
           $changer->run('tbl_order', 'ADD COLUMN test000 INT(10)',
            [
              'alterForeignKeysMethod' => $changer::ALTER_FOREIGN_KEY_DROP_SWAP,
              'execute' => true,
              'debug' => false
            ]);
            return true;
      } catch (RuntimeException $ex){
         echo $ex->getMessage() . PHP_EOL;
         return false;
      }
    }
```

execute - true - execute the script, false - check its correctness. The default is false.

debug - enable debug mode or not. The default is false.

alterForeignKeysMethod - how to change foreign keys to point to a new table.
The default is 'drop_swap'.



Operations allowed to be performed and their features:

1. In each Yii migration through this component, only 1 table change operation should be performed.


2. Changes can only be made on tables containing a PRIMARY KEY.


4. Permitted operations: ADD COLUMN, DROP COLUMN, MODIFY COLUMN, ADD INDEX, DROP INDEX, ADD FOREIGN KEY, DROP FOREIGN KEY.


5. When adding a column with NOT NULL, you must specify the default value. 


6. If we add a foreign key, for example with the name fk_foo,
then in order to delete it through a rollback to down, you will need to refer to the key through an underscore:
 
```sql
 DROP FOREIGN KEY _fk_foo
 ```
                        
 






 


Компонент PerconaOnlineSchemaChange - PHP обёртка над утилитой pt-online-schema-change от Percona - 
предназачен для выполнения Alter table без блокировок таблицы.



Используя этот компонент нужно будет писать alter table на большие таблицы в Yii миграциях.
Нужно использовать нетразакционные методы (up()/down()), так как миграция может 
выполняться длительное количество времени, и транзакция может отвалиться по таймауту.

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
              'execute'                => true,
              'debug'                  => false
            ]);
            return true;
      } catch (RuntimeException $ex){
         echo $ex->getMessage() . PHP_EOL;
         return false;
      }
    }
```
 
 execute - true - выполнить скрипт, false -  проверит его корректность. По умолчанию - false.
 
 debug - включить режим дебага или нет. По умолчанию false.
 
 alterForeignKeysMethod - как изменить внешние ключи, чтобы они ссылались на новую таблицу. 
 По умолчанию - 'drop_swap'.  
 
 
 
 Операции допустимые для выполенения и их особенности:
 
 1.В каждой Yii миграции через данный компонент должна быть выполнена только 1 операция изменения таблицы.
 
 2.Изменения могут быть выполнены только на таблицах содержащих PRIMARY KEY.
  
 3.Разрешенные операции: ADD COLUMN, DROP COLUMN, MODIFY COLUMN, ADD INDEX, DROP INDEX, ADD FOREIGN KEY, DROP FOREIGN KEY.
 
 4.При добавлениии колонки с NOT NULL нужно обязательно указать default value.
 
 5.Если мы добавленяем foreign key, к примеру с именем fk_foo, 
 то чтобы его в удалить через откат в down нужно будет обратиться к ключу через подчеркивание:
 ```sql
 DROP FOREIGN KEY _fk_foo
 ```
                        
 






 

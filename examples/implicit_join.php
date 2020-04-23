<?php

namespace Lum\DB\PDO;

require_once 'examples/common.php';
require_once 'vendor/autoload.php';

class FakeModel extends Model {}

$db = new Simple(['dsn'=>'fakedb:dbname=foo', 'noConnect'=>true]);

$res = $db->select(['table1','table2','table3'],
[
  'cols' => 'table3.*',
  'where' =>
  [
    'table1.name' => 'Foo',
    'table2.company' => new Reference('table1','id'),
    'table3.id' => new Reference('table2','user'),
  ],
]);

showres($res);

$model1 = new FakeModel(['table'=>'table1'], $db);
$model2 = new FakeModel(['table'=>'table2'], $db);
$model3 = new FakeModel(['table'=>'table3'], $db);

$res2 = $model3->select([
  'with' => [$model1,$model2],
  'where' =>
  [
    $model1->refName('name') => 'Foo',
    $model2->refName('company') => $model1->refData('id'),
    $model3->refName('id') => $model2->refData('user'),
  ],
]);

showres($res2);

<?php

namespace Lum\DB\PDO;

require_once 'examples/common.php';
require_once 'vendor/autoload.php';

$db = new Simple(['dsn'=>'fakedb:dbname=foo', 'noConnect'=>true]);
$query = new Query();

$query->get(['name','job'])->where('age','>','19')->and('age','<','50')->order('name')->limit(10)->offset(0);

$res = $db->select('employees', $query);
showres($res);

$query->reset();
$query->get('id')->where('project',123)->and()->where(['type'=>2,'class'=>1])->or('level','>',3)->sort('project ASC, id DESC');

$res = $db->select('documents', $query);
showres($res);



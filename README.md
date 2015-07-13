# simpleMysql
simple mysql driver for php

常用操作：
//从配置新建数据库连接
$db = new DB($db_config);

//统计数据表总记录行数,返回整型
$total = $db -> table($db_table) -> total();

//查询一条记录,返回一维数组
$task = $db -> table($db_table) -> where(array('id' => 1)) -> find();

//查询多条记录,返回二维数组
$tasks = $db -> table($db_table) -> limit('2,3') -> order('id desc') select();

//新增一条记录
$arr = array(
    'data' => 'test',
);
$last_insert_id = $db -> table($db_table) -> insert($arr);

//更新一条记录
$arr = array(
    'id' => $last_insert_id,
    'data' => 'ok',
);
$db -> table($db_table) -> where(array('id' => $last_insert_id)) -> update($arr);

//删除一条记录

$db -> table($db_table) -> where(array('id' => $last_insert_id)) -> delete();

it was isolated from brophp lib, you can look up BroPHP.chm  for more infomation.

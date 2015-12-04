<?php
include __DIR__ .'/db.class.php';
// 设置要连接的mysql数据库名
$db_config['database'] = 'test';
// 实例化一个mysql连接对象
$db = new DB($db_config);

// 使用simpleMysql可以执行特殊的sql语句, 通过query方法实现
$table_sql = '
CREATE TABLE IF NOT EXISTS `user1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utime` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `other1` int(11) NOT NULL,
  `other2` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';

p($db -> query($table_sql));


// 编写自定义sql语句
$sql = 'select utime, username, password, other1, other2 from user1 where utime > 0 order by utime desc, other1 asc';
// 执行select查询，如果只需要返回一条记录可以在第二个参数传入DB::find参数；如果需要返回多条记录可以在第二个参数传入DB::select参数
$arr = $db -> query($sql, 'DB::find');
// 打印出一维数组
p($arr);

// 使用类似于phpQuery
$brr = $db -> table('user1') -> field('utime, username, password, other1, other2') -> where(array('utime > ' => 0)) -> order('utime desc, other1 asc') -> find();
// 执行select语句将会返回一个二维数组
p($brr);
// 执行delete语句将会返回true或false
$result = $db -> table('user1') -> where(array('utime > ' => 0), array('password' => '')) -> delete();
p($result);

// 构造一个一维数组作为一条记录插入数据库
$crr = array();
$crr['utime'] = time();
$crr['username'] = 'test';
$crr['password'] = '123456';
$crr['other1'] = '12312';
$crr['other2'] = '21312';
$crr['test'] = 'test';
// 执行insert语句将会返回记录自增的ID
$insert_id = $db -> table('user1') -> insert($crr);
p($insert_id);

// 对一维数组的字段进行修改, 然后更新到数据库
$crr['username'] = 'change';
$crr['password'] = "123'456";
// 执行update语句将会返回true或false
$result = $db -> table('user1') -> where(array('utime' => $crr['utime'])) -> update($crr);
p($result);

// 打印函数
function p($var)
{
    echo "<pre>";
    if($var === false)
    {
        echo 'false';
    }else if($var === ''){
        print_r("''");
    }else{
        print_r($var);
    }
    echo "</pre>";
}

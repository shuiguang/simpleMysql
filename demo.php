<?php
set_time_limit(0);
header("Content-type:text/html;charset=utf-8");
include __DIR__ .'/db.class.php';
for($i=0; $i<1; $i++)
{
    p($db_config['driver']);
    $db_config['database'] = 'test';
    $db = new DB($db_config);
    $table_sql = '
    CREATE TABLE IF NOT EXISTS `user1` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `pid` int(11) NOT NULL,
      `username` varchar(255) NOT NULL,
      `password` varchar(255) NOT NULL,
      `other1` int(11) NOT NULL,
      `other2` int(11) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';

    p($db -> query($table_sql));

    $sql = 'select pid, username, password, other1, other2 from user1 where password > 0 order by pid desc, other1 asc';
    $arr = $db -> table('user1') -> field('pid, username, password, other1, other2') -> where(array('password > ' => 0)) -> order('pid desc, other1 asc') -> find();
    $arr = $db -> query($sql);
    p($arr);

    $sql = 'delete from user1 where pid>1398736277';
    $result = $db -> table('user1') -> where(array('pid > ' => 1398736277), array('password' => '')) -> delete();
    p($result);
    $sql = 'insert into user1 set(pid, username, password, other1, other2) values ('.time().', "test", "123456", "12312", "21312")';
    $brr['pid'] = time();
    $brr['username'] = 'test';
    $brr['password'] = '123456';
    $brr['other1'] = '12312';
    $brr['other2'] = '21312';
    $brr['test'] = 'test';
    $result = $db -> table('user1') -> insert($brr);
    p($result);
    $sql = 'update user1 set username="change", password="123456" where pid='.$brr['pid'];
    $brr['username'] = 'change';
    $brr['password'] = "123'456";
    $result = $db -> table('user1') -> where(array('pid' => $brr['pid'])) -> update($brr);
    p($result);
    $result = $db -> table('user1') -> total();
    p($result);
    $result = $db -> table('user1') -> select();
    p($result);
    p($db->dbSize());
}

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

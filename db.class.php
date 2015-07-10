<?php
//数据库连接配置
$db_config = array();
$db_config['driver']    = 'mysqli';             //mysql驱动,支持mysqli > pdo > mysql共3种驱动
$db_config['cache_dir'] = '';                   //mysql缓存目录,当目录不存在时关闭缓存
$db_config['cache_time']= 60*60*24;             //mysql缓存时间,默认1天
$db_config['host']      = 'localhost';          //mysql主机
$db_config['user']      = 'root';               //mysql用户名
$db_config['pwd']       = '';                   //mysql密码
$db_config['port']      = '3306';               //mysql端口
$db_config['database']  = 'test';               //默认测试数据库,非测试不用填写
$db_config['charset']   = 'utf8';               //mysql查询字符集
$db_config['pconnect']  = false;                //是否使用持久性连接
$db_config['socket']    = '';                   //如果使用socket文件连接,则填入socket文件绝对路径,否则不要填写
$link_file = __DIR__ .'/'.$db_config['driver'].'.class.php';
if(file_exists($link_file))
{
    //常驻内存时为include_once,否则建议改为include
    include_once $link_file;
}else{
    throw new Exception('DB include '.$link_file.' not found!');
}
if(!defined('DB_CONFIG'))
{
    define('DB_CONFIG', serialize($db_config));
}
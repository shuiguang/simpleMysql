<?php
/**
 * mysql driver for php
 * @author shuiguang
 */
class DB
{
    /**
     * 是否执行sql语句还是直接输出完整sql语句,用于调试
     * @var bool
     */
    public $excute_sql = true;
    
    /**
     * 数据库连接标识
     * @var null
     */
    public static $link = null;
    
    /**
     * 可写缓存目录绝对路径
     * @var string
     */
    protected static $cache_dir = '';
    
    /**
     * 数据库连接配置
     * @var array
     */
    protected static $config = array();
    
    /**
     * 当前操作的表
     * @var string
     */
    protected static $table = '';
    
    /**
     * 当前操作的表所有字段
     * @var array
     */
    protected static $fields = array();
    
    /**
     * 所有操作的表所有字段缓存
     * @var array
     */
    protected static $fields_cache = array();
    
    /**
     * 查询参数列表
     * @var array
     */
    protected static $options = array('field'=>'','where'=>'', 'order'=>'', 'limit'=>'', 'group'=>'', 'having'=>'');
    
    /**
     * 当前执行的sql语句
     * @var string
     */
    protected $sql = '';
    
    /**
     * 当前执行的sql所用时间
     * @var string
     */
    protected $cost = '';
    
    /**
     * 自增字段标识
     * @var string
     */
    protected static $auto = 'yno';
    
    /**
     * 构造方法,自动连接数据库,实例化对象之后自动连接数据库
     * @param array $db_config
     * @return null
     */
    public function __construct($db_config = array())
    {
        self::$config = unserialize(DB_CONFIG);
        $this->connect($db_config);
    }
    
    /**
     * 析构方法,断开mysql连接
     * @param array $db_config
     * @return null
     */
    public function __destruct()
    {
        if(!is_null(self::$link))
        {
            //关闭连接
            self::$link = null;
        }
    }
    
    /**
     * 连接数据库或复用数据库连接
     * @param array $db_config
     * @return null
     * @throws Exception
     */
    public function connect($db_config = array())
    {
        //分2种情况：如果没有连接数据库则执行连接操作;如果已经连接当$config不为空时重新连接,当为空时直接返回已连接状态
        if(is_null(self::$link) || !empty($db_config))
        {
            $config = array_merge(self::$config, $db_config);
            //连接数据库查询,使用socket嵌套字连接时port参数无效
            $func = isset($config['pconnect']) && $config['pconnect'] ? 'mysql_pconnect' : 'mysql_connect';
            if(isset($config['socket']) && $config['socket'])
            {
                self::$link = $func($config['host'].':'.$config['socket'], $config['user'], $config['pwd']);
            }else{
                self::$link = $func($config['host'].':'.$config['port'], $config['user'], $config['pwd']);
            }
            if(!self::$link)
            {
                throw new Exception('DB::connect can not connect to DB!');
            }
            self::$config = $config;
            mysql_select_db($config['database'], self::$link);
            mysql_query('SET NAMES '.$config['charset']);
            //检测缓存目录
            if(is_dir(self::$config['cache_dir']) && is_writeable(self::$config['cache_dir']))
            {
                self::$cache_dir = $config['cache_dir'];
            }
        }
    }
    
    /**
     * 自动加载函数, 实现特殊操作
     * @param string $func
     * @param string $args
     * @return self
     * @throws Exception
     */
    public function __call($func, $args)
    {
        //连贯操作调用table() field() where() order() limit() group() having()方法
        if(in_array($func, array_keys(self::$options)))
        {
           self::$options[$func] = $args;
           return $this;
        }else if($func === 'table')
        {
            self::$options['table'] = array_shift($args);
            self::$table = $this->_add_special_char(self::$options['table']);
            $fields = array();
            $auto = 'yno';
            //预查询所有的字段
            $query = 'desc '.self::$table;
            if(isset(self::$fields_cache[self::$options['table']]))
            {
                self::$fields = self::$fields_cache[self::$options['table']]['fields'];
                self::$auto = self::$fields_cache[self::$options['table']]['auto'];
            }else{
                try{
                    $result = mysql_query($query, self::$link);
                }catch(Exception $e)
                {
                    return $this;
                }
                while($row = mysql_fetch_assoc($result))
                {
                    //考虑到联合主键
                    if($row['Key'] == 'PRI' && !isset($fields['pri']))
                    {
                        $fields['pri'] = strtolower($row['Field']);
                    }else{
                        $fields[] = strtolower($row['Field']);
                    }
                    if($row['Extra'] == 'auto_increment')
                    {
                        $auto = 'yes';
                    }
                }
                //如果表中没有主键,则将第一列当作主键
                if(!array_key_exists('pri', $fields))
                {
                    $fields['pri'] = array_shift($fields);
                }
                self::$fields = $fields;
                self::$auto = $auto;
                self::$fields_cache[self::$options['table']]['fields'] = $fields;
                self::$fields_cache[self::$options['table']]['auto'] = $auto;
            }
            return $this;
        }else if($func === 'field')
        {
           self::$options['field'] = array_shift($args);
           return $this;
        }
        //如果函数不存在, 则抛出异常
        throw new Exception('Call to undefined method DB::'.$func.'()');
    }
    
    /**
     * 按指定的条件获取结果集中的记录数,返回记录数值
     * @return int
     */
    public function total()
    {
        $where = '';
        $data = array();
        $args = func_get_args();
        if(count($args) > 0)
        {
            $where = $this->_comWhere($args);
            $data = $where['data'];
            $where = $where['where'];
        }else if(self::$options['where'] != '')
        {
            $where = $this->_comWhere(self::$options['where']);
            $data = $where['data'];
            $where = $where['where'];
        }
        $sql = 'SELECT COUNT(*) as count FROM '.self::$table.$where;
        //判断是否开启缓存
        if($this->_checkCache())
        {
            return $this->_readCache($sql, __METHOD__, $data);
        }else{
            return $this->query($sql, __METHOD__, $data);
        }
    }
    
    /**
     * 查询多条结果,返回二维数组
     * @return array
     */
    public function select()
    {
        $field_arr = self::$options['field'] != '' ? explode(',', self::$options['field'][0]) : self::$fields;
        $field_arr = array_map(array($this, '_add_special_char'), $field_arr);
        $fields = '';
        foreach($field_arr as $field)
        {
            $fields .= $this::$table.'.'.$field.',';
        }
        $fields = rtrim($fields, ',');
        $where = '';
        $data = array();
        $args = func_get_args();
        if(count($args) > 0)
        {
            $where = $this -> _comWhere($args);
            $data = $where['data'];
            $where = $where['where'];
        }else if(self::$options['where'] != '')
        {
            $where = $this->_comWhere(self::$options['where']);
            $data = $where['data'];
            $where = $where['where'];
        }
        $order = self::$options['order'] != '' ?  ' ORDER BY '.self::$options['order'][0] : '';
        $limit = self::$options['limit'] != '' ? $this->_comLimit(self::$options['limit']) : '';
        $group = self::$options['group'] != '' ? ' GROUP BY '.self::$options['group'][0] : '';
        $having = self::$options['having'] != '' ? ' HAVING '.self::$options['having'][0] : '';
        //修复没有传入field并且数据库连接失败的情况
        $sql = 'SELECT '.($fields ? $fields : '*').' FROM '.self::$table.$where.$group.$having.$order.$limit;
        //判断是否开启缓存
        if($this->_checkCache())
        {
            return $this->_readCache($sql, __METHOD__, $data);
        }else{
            return $this->query($sql, __METHOD__, $data);
        }
    }
    
    /**
     * 查询一条记录,返回一维数组,如果传入参数$pri则从主键中匹配
     * @param string $pri
     * @return array
     */
    public function find($pri = '')
    {
        $field_arr = self::$options['field'] != '' ? explode(',', self::$options['field'][0]) : self::$fields;
        $field_arr = array_map(array($this, '_add_special_char'), $field_arr);
        $fields = '';
        foreach($field_arr as $field)
        {
            $fields .= $this::$table.'.'.$field.',';
        }
        $fields = rtrim($fields, ',');
        if($pri == '')
        {
            $where = $this->_comWhere(self::$options['where']);
            $data = $where['data'];
            $where = self::$options['where'] != '' ? $where['where'] : '';
        }else{
            $where = ' where '.self::$fields['pri'].'=?';
            $data[] = $pri;
        }
        $order = self::$options['order'] != '' ?  ' ORDER BY '.self::$options['order'][0] : '';
        //修复没有传入field并且数据库连接失败的情况
        $sql = 'SELECT '.($fields ? $fields : '*').' FROM '.self::$table.$where.$order.' LIMIT 1';
        //判断是否开启缓存
        if($this->_checkCache())
        {
            return $this->_readCache($sql, __METHOD__, $data);
        }else{
            return $this->query($sql, __METHOD__, $data);
        }
    }
    
    /**
     * 向数据库中插入一条记录,$array为记录数组,$filter为是否开启htmlspecialchars处理,返回last_insert_id
     * @param array $array 
     * @param int $filter 
     * @return int
     */
    public function insert($array = null, $filter = 1)
    {
        if(is_null($array))
        {
            $array = array_merge($_GET, $_POST);
        }
        $array = self::strip_data($array, $filter);
        $field_arr = array_keys($array);
        $field_arr = array_map(array($this, '_add_special_char'), $field_arr);
        $fields = '';
        foreach($field_arr as $field)
        {
            $fields .= $this::$table.'.'.$field.',';
        }
        $fields = rtrim($fields, ',');
        $sql = 'INSERT INTO '.$this::$table.'('.$fields.') VALUES ('.implode(',', array_fill(0, count($array), '?')).')';
        return $this->query($sql, __METHOD__, array_values($array));
    }
    
    /**
     * 更新数据表中指定条件的记录,如果没有指定where则取第一个字段(通常为主键)为条件更新,返回影响行数
     * @param array $array 
     * @param int $filter 
     * @return int
     */
    public function update($array = null, $filter = 1)
    {
        if(is_null($array))
        {
            $array = array_merge($_GET, $_POST);
        }
        $data = array();
        if(is_array($array))
        {
            if(array_key_exists(self::$fields['pri'], $array))
            {
                $pri_value = $array[self::$fields['pri']];
                unset($array[self::$fields['pri']]);
            }
            $array = self::strip_data($array, $filter);
            $s = '';
            foreach ($array as $k => $v)
            {
                $s .= $this::$table.'.'.$this->_add_special_char($k).'=?,';
                $data[] = $v;
            }
            $s = rtrim($s, ',');
            $setfield = $s;
        }else{
            $setfield = $array;
            $pri_value = '';
        }
        $order = self::$options['order'] != '' ?  ' ORDER BY '.self::$options['order'][0] : '';
        $limit = self::$options['limit'] != '' ? self::_comLimit(self::$options['limit']) : '';
        if(self::$options['where'] != '')
        {
            $where = $this->_comWhere(self::$options['where']);
            $sql = 'UPDATE '.self::$table.' SET '.$setfield.$where['where'];
            if(!empty($where['data']))
            {
                foreach($where['data'] as $v)
                {
                    $data[] = $v;
                }
            }
            $sql .= $order.$limit;
        }else
        {
            $sql = 'UPDATE '.self::$table.' SET '.$setfield.' WHERE '.self::$fields['pri'].'=?';
            $data[] = $pri_value;
        }
        return $this->query($sql, __METHOD__, $data);
    }
    
    /**
     * 删除满足条件的记录
     * @return null
     */
    public function delete()
    {
        $where = '';
        $data = array();
        $args = func_get_args();
        if(count($args)>0)
        {
            $where = $this->_comWhere($args);
            $data = $where['data'];
            $where = $where['where'];
        }else if(self::$options['where'] != '')
        {
            $where = $this->_comWhere(self::$options['where']);
            $data = $where['data'];
            $where = $where['where'];
        }
        $order = self::$options['order'] != '' ?  ' ORDER BY '.self::$options['order'][0] : '';
        $limit = self::$options['limit'] != '' ? self::_comLimit(self::$options['limit']) : '';
        if($where == '' && $limit == '')
        {
            $where = ' where '.self::$fields['pri']."=''";
        }
        $sql = 'DELETE FROM '.self::$table.$where.$order.$limit;
        return $this->query($sql, __METHOD__, $data);
    }
    
    /**
     * 执行SQL
     * @param  string $sql
     * @param  string $method
     * @param  array  $data
     * @return mixed
     * @throws Exception
     */
    public function query($sql, $method='', $data=array())
    {
        $value = self::_escape_string_array($data);
        $startTime = microtime(true);
        $this->_setNull();
        $sql = $this->sql($sql, $value);
        $marr = explode('::', $method);
        $method = strtolower(array_pop($marr));
        if(trim(strtolower($method)) == 'total')
        {
            $sql = preg_replace('/select.*?from/i','SELECT count(*) as count FROM', $sql);
        }
        //是否执行sql语句还是调试输出
        if(!$this->excute_sql)
        {
            return '<pre><font color="red">[debug]'.$sql.'</font></pre>';
        }
        $this->sql = $sql;
        $returnv = $result = null;
        //修复bug：判断是否self::$link是否为null,如果为null则尝试以已有配置连接数据库
        $this->connect();
        try{
            $result = mysql_query($sql, self::$link);
        }catch(Exception $e)
        {
            $sql_error = mysql_errno(self::$link);
            if(!empty($sql_error))
            {
                //服务端断开时带参数强行重连一次
                if($sql_error == 2006 || $sql_error == 2013)
                    {
                    //必须关闭之后才能重新连接成功
                    mysql_close(self::$link);
                    $this->connect(self::$config);
                    $result = mysql_query($sql, self::$link);
                    if(mysql_errno(self::$link))
                    {
                        throw new Exception("DB::query error\n".mysql_error(self::$link));
                    }
                }else{
                    throw new Exception("DB::query error\n".mysql_error(self::$link));
                }
            }
        }
        switch($method)
        {
            case 'select':                          //查所有满足条件的
                $returnv = $this->_getAll($result);
                break;
            case 'find':                            //只要一条记录的
                $returnv = $this->_getOne($result);
                break;
            case 'total':                           //返回总记录数
                $row = $this->_getOne($result);
                $returnv = $row['count'];
                break;
            case 'insert':                          //插入数据 返回最后插入的ID
                if(self::$auto == 'yes')
                {
                    $returnv = mysql_insert_id(self::$link);
                }else{
                    $returnv = $result;
                }
                break;
            case 'delete':
            case 'update':                          //update 
                $returnv = mysql_affected_rows(self::$link);
                break;
            default:
                $returnv = $result;
        }
        $stopTime = microtime(true);
        $this->cost = round(($stopTime - $startTime) , 4);
        //释放结果集
        if(is_resource($result))
        {
            mysql_free_result($result);
        }
        return $returnv;
    }
    
    /**
     * 格式化完整的SQL语句,调试参考
     * @param  string $sql
     * @param  array  $params_arr
     * @return string
     */
    protected function sql($sql, $params_arr)
    {
        if(false === strpos($sql, '?') || count($params_arr) == 0)  return $sql;
        // 进行 ? 的替换,变量替换
        if(false === strpos($sql, '%'))
        {
            // 不存在%,替换问号为s%,进行字符串格式化
            $sql = str_replace('?', "'%s'", $sql);
            array_unshift($params_arr, $sql);
            return call_user_func_array('sprintf', $params_arr);    //调用函数和所用参数
        }
    }
    
    /**
     * 获取上一次执行的sql语句,调试参考
     * @return string
     */
    public function lastSQL()
    {
        return $this->sql;
    }
    
    /**
     * 获取上一次执行的sql语句所用时间,调试参考
     * @return string
     */
    public function lastCost()
    {
        return $this->cost;
    }
        
    /**
     * 获取多所有记录数组
     * @param  object $res
     * @return array()
     */
    private function _getAll($res)
    {
        $result = array();
        //取出所有记录返回二维数组
        while($row = mysql_fetch_assoc($res))
        {
            $result[] = $row;
        }
        return $result;
    }

    /**
     * 获取一条记录数组
     * @param  object $res
     * @return array()
     */
    private function _getOne($res)
    {
        $result = array();
        //取出一条记录返回一维数组
        while($row = mysql_fetch_assoc($res))
        {
            $result = $row;
            break;
        }
        return $result;  //一维关联数组
    }
    
    /**
     * 处理limit参数
     * @param  array $args
     * @return string
     */
    private function _comLimit($args)
    {
        if(count($args) == 2)
        {
            return ' LIMIT '.$args[0].','.$args[1];
        }else if(count($args)==1){
            return ' LIMIT '.$args[0];
        }else{
            return '';
        }
    }

    /**
     * 用来组合SQL语句中的where条件
     * @param  array $args
     * @return array
     */
    private function _comWhere($args)
    {
        $where = ' WHERE ';
        $data = array();
        if(empty($args))
        {
            return array('where' => '', 'data' => $data);
        }
        foreach($args as $option)
        {
            if(empty($option))
            {
                $where = '';                    //条件为空,返回空字符串；如'',0,false 返回： ''
                continue;
            }else if(is_array($option))         //where(array('id' => '1,2,3,4'), array('check' => '1'))
            {
                if(isset($option[0]))
                {
                    //如果是1维数组,array(1,2,3,4);
                    $where .= $this::$table.'.'.$this->_add_special_char(self::$fields['pri']).' IN('.implode(',', array_fill(0, count($option), '?')).')';
                    $data = $option;
                    continue;
                }
                foreach($option as $k => $v )
                {
                    if(is_array($v))
                    {
                        //如果是2维数组,array('uid'=>array(1,2,3,4))
                        $where .= $this::$table.'.'.$this->_add_special_char($k).' IN('.implode(',', array_fill(0, count($v), '?')).')';
                        foreach($v as $val)
                        {
                            $data[] = $val;
                        }
                    }else{
                        //修正非正常字符显示为空格导致的查询条件
                        $v = str_replace(' ', ' ', $v);
                        if(strpos($k, ' '))
                        {
                            //array('add_time >'=>'2010-10-1'),条件key中带 > < =符号
                            $field = trim($k, '>=< ');
                            $k = str_replace($field, $this::$table.'.'.$this->_add_special_char($field), $k);
                            $where .= $k.'?';
                            $data[] = $v;
                        }else if(isset($v[0]) && $v[0] == '%' && substr($v, -1) == '%')
                        {
                            //array('name'=>'%中%'),LIKE操作
                            $where .= $this::$table.'.'.$this->_add_special_char($k).' LIKE ?';
                            $data[] = $v;
                        }else{
                            //array('res_type'=>1)
                            $where .= $this::$table.'.'.$this->_add_special_char($k).'=?';
                            $data[] = $v;
                        }
                    }
                    $where .= ' AND ';
                }
                $where = rtrim($where, 'AND ');
                $where .= ' OR ';
                continue;
            }
        }
        $where = rtrim($where, 'OR ');
        return array('where' => $where, 'data' => $data);
    }
    
    /**
     * 用于重置成员属性
     * @return null
     */
    protected function _setNull()
    {
        self::$options = array('field'=>'', 'where'=>'', 'order'=>'', 'limit'=>'', 'group'=>'', 'having'=>'');
    }

    /**
     * 对字段两边加反引号,以保证数据库安全
     * @param  string $value
     * @return string
     */
    public function _add_special_char($value)
    {
        if('*' == $value || false !== strpos($value, '(') || false !== strpos($value, '.') || false !== strpos ( $value, '`'))
        {
            //不处理包含* 或者 使用了sql方法。
        }else{
            $value = '`'.trim($value).'`';
        }
        return $value;
    }

    /**
     * 修复bind_param第二个参数为引用的问题
     * @param  array $arr
     * @return array
     */
    public function _refValues($arr)
    {
        if(version_compare(PHP_VERSION, '5.3.0') >= 0)
        {
            $refs = array();
            foreach($arr as $key => $value)
            {
                $refs[$key] = &$arr[$key];
            }
            return $refs;
        }
        return $arr;
    }
    
    /**
     * MyISAM表读锁定,该线程和所有其他线程只能从表中读数据,不能进行任何写操作
     * @param  string $table
     * @return null
     */
    public function readLock($table)
    {
        $this->query('LOCK TABLE `'.$table.'` READ');
    }
    
    /**
     * MyISAM表写锁定,那么只有拥有这个锁的线程可以从表中读取和写表,其它的线程被阻塞
     * @param  string $table
     * @return null
     */
    public function writeLock($table)
    {
        $this->query('LOCK TABLE `'.$table.'` WRITE');
    }
    
    /**
     * MyISAM表解锁,解除读锁定或写锁定,脚本结束系统会自动释放锁
     * @return null
     */
    public function unlock()
    {
        $this->query('UNLOCK TABLES');
    }

    /**
     * 事务开始
     * @return null
     */
    public function beginTransaction()
    {
        mysql_query('SET AUTOCOMMIT=0');
    }
    
    /**
     * 事务提交
     * @return null
     */
    public function commit()
    {
        mysql_query('COMMIT');
        mysql_query('SET AUTOCOMMIT=1');
    }
    
    /**
     * 事务回滚
     * @return null
     */
    public function rollBack()
    {
        mysql_query('ROLLBACK');
        mysql_query('SET AUTOCOMMIT=1');
    }
    
    /**
     * 过滤数组元素单双引号
     * @param array $array
     * @return array
     */
    public static function _escape_string_array($array)
    {
        if(empty($array))
        {
            return array();
        }
        $value = array();
        foreach($array as $val)
        {
            //去斜杠
            $val = stripslashes($val);
            //加斜杠
            $value[] = mysql_real_escape_string($val);
        }
        return $value;
    }
    
    /**
     * 过滤参数,filter = 1 去除 " ' 和 HTML 实体, 0则不变
     * @param array $array
     * @param int $filter
     * @return array
     */
    public static function strip_data($array, $filter)
    {
        $arr = array();
        if(strtolower(self::$config['charset']) == 'utf8')
        {
            $char='UTF-8';
        }else{
            $char='ISO-8859-1';
        }
        foreach($array as $key => $value)
        {
            $key = strtolower($key);
            if(in_array($key, self::$fields))                   //过滤不存在的参数
            {
                if(is_array($filter) && !empty($filter))
                {
                    if(in_array($key, $filter))
                    {
                        $arr[$key] = $value;    
                    }else
                    {
                        if(version_compare(PHP_VERSION, '5.4.0') >= 0)
                        {
                            $arr[$key] = stripslashes(htmlspecialchars($value, ENT_COMPAT, $char));
                        }else{
                            $arr[$key] = stripslashes(htmlspecialchars($value));
                        }
                    }
                }else if(!$filter)
                {
                    $arr[$key] = $value;
                }else{
                    if(version_compare(PHP_VERSION, '5.4.0') >= 0)
                    {
                        $arr[$key] = stripslashes(htmlspecialchars($value, ENT_COMPAT, $char));
                    }else{
                        $arr[$key] = stripslashes(htmlspecialchars($value));
                    }
                }
            }
        }
        return $arr;
    }

    /**
     * 获取数据库的版本
     * @return string
     */
    public static function dbVersion()
    {
        $sql = 'SELECT VERSION()';
        $result = mysql_query($sql);
        while($row = mysql_fetch_assoc($result))
        {
            return $row['VERSION()'];
        }
    }
    
    /**
     * 获取数据库使用大小
     * @return string
     */
    public static function dbSize()
    {
        $sql = 'SHOW TABLE STATUS FROM '.self::$config['database'];
        $result = mysql_query($sql);
        $size = 0;
        while($row = mysql_fetch_assoc($result))
        {
            $size += $row['Data_length'] + $row['Index_length'];
        }
        return self::tosize($size);
    }
    
    /**
     * 格式化字节
     * @param int $bytes
     * @return string
     */
    public static function tosize($bytes)                   //自定义一个文件大小单位转换函数
    {
        if ($bytes >= pow(2,40))                            //如果提供的字节数大于等于2的40次方,则条件成立
        {
            $return = round($bytes / pow(1024,4), 2);       //将字节大小转换为同等的T大小
            $suffix = 'TB';                                 //单位为TB
        }elseif ($bytes >= pow(2,30)){                      //如果提供的字节数大于等于2的30次方,则条件成立
            $return = round($bytes / pow(1024,3), 2);       //将字节大小转换为同等的G大小
            $suffix = 'GB';                                 //单位为GB
        }elseif ($bytes >= pow(2,20)){                      //如果提供的字节数大于等于2的20次方,则条件成立
            $return = round($bytes / pow(1024,2), 2);       //将字节大小转换为同等的M大小
            $suffix = 'MB';                                 //单位为MB
        }elseif ($bytes >= pow(2,10)){                      //如果提供的字节数大于等于2的10次方,则条件成立
            $return = round($bytes / pow(1024,1), 2);       //将字节大小转换为同等的K大小
            $suffix = 'KB';                                 //单位为KB
        }else{                                              //否则提供的字节数小于2的10次方,则条件成立
            $return = $bytes;                               //字节大小单位不变
            $suffix = 'Byte';                               //单位为Byte
        }
        return $return.' '.$suffix;                         //返回合适的文件大小和单位
    }
    
    /**
     * 获取缓存名称
     * @param string $sql
     * @return string
     */
    public function getCacheName($sql)
    {
        return self::$cache_dir.'/'.md5($sql).'.tmp';
    }
    
    /**
     * 读取缓存
     * @param string $sql
     * @param string $method
     * @param array $data
     * @return string
     */
    protected function _readCache($sql, $method='', $data=array())
    {
        $value = self::_escape_string_array($data);
        $startTime = microtime(true);
        $this->_setNull();
        $sql = $this->sql($sql, $value);
        $marr = explode('::', $method);
        $method = strtolower(array_pop($marr));
        if(trim(strtolower($method)) == 'total')
        {
            $sql = preg_replace('/select.*?from/i','SELECT count(*) as count FROM', $sql);
        }
        $this->sql = $sql;
        $file = $this->getCacheName($sql);
        //缓存不存在或过期
        if(!file_exists($file) || (filemtime($file) + self::$config['cache_time']) < time())
        {
            $result = $this->query($sql, $method, $data);
            $this->_writeCache($sql, $method='', $result);
            return $result;
        }else{
            $result = file_get_contents($file);
            return unserialize($result);
        }
    }
    
    /**
     * 写入缓存
     * @param string $sql
     * @param string $method
     * @param array $data
     * @return bool
     */
    protected function _writeCache($sql, $method='', $data='')
    {
        if(self::$config['cache_time'] > 0)
        {
            $file = $this->getCacheName($sql);
            $data = serialize($data);
            return file_put_contents($file, $data);
        }
    }
    
    /**
     * 检测缓存配置,仅当缓存目录存在且缓存时间大于0时成立
     * @return bool
     */
    protected function _checkCache()
    {
        return self::$cache_dir && (self::$config['cache_time'] > 0);
    }
    
    /**
     * 公开设置缓存时间
     * @param  int $seconds
     * @return null
     */
    public function setCacheTime($seconds = 86400)
    {
        self::$config['cache_time'] = $seconds;
    }
    
    /**
     * 清除缓存
     * @param  string $sql
     * @return bool
     */
    public function clearCache($sql)
    {
        $file = $this->getCacheName($sql);
        if(file_exists($file))
        {
            return unlink($file);
        }else{
            return true;
        }
    }
}


<?php
/**
 * mysql pdo driver for php
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
    public $link = null;
    
    /**
     * 可写缓存目录绝对路径
     * @var string
     */
    protected $cache_dir = '';
    
    /**
     * 数据库连接配置
     * @var array
     */
    protected $config = array();
    
    /**
     * 当前操作的表
     * @var string
     */
    protected $table = '';
    
    /**
     * 当前操作的表所有字段
     * @var array
     */
    protected $fields = array();
    
    /**
     * 所有操作的表所有字段缓存
     * @var array
     */
    protected $fields_cache = array();
    
    /**
     * 查询参数列表
     * @var array
     */
    protected $options = array('field'=>'','where'=>'', 'order'=>'', 'limit'=>'', 'group'=>'', 'having'=>'');
    
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
    protected $auto = 'yno';
    
    /**
     * 构造方法,自动连接数据库,实例化对象之后自动连接数据库
     * @param array $db_config
     * @return null
     */
    public function __construct($db_config = array())
    {
        $this->config = unserialize(DB_CONFIG);
        $this->connect($db_config);
    }
    
    /**
     * 析构方法,断开mysql连接
     * @param array $db_config
     * @return null
     */
    public function __destruct()
    {
        if(!is_null($this->link))
        {
            //关闭连接
            $this->link = null;
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
        if(is_null($this->link) || !empty($db_config))
        {
            $config = array_merge($this->config, $db_config);
            //连接数据库查询
            $dsn = 'mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'];
            if(isset($config['socket']) && $config['socket'])
            {
                $dsn = 'mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';unix_socket='.$config['socket'];
            }else{
                $dsn = 'mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'];
            }
            $this->link = new PDO($dsn, $config['user'], $config['pwd'], array(PDO::ATTR_PERSISTENT => $config['pconnect']));
            if(!$this->link)
            {
                throw new Exception('DB::connect can not connect to DB!');
            }
            $this->link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->config = $config;
            $this->link->query('SET NAMES '.$config['charset']);
            //检测缓存目录
            if(is_dir($this->config['cache_dir']) && is_writeable($this->config['cache_dir']))
            {
                $this->cache_dir = $config['cache_dir'];
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
        if(in_array($func, array_keys($this->options)))
        {
           $this->options[$func] = $args;
           return $this;
        }else if($func === 'table')
        {
            $this->options['table'] = array_shift($args);
            $this->table = $this->_add_special_char($this->options['table']);
            $fields = array();
            $auto = 'yno';
            //预查询所有的字段
            $query = 'desc '.$this->table;
            if(isset($this->fields_cache[$this->options['table']]))
            {
                $this->fields = $this->fields_cache[$this->options['table']]['fields'];
                $this->auto = $this->fields_cache[$this->options['table']]['auto'];
            }else{
                $stmt = $this->link->prepare($query);
                try{
                    $stmt->execute();
                }catch(Exception $e)
                {
                    return $this;
                }
                while($row = $stmt->fetch(PDO::FETCH_ASSOC))
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
                $this->fields = $fields;
                $this->auto = $auto;
                $this->fields_cache[$this->options['table']]['fields'] = $fields;
                $this->fields_cache[$this->options['table']]['auto'] = $auto;
            }
            return $this;
        }else if($func === 'field')
        {
           $this->options['field'] = array_shift($args);
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
        }else if($this->options['where'] != '')
        {
            $where = $this->_comWhere($this->options['where']);
            $data = $where['data'];
            $where = $where['where'];
        }
        $sql = 'SELECT COUNT(*) as count FROM '.$this->table.$where;
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
        $field_arr = $this->options['field'] != '' ? explode(',', $this->options['field'][0]) : $this->fields;
        $field_arr = array_map(array($this, '_add_special_char'), $field_arr);
        $fields = '';
        foreach($field_arr as $field)
        {
            $fields .= $this->table.'.'.$field.',';
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
        }else if($this->options['where'] != '')
        {
            $where = $this->_comWhere($this->options['where']);
            $data = $where['data'];
            $where = $where['where'];
        }
        $order = $this->options['order'] != '' ?  ' ORDER BY '.$this->options['order'][0] : '';
        $limit = $this->options['limit'] != '' ? $this->_comLimit($this->options['limit']) : '';
        $group = $this->options['group'] != '' ? ' GROUP BY '.$this->options['group'][0] : '';
        $having = $this->options['having'] != '' ? ' HAVING '.$this->options['having'][0] : '';
        //修复没有传入field并且数据库连接失败的情况
        $sql = 'SELECT '.($fields ? $fields : '*').' FROM '.$this->table.$where.$group.$having.$order.$limit;
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
        $field_arr = $this->options['field'] != '' ? explode(',', $this->options['field'][0]) : $this->fields;
        $field_arr = array_map(array($this, '_add_special_char'), $field_arr);
        $fields = '';
        foreach($field_arr as $field)
        {
            $fields .= $this->table.'.'.$field.',';
        }
        $fields = rtrim($fields, ',');
        if($pri == '')
        {
            $where = $this->_comWhere($this->options['where']);
            $data = $where['data'];
            $where = $this->options['where'] != '' ? $where['where'] : '';
        }else{
            $where = ' where '.$this->fields['pri'].'=?';
            $data[] = $pri;
        }
        $order = $this->options['order'] != '' ?  ' ORDER BY '.$this->options['order'][0] : '';
        //修复没有传入field并且数据库连接失败的情况
        $sql = 'SELECT '.($fields ? $fields : '*').' FROM '.$this->table.$where.$order.' LIMIT 1';
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
        $array = $this->strip_data($array, $filter);
        $field_arr = array_keys($array);
        $field_arr = array_map(array($this, '_add_special_char'), $field_arr);
        $fields = '';
        foreach($field_arr as $field)
        {
            $fields .= $this->table.'.'.$field.',';
        }
        $fields = rtrim($fields, ',');
        $sql = 'INSERT INTO '.$this->table.'('.$fields.') VALUES ('.implode(',', array_fill(0, count($array), '?')).')';
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
            if(array_key_exists($this->fields['pri'], $array))
            {
                $pri_value = $array[$this->fields['pri']];
                unset($array[$this->fields['pri']]);
            }
            $array = $this->strip_data($array, $filter);
            $s = '';
            foreach ($array as $k => $v)
            {
                $s .= $this->table.'.'.$this->_add_special_char($k).'=?,';
                $data[] = $v;
            }
            $s = rtrim($s, ',');
            $setfield = $s;
        }else{
            $setfield = $array;
            $pri_value = '';
        }
        $order = $this->options['order'] != '' ?  ' ORDER BY '.$this->options['order'][0] : '';
        $limit = $this->options['limit'] != '' ? $this->_comLimit($this->options['limit']) : '';
        if($this->options['where'] != '')
        {
            $where = $this->_comWhere($this->options['where']);
            $sql = 'UPDATE '.$this->table.' SET '.$setfield.$where['where'];
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
            $sql = 'UPDATE '.$this->table.' SET '.$setfield.' WHERE '.$this->fields['pri'].'=?';
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
        }else if($this->options['where'] != '')
        {
            $where = $this->_comWhere($this->options['where']);
            $data = $where['data'];
            $where = $where['where'];
        }
        $order = $this->options['order'] != '' ?  ' ORDER BY '.$this->options['order'][0] : '';
        $limit = $this->options['limit'] != '' ? $this->_comLimit($this->options['limit']) : '';
        if($where == '' && $limit == '')
        {
            $where = ' where '.$this->fields['pri']."=''";
        }
        $sql = 'DELETE FROM '.$this->table.$where.$order.$limit;
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
        $value = $this->_escape_string_array($data);
        $startTime = microtime(true);
        $this->_setNull();
        $memkey = $sql;                             //使用prepare方法不能使用完整sql语句
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
        
        $stmt = $this->link->prepare($memkey);          //准备好一个语句,不受mysql连接状态影响
        try{
            //这一句可能会抛出异常到页面,$this->link->errorInfo()无法捕捉到异常
            $result = @$stmt->execute($value);
        }catch(Exception $e){
            //服务端断开时带参数强行重连一次
            if($e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013)
            {
                //pdo连接没有关闭的方法,置为null
                $this->link = null;
                $this->connect($this->config);
                $stmt = $this->link->prepare($memkey);
                try{
                    $result = @$stmt->execute($value);
                }catch(Exception $second)
                {
                    throw new Exception("DB::query error\n".$second->errorInfo[2]);
                }
            }else{
                throw new Exception("DB::query error\n".$e->errorInfo[2]);
            }
        }
        $returnv = null;
        switch($method)
        {
            case 'select':                          //查所有满足条件的
                $returnv = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
            case 'find':                            //只要一条记录的
                $returnv = $stmt->fetch(PDO::FETCH_ASSOC);
                break;
            case 'total':                           //返回总记录数
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $returnv = $row['count'];
                break;
            case 'insert':                          //插入数据 返回最后插入的ID
                if($this->auto == 'yes')
                {
                    $returnv = $this->link->lastInsertId();
                }else{
                    $returnv = $result;
                }
                break;
            case 'delete':
            case 'update':                          //update 
                $returnv = $stmt->rowCount();
                break;
            default:
                $returnv = $result;
        }
        $stopTime = microtime(true);
        $this->cost = round(($stopTime - $startTime) , 4);
        //释放内存
        $stmt = null;
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
    private function _getAll($stmt)
    {
        $result = array();
        $field = $stmt->result_metadata()->fetch_fields();
        $out = array();
        //获取所有结果集中的字段名
        $fields = array();
        foreach ($field as $val)
        {
            $fields[] = &$out[$val->name];
        }
        //用所有字段名绑定到bind_result方上
        call_user_func_array(array($stmt, 'bind_result'), $fields);
        while($stmt->fetch())
        {
            $t = array();  //一条记录关联数组
            foreach($out as $key => $val)
            {
                $t[$key] = $val;
            }
            $result[] = $t;
        }
        return $result;
    }

    /**
     * 获取一条记录数组
     * @param  object $res
     * @return array()
     */
    private function _getOne($stmt)
    {
        $result = array();
        $field = $stmt->result_metadata()->fetch_fields();
        $out = array();
        //获取所有结果集中的字段名
        $fields = array();
        foreach ($field as $val)
        {
            $fields[] = &$out[$val->name];
        }
        //用所有字段名绑定到bind_result方上
        call_user_func_array(array($stmt, 'bind_result'), $fields);
        $stmt->fetch();
        foreach($out as $key => $val)
        {
            $result[$key] = $val;
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
                    $where .= $this->table.'.'.$this->_add_special_char($this->fields['pri']).' IN('.implode(',', array_fill(0, count($option), '?')).')';
                    $data = $option;
                    continue;
                }
                foreach($option as $k => $v )
                {
                    if(is_array($v))
                    {
                        //如果是2维数组,array('uid'=>array(1,2,3,4))
                        $where .= $this->table.'.'.$this->_add_special_char($k).' IN('.implode(',', array_fill(0, count($v), '?')).')';
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
                            $k = str_replace($field, $this->table.'.'.$this->_add_special_char($field), $k);
                            $where .= $k.'?';
                            $data[] = $v;
                        }else if(isset($v[0]) && $v[0] == '%' && substr($v, -1) == '%')
                        {
                            //array('name'=>'%中%'),LIKE操作
                            $where .= $this->table.'.'.$this->_add_special_char($k).' LIKE ?';
                            $data[] = $v;
                        }else{
                            //array('res_type'=>1)
                            $where .= $this->table.'.'.$this->_add_special_char($k).'=?';
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
        $this->options = array('field'=>'','where'=>'', 'order'=>'', 'limit'=>'', 'group'=>'', 'having'=>'');
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
        $this->link->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
        $this->link->beginTransaction();
    }
    
    /**
     * 事务提交
     * @return null
     */
    public function commit()
    {
        $this->link->commit();
        $this->link->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
    }
    
    /**
     * 事务回滚
     * @return null
     */
    public function rollBack()
    {
        $this->link->rollBack();
        $this->link->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
    }
    
    /**
     * 过滤数组元素单双引号
     * @param array $array
     * @return array
     */
    public function _escape_string_array($array)
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
            $value[] = addslashes($val);
        }
        return $value;
    }
    
    /**
     * 过滤参数,filter = 1 去除 " ' 和 HTML 实体, 0则不变
     * @param array $array
     * @param int $filter
     * @return array
     */
    public function strip_data($array, $filter)
    {
        $arr = array();
        if(strtolower($this->config['charset']) == 'utf8')
        {
            $char='UTF-8';
        }else{
            $char='ISO-8859-1';
        }
        foreach($array as $key => $value)
        {
            $key = strtolower($key);
            if(in_array($key, $this->fields))                   //过滤不存在的参数
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
    public function dbVersion()
    {
        return $this->link->getAttribute(PDO::ATTR_SERVER_VERSION);
    }
    
    /**
     * 获取数据库使用大小
     * @return string
     */
    public function dbSize()
    {
        $sql = 'SHOW TABLE STATUS FROM ' . $this->config['database'];
        $stmt = $this->link->prepare($sql);
        $stmt->execute();
        $size = 0;
        while($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $size += $row['Data_length'] + $row['Index_length'];
        }
        return $this->tosize($size);
    }
    
    /**
     * 格式化字节
     * @param int $bytes
     * @return string
     */
    public function tosize($bytes)                          //自定义一个文件大小单位转换函数
    {
        if ($bytes >= pow(2,40))                            //如果提供的字节数大于等于2的40次方,则条件成立
        {
            $return = round($bytes / pow(1024,4), 2);       //将字节大小转换为同等的T大小
            $suffix = 'TB';                                 //单位为TB
        } elseif ($bytes >= pow(2,30)){                     //如果提供的字节数大于等于2的30次方,则条件成立
            $return = round($bytes / pow(1024,3), 2);       //将字节大小转换为同等的G大小
            $suffix = 'GB';                                 //单位为GB
        } elseif ($bytes >= pow(2,20)) {                    //如果提供的字节数大于等于2的20次方,则条件成立
            $return = round($bytes / pow(1024,2), 2);       //将字节大小转换为同等的M大小
            $suffix = 'MB';                                 //单位为MB
        } elseif ($bytes >= pow(2,10)) {                    //如果提供的字节数大于等于2的10次方,则条件成立
            $return = round($bytes / pow(1024,1), 2);       //将字节大小转换为同等的K大小
            $suffix = 'KB';                                 //单位为KB
        } else {                                            //否则提供的字节数小于2的10次方,则条件成立
            $return = $bytes;                               //字节大小单位不变
            $suffix = 'Byte';                               //单位为Byte
        }
        return $return.' '.$suffix;                        //返回合适的文件大小和单位
    }
    
    /**
     * 获取缓存名称
     * @param string $sql
     * @return string
     */
    public function getCacheName($sql)
    {
        return $this->cache_dir.'/'.md5($sql).'.tmp';
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
        $value = $this->_escape_string_array($data);
        $startTime = microtime(true);
        $this->_setNull();
        $memkey = $sql;                                         //如果缓存不存在需要传入原始sql
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
        if(!file_exists($file) || (filemtime($file) + $this->config['cache_time']) < time())
        {
            $result = $this->query($memkey, $method, $data);
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
        if($this->config['cache_time'] > 0)
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
        return $this->cache_dir && ($this->config['cache_time'] > 0);
    }
    
    /**
     * 公开设置缓存时间
     * @param  int $seconds
     * @return null
     */
    public function setCacheTime($seconds = 86400)
    {
        $this->config['cache_time'] = $seconds;
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


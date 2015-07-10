<?php
/**
 * simple page for php
 * @author shuiguang
 */
class SimplePage
{
    public     $first_row;              //起始行数
    public     $list_rows;              //列表每页显示行数
    protected  $total_pages;            //总页数
    protected  $total_rows;             //总行数
    protected  $now_page;               //当前页数
    protected  $method  = 'defalut';    //处理情况 ajax分页 html分页(静态化时) 普通get方式 
    protected  $parameter = '';
    protected  $page_name;              //分页参数的名称
    protected  $ajax_func_name;
    public     $plus = 3;               //分页偏移量
    protected  $url;                    //特殊情况需要手动指定链接
    protected  $href;                   //指定分页a标签的href属性,默认为javascript:void(0);
    
    /**
     * 构造函数
     * @param unknown_type $data
     */
    public function __construct($data = array())
    {
        $this->total_rows        = (int)$data['total_rows'];
        $this->parameter         = !empty($data['parameter']) ? $data['parameter'] : '';
        $this->list_rows         = !empty($data['list_rows']) && $data['list_rows'] <= 100 ? $data['list_rows'] : 15;
        $this->total_pages       = ceil($this->total_rows / $this->list_rows);
        $this->page_name         = !empty($data['page_name']) ? $data['page_name'] : 'page';
        $this->ajax_func_name    = !empty($data['ajax_func_name']) ? $data['ajax_func_name'] : '';
        $this->method            = !empty($data['method']) ? $data['method'] : '';
        $this->url               = !empty($data['url']) ? $data['url'] : NULL;
        $this->href              = !empty($data['href']) ? $data['href'] : 'javascript:void(0);';
        
        /* 当前页面 */
        if(!empty($data['now_page']))
        {
            $this->now_page = intval($data['now_page']);
        }else{
            $this->now_page = !empty($_GET[$this->page_name]) ? intval($_GET[$this->page_name]) : 1;
        }
        $this->now_page = $this->now_page <= 0 ? 1 : $this->now_page;
        if(!empty($this->total_pages) && $this->now_page > $this->total_pages)
        {
            $this->now_page = $this->total_pages;
        }
        $this->first_row = $this->list_rows * ($this->now_page - 1);
    }   

    /**
     * 得到当前连接
     * @param $page
     * @param $text
     * @return string
     */
    protected function _get_link($page, $text)
    {
        switch($this->method)
        {
            case 'ajax':
                $parameter = '';
                if($this->parameter)
                {
                    if(is_array($this->parameter))
                    {
                        $attach_params = $this->parameter;
                        //检测单双引号
                        $all_params = implode('', $attach_params);
                        if(strpos($all_params, "'") !== false)
                        {
                            foreach($attach_params as &$para)
                            {
                                $para = "'".str_replace(array("'", '"', '/'), array("\\'", "\\'", '\\/'), $para)."'";
                            }
                            $parameter = ','.implode(',', $attach_params);
                            return '<a '.($this->ajax_func_name ? 'onclick="'.$this->ajax_func_name.'(\''.$page.'\''.$parameter.');return false;"' : '').' href="'.$this->href.'">'.$text.'</a>'."\n";
                        }else if(strpos($all_params, '"') !== false){
                            foreach($attach_params as &$para)
                            {
                                $para = '"'.str_replace(array('"', "'", '/'), array('\\"', '\\"', '\\/'), $para).'"';
                            }
                            $parameter = ','.implode(',', $attach_params);
                            return "<a ".($this->ajax_func_name ? "onclick='".$this->ajax_func_name."(\"".$page."\"".$parameter.");return false;'" : "")." href='".$this->href."'>".$text."</a>"."\n";
                        }
                    }else{
                        $parameter = ','.$this->parameter;
                    }
                }
                return '<a '.($this->ajax_func_name ? 'onclick="'.$this->ajax_func_name.'(\''.$page.'\''.$parameter.');return false;"' : '').' href="'.$this->href.'">'.$text.'</a>'."\n";
            break;
            case 'html':
                $url = str_replace('?', $page, $this->parameter);
                return '<a href="'.$url. '">'.$text.'</a>'."\n";
            break;
            default:
                return '<a href="'.$this->_get_url($page).'">'.$text.'</a>'."\n";
            break;
        }
    }
    
    /**
     * 设置当前页面链接
     */
    protected function _set_url()
    {
        $url = $_SERVER['REQUEST_URI'].(strpos($_SERVER['REQUEST_URI'],'?') ? '' : "?").$this->parameter;
        $parse = parse_url($url);
        if(isset($parse['query']))
        {
            parse_str($parse['query'], $params);
            unset($params[$this->page_name]);
            $url = $parse['path'].'?'.http_build_query($params);
        }
        if(!empty($params))
        {
            $url .= '&';
        }
        $this->url = $url;
    }
    
    /**
     * 得到$page的url
     * @param $page 页面
     * @return string
     */
    protected function _get_url($page)
    {
        if($this->url === NULL)
        {
            $this->_set_url();   
        }
    //  $lable = strpos('&', $this->url) === FALSE ? '' : '&';
        return $this->url.$this->page_name.'='.$page;
    }
    
    /**
     * 得到第一页
     * @return string
     */
    public function first_page($name = '首页')
    {
        if($this->now_page > 5)
        {
            return $this->_get_link('1', $name);
        }   
        return '';
    }
    
    /**
     * 最后一页
     * @param $name
     * @return string
     */
    public function last_page($name = '尾页')
    {
        if($this->now_page < $this->total_pages - 5)
        {
            return $this->_get_link($this->total_pages, $name);
        }   
        return '';
    }  
    
    /**
     * 上一页
     * @return string
     */
    public function prev_page($name = '上一页')
    {
        if($this->now_page != 1)
        {
            return $this->_get_link($this->now_page - 1, $name);
        }
        return '';
    }
    
    /**
     * 下一页
     * @return string
     */
    public function next_page($name = '下一页')
    {
        if($this->now_page < $this->total_pages)
        {
            return $this->_get_link($this->now_page + 1, $name);
        }
        return '';
    }
 
    /**
     * 分页样式输出
     * @param $param
     * @return string
     */
    public function show($param = 1)
    {
        if($this->total_rows < 1)
        {
            return '';
        }
        $className = 'show_'.$param;
        $classNames = get_class_methods($this);
        if(in_array($className, $classNames))
        {
            return $this->$className();
        }
        return '';
    }
    
    protected function show_1()
    {
        $plus = $this->plus;
        if( $plus + $this->now_page > $this->total_pages)
        {
            $begin = $this->total_pages - $plus * 2;
        }else{
            $begin = $this->now_page - $plus;
        }
         
        $begin = ($begin >= 1) ? $begin : 1;
        $returnv = '';
        $returnv .= $this->first_page();
        $returnv .= $this->prev_page();
        for ($i = $begin; $i <= $begin + $plus * 2;$i++)
        {
            if($i>$this->total_pages)
            {
                break;
            }
            if($i == $this->now_page)
            {
                $returnv .= "<a class='now_page' total_pages='".$this->total_pages."' total_rows='".$this->total_rows."'>$i</a>\n";
            }
            else
            {
                $returnv .= $this->_get_link($i, $i) . "\n";
            }
        }
        $returnv .= $this->next_page();
        $returnv .= $this->last_page();
        return $returnv;
    }
    
    protected function show_2()
    {
        if($this->total_pages != 1)
        {
            $returnv = '';
            $returnv .= $this->prev_page('<<');
            for($i = 1; $i<=$this->total_pages; $i++)
            {
                if($i == $this->now_page)
                {
                    $returnv .= "<a class='now_page' total_pages='".$this->total_pages."' total_rows='".$this->total_rows."'>$i</a>\n";
                }
                else
                {
                    if($this->now_page-$i>=4 && $i != 1)
                    {
                        $returnv .= "<span class='pageMore'>...</span>\n";
                        $i = $this->now_page-3;
                    }
                    else
                    {
                        if($i >= $this->now_page+5 && $i != $this->total_pages)
                        {
                            $returnv .= "<span>...</span>\n"; 
                            $i = $this->total_pages;
                        }
                        $returnv .= $this->_get_link($i, $i)."\n";
                    }
                }
            }
            $returnv .= $this->next_page('>>');
            return $returnv;
        }
    }
    
    protected function show_3()
    {
        $plus = $this->plus;
        if( $plus + $this->now_page > $this->total_pages)
        {
            $begin = $this->total_pages - $plus * 2;
        }else{
            $begin = $this->now_page - $plus;
        }       
        $begin = ($begin >= 1) ? $begin : 1;
        $returnv = '总计 ' .$this->total_rows. ' 个记录分为 '.$this->total_pages.' 页, 当前第 '.$this->now_page.' 页 ';
        $returnv .= ',每页 ';
        $returnv .= '<input type="text" value="'.$this->list_rows.'" id="pageSize" size="3" total_pages="'.$this->total_pages.'" total_rows="'.$this->total_rows.'"> ';
        $returnv .= $this->first_page()."\n";
        $returnv .= $this->prev_page()."\n"; 
        $returnv .= $this->next_page()."\n";
        $returnv .= $this->last_page()."\n";
        $returnv .= '<select '.($this->ajax_func_name ? 'onchange="'.$this->ajax_func_name.'(this.value);return false;"' : '').' id="gotoPage">';
        for ($i = $begin;$i<=$begin+10;$i++)
        {
            if($i>$this->total_pages)
            {
                break;
            }           
            if($i == $this->now_page)
            {
                $returnv .= '<option selected="true" value="'.$i.'">'.$i.'</option>';
            }
            else
            {
                $returnv .= '<option value="'.$i.'">'.$i.'</option>';
            }           
        }
        $returnv .= '</select>';
        return $returnv;
    }
}
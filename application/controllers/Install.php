<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Install extends CI_Controller{
    
    public function __construct() {
        
        parent::__construct();
        $this->load->library('session');
        
        // 检测环境是否支持可写
        define('IS_WRITE',true);
        
        $this->data['base_url'] = config_item('base_url');        
    }
    
    //安装首页
    public function index(){
                
        $msg = '已经成功安装了客户端  API 服务，请不要重复安装!';
                
        if(is_file(APPPATH.'data/install.lock')){
            $this->error($msg);
        }
        
        $this->load->view('install/index',$this->data);
    }
    
    //安装第一步，检测运行所需的环境设置
    public function step1(){
        
        $this->session->set_flashdata('error', false );
            
        //环境检测
        $env = self::check_env();
        
        //目录文件读写检测
        if(IS_WRITE){
            $dirfile = self::check_dirfile();
            $this->data['dirfile'] =  $dirfile;
        }
    
        //函数检测
        $func = check_func();
    
        $this->session->set_userdata('step',1);
    
        $this->data['env'] =  $env;
        $this->data['func'] = $func;
        
        $this->load->view('install/step1',$this->data);
    }
    
    //安装第二步，创建数据库
    public function step2($db = null, $admin = null){
        
        if(IS_POST){
            //检测管理员信息
            if(!is_array($admin) || empty($admin[0]) || empty($admin[1]) || empty($admin[3])){
                $this->error('请填写完整管理员信息');
            } else if($admin[1] != $admin[2]){
                $this->error('确认密码和密码不一致');
            } else {
                $info = array();
                list($info['username'], $info['password'], $info['repassword'], $info['email'])
                    = $admin;
                //缓存管理员信息
                $this->session->set_userdata('admin_info', $info);
            }
    
            //检测数据库配置
            if(!is_array($db) || empty($db[0]) ||  empty($db[1]) || empty($db[2]) || empty($db[3])){
                $this->error('请填写完整的数据库配置');
            } else {
                $DB = array();
                list($DB['DB_TYPE'], $DB['DB_HOST'], $DB['DB_NAME'], $DB['DB_USER'], $DB['DB_PWD'],
                    $DB['DB_PORT'], $DB['DB_PREFIX']) = $db;
                //缓存数据库配置
                $this->Session('db_config', $DB);
    
                //创建数据库
                $dbname = $DB['DB_NAME'];
                unset($DB['DB_NAME']);
                $db  = Db::getInstance($DB);
                $sql = "CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8";
                $db->execute($sql) || $this->error($db->getError());
            }
    
            //跳转到数据库安装页面
            $this->redirect('step3');
        } else {
            if($this->Session('update')){
                $this->session->set_userdata('step', 2);
                $this->display('update');
            }else{
                $this->session->set_flashdata('error') && $this->error('环境检测没有通过，请调整环境后重试！');
    
                $step = $this->session->userdata('step');
                if($step != 1 && $step != 2){
                    $this->redirect('step1');
                }
    
                $this->Session('step', 2);
                $this->display();
            }
        }
    }
    
    //安装第三步，安装数据表，创建配置文件
    public function step3(){
        if($this->Session('step') != 2){
            $this->redirect('step2');
        }    
        $this->display();
    
        if($this->Session('update')){
            $db = Db::getInstance();
            //更新数据表
            update_tables($db, C('DB_PREFIX'));
        }else{
            //连接数据库
            $dbconfig = $this->Session('db_config');
            $db = Db::getInstance($dbconfig);
            //创建数据表
            create_tables($db, $dbconfig['DB_PREFIX']);
            //注册创始人帐号
            $auth  = build_auth_key();
            $admin = $this->Session('admin_info');
            register_administrator($db, $dbconfig['DB_PREFIX'], $admin, $auth);
    
            //创建配置文件
            $conf   =   write_config($dbconfig, $auth);
            $this->Session('config_file',$conf);
        }
    
        if($this->Session('error')){
            //show_msg();
        } else {
            $this->Session('step', 3);
            $this->redirect('Index/complete');
        }
    }

    //安装完成
    public function complete(){
        
        $step = $this->$this->Session('step');

        if(!$step){
            $this->redirect('index');
        } elseif($step != 3) {
            $this->redirect("Install/step{$step}");
        }

        // 写入安装锁定文件
        Storage::put('./Data/install.lock', 'lock');
        
        if(!$this->Session('update')){
            //创建配置文件
            $this->assign('info',$this->Session('config_file'));
        }
        $this->Session('step', null);
        $this->Session('error', null);
        $this->Session('update',null);
        $this->display();
    }

    /*
    **
    * 系统环境检测
    * @return array 系统环境数据
    */
    private function check_env(){
        
        $items = array(
            'os' => array('操作系统', '不限制', '类Unix', PHP_OS, 'success'),
            'php'     => array('PHP版本', '5.3', '5.3+', PHP_VERSION, 'success'),
            'upload'  => array('附件上传', '不限制', '2M+', '未知', 'success'),
            'gd'      => array('GD库', '2.0', '2.0+', '未知', 'success'),
            'disk'    => array('磁盘空间', '5M', '不限制', '未知', 'success'),
        );
        
        //PHP环境检测
        if($items['php'][3] < $items['php'][1]){
            $items['php'][4] = 'error';
            $this->session->set_flashdata('error', true);
        }
        
        //附件上传检测
        if(@ini_get('file_uploads'))
            $items['upload'][3] = ini_get('upload_max_filesize');
        
        //GD库检测
        $tmp = function_exists('gd_info') ? gd_info() : array();
        if(empty($tmp['GD Version'])){
            $items['gd'][3] = '未安装';
            $items['gd'][4] = 'error';
            $this->session->set_flashdata('error', true);
        } else {
            $items['gd'][3] = $tmp['GD Version'];
        }
        unset($tmp);
        
        //磁盘空间检测
        if(function_exists('disk_free_space')) {
            $items['disk'][3] = floor(disk_free_space(BASEPATH ) / (1024*1024)).'M';
        }
    
        return $items;
    }
    
        /**
        * 目录，文件读写检测
        * @return array 检测数据
        */
    private function check_dirfile(){
        $items = array(
            array('dir',  '可写', 'success', './Uploads/Download'),
            array('dir',  '可写', 'success', './Uploads/Picture'),
            array('dir',  '可写', 'success', './Uploads/Editor'),
            array('dir',  '可写', 'success', './Runtime'),
            array('dir',  '可写', 'success', './Data'),
            array('dir', '可写', 'success', './Application/User/Conf'),
            array('file', '可写', 'success', './Application/Common/Conf'),
        );
    
        foreach ($items as &$val) {
            $item =	BASEPATH . $val[3];
            if('dir' == $val[0]){
                if(!is_writable($item)) {
                    if(is_dir($items)) {
                        $val[1] = '可读';
                        $val[2] = 'error';
                        $this->session->set_flashdata('error', true);
                    } else {
                        $val[1] = '不存在';
                        $val[2] = 'error';
                        $this->session->set_flashdata('error', true);
                    }
                }
            } else {
                if(file_exists($item)) {
                    if(!is_writable($item)) {
                        $val[1] = '不可写';
                        $val[2] = 'error';
                        $this->session->set_flashdata('error', true);
                    }
                } else {
                    if(!is_writable(dirname($item))) {
                        $val[1] = '不存在';
                        $val[2] = 'error';
                        $this->session->set_flashdata('error', true);
                    }
                }
            }
        }
    
        return $items;
    }
    
    /**
    * 函数检测
    * @return array 检测数据
    */
    private function check_func(){
        $items = array(
            array('pdo','支持','success','类'),
            array('pdo_mysql','支持','success','模块'),
            array('file_get_contents', '支持', 'success','函数'),
            array('mb_strlen',		   '支持', 'success','函数'),
        );
        
        foreach ($items as &$val) {
            if(('类'==$val[3] && !class_exists($val[0]))
            || ('模块'==$val[3] && !extension_loaded($val[0]))
            || ('函数'==$val[3] && !function_exists($val[0]))
            ){
                $val[1] = '不支持';
                $val[2] = 'error';
                $this->session->set_flashdata('error', true);
            }
         }
        
        return $items;
    }
        
    /**
    * 写入配置文件
    * @param  array $config 配置信息
    */
    private function write_config($config, $auth){
            if(is_array($config)){
                //读取配置内容
                $conf = file_get_contents(MODULE_PATH . 'Data/conf.tpl');
                $user = file_get_contents(MODULE_PATH . 'Data/user.tpl');
                //替换配置项
                foreach ($config as $name => $value) {
                    $conf = str_replace("[{$name}]", $value, $conf);
                    $user = str_replace("[{$name}]", $value, $user);
                }
        
                $conf = str_replace('[AUTH_KEY]', $auth, $conf);
                $user = str_replace('[AUTH_KEY]', $auth, $user);
        
                //写入应用配置文件
                if(!IS_WRITE){
                        
                    return '由于您的环境不可写，请复制下面的配置文件内容覆盖到相关的配置文件，然后再登录后台。<p>'.realpath(APP_PATH).'/Common/Conf/config.php</p>
                        <textarea name="" style="width:650px;height:185px">'.$conf.'</textarea>
                        <p>'.realpath(APP_PATH).'/config/install.php</p>
                        <textarea name="" style="width:650px;height:125px">'.$user.'</textarea>';
                }else{
                    if(file_put_contents(APP_PATH . 'Common/Conf/config.php', $conf) &&
                                    file_put_contents(APP_PATH . 'User/Conf/config.php', $user)){
                                    show_msg('配置文件写入成功');
                    } else {
                        show_msg('配置文件写入失败！', 'error');
                        $this->session->set_flashdata('error', true);
                    }
                
                    return '';
                }
        
            }
    }
    
    /**
     * 创建数据表
     * @param  resource $db 数据库连接资源
    */
    function create_tables($db, $prefix = ''){
        //读取SQL文件
         $sql = file_get_contents(MODULE_PATH . 'Data/install.sql');
         $sql = str_replace("\r", "\n", $sql);
         $sql = explode(";\n", $sql);
        
         //替换表前缀
         $orginal = C('ORIGINAL_TABLE_PREFIX');
         $sql = str_replace(" `{$orginal}", " `{$prefix}", $sql);
        
        //开始安装
        show_msg('开始安装数据库...');
        foreach ($sql as $value) {
            
            $value = trim($value);
            if(empty($value)) continue;
            
            if(substr($value, 0, 12) == 'CREATE TABLE') {
                $name = preg_replace("/^CREATE TABLE `(\w+)` .*/s", "\\1", $value);
                $msg  = "创建数据表{$name}";
                if(false !== $db->execute($value)){
                show_msg($msg . '...成功');
                } else {
                show_msg($msg . '...失败！', 'error');
                $this->session->set_flashdata('error', true);
                }
            } else {
                $db->execute($value);
            }
    
        }
    }
    
    function register_administrator($db, $prefix, $admin, $auth){
        show_msg('开始注册创始人帐号...');
        $sql = "INSERT INTO `[PREFIX]ucenter_member` VALUES " .
        "('1', '[NAME]', '[PASS]', '[EMAIL]', '', '[TIME]', '[IP]', 0, 0, '[TIME]', '1')";
    
        $password = user_md5($admin['password'], $auth);
        $sql = str_replace(
            array('[PREFIX]', '[NAME]', '[PASS]', '[EMAIL]', '[TIME]', '[IP]'),
            array($prefix, $admin['username'], $password, $admin['email'], NOW_TIME, get_client_ip(1)),
            $sql
        );
        //执行sql
        $db->execute($sql);
    
        $sql = "INSERT INTO `[PREFIX]member` VALUES ".
            "('1', '[NAME]', '0', '0000-00-00', '', '0', '1', '0', '[TIME]', '0', '[TIME]', '1');";
        
        $sql = str_replace(
            array('[PREFIX]', '[NAME]', '[TIME]'),
            array($prefix, $admin['username'], NOW_TIME),
            $sql);
        
            $db->execute($sql);
            show_msg('创始人帐号注册完成！');
    }
    
    /**
    * 更新数据表
    * @param  resource $db 数据库连接资源
    * @author Loudon <admin_bak@ep-fo.com>
    */
    private function update_tables($db, $prefix = ''){
        //读取SQL文件
        $sql = file_get_contents(MODULE_PATH . 'Data/update.sql');
        $sql = str_replace("\r", "\n", $sql);
        $sql = explode(";\n", $sql);
    
    //替换表前缀
        $sql = str_replace(" `ep_", " `{$prefix}", $sql);
    
        //开始安装
        show_msg('开始升级数据库...');
        foreach ($sql as $value) {
        $value = trim($value);
            if(empty($value)) continue;
        if(substr($value, 0, 12) == 'CREATE TABLE') {
                $name = preg_replace("/^CREATE TABLE `(\w+)` .*/s", "\\1", $value);
                    $msg  = "创建数据表{$name}";
                    if(false !== $db->execute($value)){
                        show_msg($msg . '...成功');
                    } else {
                    show_msg($msg . '...失败！', 'error');
                        $this->session->set_flashdata('error', true);
                        }
                    } else {
                    if(substr($value, 0, 8) == 'UPDATE `') {
                    $name = preg_replace("/^UPDATE `(\w+)` .*/s", "\\1", $value);
                    $msg  = "更新数据表{$name}";
                    } else if(substr($value, 0, 11) == 'ALTER TABLE'){
                    $name = preg_replace("/^ALTER TABLE `(\w+)` .*/s", "\\1", $value);
                    $msg  = "修改数据表{$name}";
                    } else if(substr($value, 0, 11) == 'INSERT INTO'){
                    $name = preg_replace("/^INSERT INTO `(\w+)` .*/s", "\\1", $value);
                    $msg  = "写入数据表{$name}";
                    }
                    if(($db->execute($value)) !== false){
                        show_msg($msg . '...成功');
                    } else{
                        show_msg($msg . '...失败！', 'error');
                        $this->session->set_flashdata('error', true);
                        }
                    }
        }
    }
    
    /**
    * 及时显示提示信息
    * @param  string $msg 提示信息
    */
    function show_msg($msg, $class = ''){
        echo "<script type=\"text/javascript\">showmsg(\"{$msg}\", \"{$class}\")</script>";
            flush();
            ob_flush();
                            }
        
    /**
    * 生成系统AUTH_KEY
    * @author Loudon <admin_bak@ep-fo.com>
    */
    function build_auth_key(){
        $chars  = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $chars .= '`~!@#$%^&*()_+-=[]{};:"|,.<>/?';
        $chars  = str_shuffle($chars);
        return substr($chars, 0, 40);
    }
    
    /**
     * 系统非常规MD5加密方法
     * @param  string $str 要加密的字符串
     * @return string
     */
    function user_md5($str, $key = ''){
        return '' === $str ? '' : md5(sha1($str) . $key);
    }


}

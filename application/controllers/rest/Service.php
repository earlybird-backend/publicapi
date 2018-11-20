<?php

defined('BASEPATH') OR exit('No direct script access allowed');



/**
 * This is an example of a few basic user interaction methods you could use
 * all done with a hardcoded array
 *
 * @package         CodeIgniter
 * @subpackage      Rest Server
 * @category        Controller
 * @author          Phil Sturgeon, Chris Kacerguis
 * @license         MIT
 * @link            https://github.com/chriskacerguis/codeigniter-restserver
 */
class Service extends CI_Controller {


    protected $_post_args;
    protected $_get_args;

    protected $_language;
    
    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->_parse_post();
        $this->_parse_get();
        
        $this->_language = $this->get('lang') != null ? $this->get('lang') : 'cn';
        
    }

    protected function get($key = NULL, $xss_clean = NULL)
    {
        if ($key === NULL)
        {
            return $this->_get_args;
        }
        
        return isset($this->_get_args[$key]) ? $this->_get_args[$key] : NULL;
    }

    protected function post($key = NULL, $xss_clean = NULL)
    {
        if ($key === NULL)
        {
            return $this->_post_args;
        }

        return isset($this->_post_args[$key]) ? $this->_post_args[$key] : NULL;
    }

    private function _parse_post()
    {
        $postString = file_get_contents('php://input');

        $postObject = array();
        if( isset($postString) && !empty($postString) && strlen($postString) > 4)
        {
            $postObject = json_decode($postString, true);
        }      
        
        $this->_post_args = $postObject ;

    }

    private function _parse_get() {

        $queryString = $_SERVER["QUERY_STRING"];
        
        if (!empty($queryString)) {
            
            $QueryObject = array();
            $queryString = explode('&', $queryString);
            
            foreach ($queryString as $r) {
                $r = explode('=', $r);
                if (count($r) == 2) {
                    $key               = strtolower($r[0]);
                    $QueryObject[$key] = strtolower($r[1]);
                }
            }
            $this->_get_args = $QueryObject;
        }
                
    }

    /**
     * 输出JSON
     * @param mixed $arr
     */
    private function echoJson($arr) {
        header('Content-Type: application/json; charset=utf-8');

        if (strpos(PHP_VERSION, '5.3') > -1) {
            // php 5.3-
            echo json_encode($arr);
            
        } else {
            // php 5.4+
            echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        
        }
        
        return true;
    }

    /*
     * 验证邮箱是否有效
     */
    public function verify_email(){

        $email = $this->post('email');

        if( isset($email) && !empty($email) && strlen($email) > 4){


            if( $this->check_user($email) )
            {
                $this->echoJson([
                    'code' => 1,
                    'msg' => 'success'
                ]);
            }else{
                
                $this->echoJson([
                    'code' => -1,
                    'msg' => "You post email is not exists, please check it ."
                ]);
                
            }


        }else{
            $this->echoJson([
                'code' => -1,
                'msg' => "You post email parameter is invalid, please check it first ."
            ]);
        }

    }

    private function check_user($email){
        $this->load->model('Usermodel');

        return $this->Usermodel->check_exists_user(strtolower($email)) ;
    }
    

    /*
     * 用户申请重置密码
     */
    public function reset_password(){

        $email = $this->post('email');

        if( isset($email) && !empty($email) && strlen($email) > 4){

            if( $this->check_user($email) )
            {
                //这里需要给邮件发送重置密码的模板
                
                $this->echoJson([
                    'code' => 1,
                    'msg' => 'success'
                ]);
            }else{
                $this->echoJson([
                    'code' => -1,
                    'msg' => "You post email is not exists, please check it ."
                ]);
            }


        }else{
            $this->echoJson([
                'code' => -1,
                'msg' => "You post email parameter is invalid, please check it first ."
            ]);
        }

    }
    
    /*
     * 验证重置密码的码是否仍有效
     */
    private function verify_code($code){
        
        
        return true;
    }
    
    /*
     * 接收前端发过来的重置密码的数据
     */
    public function flush_password(){
       
        
        //判断验证码是否正确
        $verify = $this->get('verify_code');
        
        //判断初始密码的原因
        $type = $this->get('type');
        
        $newpassword = $this->post('newpassword');
        $confirmpassword = $this->post('confirmpassword');
        
        
        if ( $verify != null && strlen( $verify ) > 10 && $this->verify_code( $verify)){
            
        
            if($newpassword != null && $confirmpassword != null){
                
                $this->echoJson([
                    'code' => 1,
                    'msg' => 'Success.'
                ]);
                
            }else{
                $this->echoJson([
                    'code' => -1,
                    'msg' => 'please confirm password and confirm password are not empty.'
                ]);
            }
        
        }else{
            $this->echoJson([
                'code' => -1,
                'msg' => 'Your verify code is invalid ,please re-apply.'
            ]);
        }
        
        
    }
    
    /*
     * 接收用户在线咨询
     */

    public function make_enquiry(){

        $firstname = $this->post('firstname');
        $lastname = $this->post('lastname');
        $company = $this->post('company');
        $workrole = $this->post('workrole');
        $email = $this->post('email');
        $phone = $this->post('phonenumber');
        $region = $this->post('region');
        $interested = $this->post('interested');
        $memo = $this->post('memo');
        
                
                
        if( !isset($firstname) || empty($firstname) ||
            !isset($lastname) || empty($lastname) ||
            !isset($company) || empty($company) ||
            !isset($workrole) || empty($workrole) ||
            !isset($email) || empty($email) ||
            !isset($interested) || empty($interested) ||
            !isset($region) || empty($region)
        ){
                                              
            $this->echoJson([
                'code' => -1,
                'msg' => 'please confirm *fields not empty.'
            ]);
            
        }else{
            
            $data = array(
                'FirstName'         => $firstname,
                'LastName'          => $lastname,
                'CompanyName'       => $company,
                'PositionRoleId'    => $workrole,
                'ContactEmail'      => $email,
                'ContactPhone'      => $phone,
                'RegionId'          => $region,
                'InterestId'        => $interested,
                'RequestComment'    => $memo,
                'ActivateStatus'    => 0,
                'RequestLanguage'   => $this->_language,
            );
            
            //这里需要增加  数据规则验证
            
            $this->load->model('Enquirymodel');
            
            
            //先判断该用户是否已经在于用户列表中
            
            $result = $this->Enquirymodel->get_one_entity(array( 'ContactEmail' => $email));
            
            if( isset($result) && count($result) > 0){
                $this->echoJson([
                    'code' => -1,
                    'msg' => '您的申请我们已经收到，请耐心等待客服的联系。'
                ]);
            }else{          
                
                $result = $this->Enquirymodel->insert_entity($data);
                            
                if( $result > 0 )
                {
                    $this->echoJson([
                        'code' => 1,
                        'msg' => 'success'
                    ]);
                }else{
                    $this->echoJson([
                        'code' => -1,
                        'msg' => 'Bad Request'
                    ]);
                }
            }
        }

    }


    /*
     * 下拉菜单
     */

    public function  get_dropdown(){

        $lang = $this->get("lang");

        $result = array(
            "region" => array(
                1 => "中国大陆",
                2 => "中国香港",
                3 => "中国台湾",
                4 => "美国",
                5 => "欧洲",
                6 => "其它亚太地区"
            ),
            "role" => array(
                1=> "财务经理",
                2=> "财务总监",
                3=> "企业总监",
                4=> "单位主任",
                5=> "企业法人"
            ) ,
            "interest" => array(
                1=> "获得最高的年化率回报",
                2=> "获得最高的资金利用率",
                3=> "优先解决早付日短的应付"
            )
        );


        $this->echoJson([
            "code" => 1,
            "data" => $result
        ]);


    }

}

<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class LogModel extends CI_Model {

    protected $profile ;


    protected function base64url_encode($data) {

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64url_decode($data) {

        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }


	public function __construct()    {
        parent::__construct();

        $this->db = $this->load->database('activity', true);

    }

    public function init( $profile){

        $this->profile = $profile;

    }


    public function saveOperation($role, $methodType, $actionContent){


        $sql = "INSERT INTO `User_Log`(LogTime,UserRole,UserName,MethodType, LogContent)VALUES \n"	;

        $sql .= "(NOW(),'{$role}','{$this->profile["email"]}','{$methodType}','{$actionContent}'); ";

        # MethodType 操作类型 ( modify_cash, modify_market, open_market , close_market , join_market, offer_market 等)

        $non_update =  $this->db->exec($sql);

    }
}

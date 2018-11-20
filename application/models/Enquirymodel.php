<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class EnquiryModel extends CI_Model {

    //模型的字段
    public $Id;
    public $CreateTime;
    public $FirstName ;
    public $LastName;
    public $CompanyName;
    public $PositionRoleId;
    public $ContactEmail;
    public $ContactPhone;
    public $RegionId;
    public $InterestId;
    public $RequestComment;
    public $ActivateStatus;
    public $RequestLanguage;
    public $LastReplyTime;
    public $LastReplyUser;
    public $ReplyComment;

    private $entity = "User_Enquiry";

	public function __construct()    {
        parent::__construct();
        $this->db = $this->load->database('default', true);
        $this->load->driver('cache');
    }

    public function insert_entity($data)
    {

        $this->db->insert($this->entity, $data);
        return $this->db->insert_id();
        
    }

    public function update_entity($key, $data)
    {        
        $this->db->update($this->entity, $data, array('id' => $key));
        return $this->db->affected_rows();
    }
    
    
    // Get All Entitys
	public function get_all_entitys($where,$order,$limit)
    {
        if( isset($where) && !empty($where) ){
            
            foreach ( $where as $key=>$val){
                
                $this->db->where($key, $val);
                
            }            
        }
        
        if( isset( $order) && !empty($order))
        {
                    
            foreach($order as $key=>$val){
                $this->db->order_by($key, $val);
            }
            
        }else{
            
            $this->db->order_by('Id', 'DESC');
            
        }
        
        
        $query = $this->db->get($this->entity);         
        
                
        return $query->result_array();

    }


    public function get_one_entity($where, $desc = true){


        if( isset($where) && !empty($where) ){
        
            foreach ( $where as $key=>$val){
        
                $this->db->where($key, $val);
        
            }
        }
        if( $desc)
            $this->db->order_by('Id','DESC');
        else 
            $this->db->order_by('Id','ASC');
        
        
        $this->db->limit(1);
        
        $query = $this->db->get($this->entity);
        

        return $query->first_row();
                
    }



		
}

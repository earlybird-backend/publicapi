<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class UserModel extends CI_Model {

	public function __construct()    {
        parent::__construct();
        $this->db = $this->load->database('default', true);
        $this->load->driver('cache');
    } 	

     // Get category
	public function getProfile($userId=0)
    {

        return $this->get_profile($userId);;

    }


    public function check_exists_user($userEmail){

        $sql = 	"select UserId from `Users` where EmailAddress='{$userEmail}';";

        $query = $this->db->query($sql);

        if($query->num_rows() >0 )
        {
            $result = $query->row_array();
            return  (isset($result['UserId']) && $result['UserId']  > 0 ) ? true : false;

        }else{
            return false;
        }
    }

    private function get_profile($userId){

        $result = null;

	    $sql = 	"select 
                u.UserId as profile,
                u.EmailAddress as email,
                u.FirstName as name,
                u.LastName as lastname,
                u.Position as job,
                u.DepartmentEmail as department_email,
                u.Telephone as phone,
                u.fiscalyear,
                u.Industry as industry,
                c.Name as country
                from `Users_Profile` u
                inner join `Base_Country` c ON c.Id = u.CountryId
                where u.UserId = '{$userId}'
                limit 1; ";

		
		$query = $this->db->query($sql);
		
		//print_r($this->db->last_query()); die;
		
		if($query->num_rows() >0 )
		{
            $result = $query->row_array();
			return $result;
			
		}
		
        return $result;

	}    
    
	public function updateProfile($userId, $data = array())
	{
	    if( is_array($data) && count($data) >0 ) {
            $this->db->where('UserId', $userId);
            return $this->db->update('Users_Profile', $data);
        }else{
            return false;
        }

	}

		
}

<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class StatCurrentmodel extends CI_Model {

       protected $profile;

	public function __construct()    {
        parent::__construct();
        $this->db = $this->load->database('default', true);
    }

    private function getMyCashPoolCodes() {
        $email     = trim($this->profile['email']);
        $sql       = "SELECT distinct SupplierId FROM Customer_Suppliers_Users WHERE UserEmail='{$email}'";
        $query     = $this->db->query($sql);
        if (0 == $query->num_rows()) {
            return [];
        }
        $ids       = $query->result_array();
        $values    = array_column($ids, 'SupplierId');
        $ids       = "'" . implode("','", $values) . "'";
        $sql       = "SELECT distinct CashpoolCode FROM Customer_Suppliers WHERE Id in ({$ids})";
        $query     = $this->db->query($sql); 
        if (0 == $query->num_rows()) {
            return [];
        }
        $codes     = $query->result_array();
        $codes     = array_column($codes, "CashpoolCode");
        return $codes;
    }

  
    public function getAllHash() {
        $result = [];
        $myCashPoolCodes = $this->getMyCashPoolCodes();
        if (0 == count($myCashPoolCodes)) {
            return [];
        }
        $cashpoolssql    =  "'" . implode("','", $myCashPoolCodes) . "'";
        $sql             = "SELECT CashpoolCode,StatHash FROM stat_current WHERE CashpoolCode IN ($cashpoolssql)";
        $query           = $this->db->query($sql);
        foreach ($query->result_array() as $row) {
            $tmp['cashpool_code'] = $row['CashpoolCode'];
            $tmp['stat_hash']     = $row['StatHash'];
            $result[] = $tmp;
        }
        return $result;
    }

    public function getHashByCashPoolCodes($cashpools) {
        $result = [];
        $code = $this->getMyCashPoolCodes();
        $my_code = array_intersect($code, $cashpools);
        if (count($my_code) > 0) {
            $cashpoolssql  =  "'" . implode("','", $my_code) . "'";
            $sql           = "SELECT CashpoolCode,StatHash FROM stat_current WHERE CashpoolCode IN ($cashpoolssql)";
            $query         = $this->db->query($sql);
            foreach ($query->result_array() as $row) {
                $tmp['cashpool_code'] = $row['CashpoolCode'];
                $tmp['stat_hash']     = $row['StatHash'];
                $result[] = $tmp;
            }
        } 
        return $result;
    }

    public function init($profile) {
        $this->profile = $profile;
    }
}

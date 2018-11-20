<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class OfferModel extends CI_Model {

    protected $profile ;

    protected function base64url_encode($data) {

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64url_decode($data) {

        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }


	public function __construct()    {
        parent::__construct();

        $this->db = $this->load->database('default', true);

        $this->load->driver('cache');
        $this->load->model('Cachemodel');

    }

    public function init( $profile ){
        $this->profile = $profile;
    }

    private function get_uuid(){
        $query = $this->db->query("select UUID_SHORT() as uId");
        $result = $query->row_array();
        return $result["uId"];
    }

    public function offer_bid($cashpoolId, $vendorcode, $paydate, $offer_type = "apr", $offer_value = 0, $min_payment = 0){


        //判断是否为开价时间
        if( $this->Cachemodel->getCacheService() <= 0)
            return -2;

        $market = $this->Cachemodel->getCacheMarket($cashpoolId, $vendorcode ) ;

        if( !isset($market) || empty($market) || $market["Status"] != 1){
            return -3;
        }

        if( $offer_value > 0) {

            $invoice = $this->Cachemodel->getCacheInvoice($cashpoolId, $vendorcode, $paydate);

            if (!isset($invoice) || empty($invoice) || !is_array($invoice) || count($invoice) <= 0) {

                return -1;
            }

        }

        $offer = $this->Cachemodel->getCacheOffer($cashpoolId, $vendorcode);

        if(isset($offer) &&  $offer["status"] == 0)
            return 0;


        $sql = "select o.Id,o.CustomerId,o.CashPoolId,o.Vendorcode,o.BidType,o.BidRate,o.MinAmount
                from `Supplier_Bids` o                
                WHERE o.CashPoolId = '{$cashpoolId}' AND o.Vendorcode = '{$vendorcode}'                
                LIMIT 1;";

        $query = $this->db->query($sql);

        $offer =  $query->row_array();

        //采用 Codeigniter 事务的手动模式
        $this->db->trans_strict(FALSE);
        $this->db->trans_begin();
        $update_sql = "";

        if( !isset($offer) || empty($offer) ||  $offer["Id"] <= 0) {

            $sql = "INSERT INTO `Supplier_Bids`(CreateUser,BidStatus,CashPoolId,Vendorcode,BidType,BidRate,MinAmount)VALUES";
            $sql .= "('{$this->profile['email']}',0,'{$cashpoolId}','{$vendorcode}','{$offer_type}','{$offer_value}','{$min_payment}');" ;

            $result = $this->db->query($sql);//INSERT 新的开价

            if (!$this->db->affected_rows()) {
                $this->db->trans_rollback();
                return -3;
            }else{
                $offer_id = $this->db->insert_id();
            }

            if( $offer_value > 0)
                $update_sql = "UPDATE `Supplier_Bids` SET BidStatus = 0, ResultRate = 0.00, QueueId = ";
            else
                $update_sql = "UPDATE `Supplier_Bids` SET BidStatus = -1, ResultRate = 0.00, QueueId = ";

        }else {

            $offer_id = $offer["Id"];
            $min_payment = $min_payment === 0 ? $offer['MinAmount'] : $min_payment;

            //判断是否有正在等待的队列
            $sql = "SELECT Id FROM `Supplier_Bid_Queue` WHERE CashpoolId = '{$cashpoolId}' AND BidId = '{$offer_id}' AND QueueStatus = 0;" ;
            $query = $this->db->query($sql);

            if($query->num_rows() >  0){
                return 0;
            }

            if( $offer_value > 0)
                $update_sql = "UPDATE `Supplier_Bids` SET BidStatus = 0 ,BidType = '{$offer_type}', BidRate='{$offer_value}' , MinAmount='{$min_payment}', QueueId =";
            else
                $update_sql = "UPDATE `Supplier_Bids` SET BidStatus = -1 , QueueId =";
        }

        if(!isset($offer_id) || $offer_id <= 0 )
            return -3;


        $uId = $this->get_uuid();

        $sql = "INSERT INTO `Supplier_Bid_Queue`(Id,CreateUser,QueueStatus,CashPoolId,Vendorcode,BidType,BidRate,MinAmount,BidId)VALUES";
        $sql .= "({$uId},'{$this->profile['email']}',0,'{$cashpoolId}','{$vendorcode}','{$offer_type}','{$offer_value}','{$min_payment}', '{$offer_id}');" ;

        $this->db->query($sql);//INSERT 新的开价

        if (!$this->db->affected_rows()) {
            $this->db->trans_rollback();

            return -3;

        } else {
            //上次插入操作生成的ID
           // $queueid = $this->db->insert_id();

            $update_sql .= "'{$uId}' WHERE Id = '{$offer_id}'";

            $this->db->query($update_sql);//INSERT 新的开价

            if (!$this->db->affected_rows()) {

                $this->db->trans_rollback();
                return -3;
            }

            if ($this->db->trans_status() === TRUE) {

                $this->db->trans_commit();
                return 1;
            }

            return -3;

        }

    }


}

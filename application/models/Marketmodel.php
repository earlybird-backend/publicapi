<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class MarketModel extends CI_Model {

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

    public function init($profile){
        $this->profile = $profile;
    }

    public function getMarketServiceTime(){
        $sql = "select StartTime,EndTime 
              from Customer_Cashpool_Service where `ServiceStatus` = 1;";

        $query = $this->db->query($sql);

        $result = $query ->row_array();

        return $result;
    }

     // Get category
	public function getMarkets(){

	    $markets = array();

	    $sql = 		"select p.CashpoolCode ,s.supplier,s.vendorcode,p.CompanyDivision as buyer,p.currencysign as currency_sign,p.currencyname as currency, p.Id as CashpoolId, p.NextPaydate as paydate, p.MarketStatus
                     ,sc.VendorStatus,PayAmount,NoPayAmount,ValidAmount,PayDiscount,AvgAPR,AvgDpe,CashpoolStatus
                from `Customer_Suppliers_Users` u
                inner join `Customer_Suppliers` s ON s.Id = u.SupplierId  
                inner join `Customer_Cashpool` p ON p.CashpoolCode = s.CashpoolCode
                left join `stat_current_cashpools_vendors` sc ON sc.CashpoolCode= s.CashpoolCode and sc.Vendorcode=s.Vendorcode
                where u.UserStatus = 1  and u.UserEmail = '".$this->profile['email']."'
                ORDER BY p.Id DESC;
            ";

		$query = $this->db->query($sql);
		
		//print_r($this->db->last_query()); die;
		
		if($query->num_rows() >0 )
		{
            $result = $query->result_array();

		    foreach($result as $row){
                $market = array();

                $market["buyer_id"] = $row['CashpoolCode'];

                $market["supplier"] = $row['supplier'];
                $market["vendorcode"] = $row['vendorcode'];
                $market["buyer"] = $row['buyer'];
                $market["buyer_name"] = $row['buyer'];
                $market["currency_sign"] = $row['currency_sign'];
                $market["currency"] = $row['currency'];

                $market['is_participation'] = intval($row['VendorStatus']);
                $market['paydate'] = '-';
                $market['offer_status'] = 0;
                $market['offer_type'] = "APR";
                $market['offer_value'] = 0.0;
                $market['clearing_amount'] = floatval($row['PayAmount']);
                $market['noclearing_amount'] = floatval($row['NoPayAmount']);
                $market['avaibale_amount']  = floatval($row['ValidAmount']);
                $market['discount'] = floatval($row['PayDiscount']);;
                $market['avg_apr'] = floatval($row['AvgAPR']);
                $market['avg_dpe'] = floatval($row['AvgDpe']);





                if($row['CashpoolId'] == null || empty($row['CashpoolId']) || !isset($row['MarketStatus']) || empty($row['MarketStatus']) || $row['MarketStatus'] != 1 )
                {
                    $market['buyer_status'] = 0;
                    $market['paydate'] = '-';
                    $paydate = date('Y-m-d', time()) ;
                    
                }else {
                
                    $market['buyer_status'] = 1;
                    $market['paydate']  = $row['paydate'];
                    //$paydate = $row['paydate'];
                
                }
                
                /*$sql = "SELECT Id,InvoiceNo,InvoiceAmount,EstPaydate
                FROM `Customer_Payments`
                where CashpoolCode = '{$row['CashpoolCode']}'
                and Vendorcode = '{$row['vendorcode']}'
                    and InvoiceStatus = 1
                    and IsIncluded = 1
                    and EstPaydate > '{$row['paydate']}';
                    ";
                
                     
                $query = $this->db->query($sql);

                $amount = 0;
                $dpe = 0;

                if ($query->num_rows() > 0) {

                    $invoices = $query->result_array();

                    foreach ($invoices as $inv) {

                        if (isset($row['paydate']) && !empty($row['paydate']))
                            $paydate = $row['paydate'];
                        else
                            $paydate = $inv['EstPaydate'];

                        $amount += $inv['InvoiceAmount'];
                        $dpe += (strtotime($inv['EstPaydate']) - strtotime($paydate)) / 86400;
                    }

                    $market['avg_dpe'] = round($dpe / count($invoices), 1);
                    $market['avaibale_amount'] = $amount;
                }*/

                //判断供应商是否已经参与了开价
                /*$sql = "select id,bidstatus,bidtype, bidrate,minamount,awardbid from `Supplier_Bids` where CashpoolId = '{$row['CashpoolId']}'and vendorcode='{$row['vendorcode']}' order by createtime desc limit 1;";

                $query = $this->db->query($sql);

                if ($query->num_rows() > 0) {

                    $bid = $query->row_array();

                    $market['offer_type'] = $bid['bidtype'];
                    $market['offer_value'] = $bid['bidrate'];
                    $market['avg_apr'] = isset($bid['awardbid']) ? $bid['awardbid'] : 0;

                    if ($bid['bidstatus'] >= 0) {

                        $market['is_participation'] =  1 ;
                        $market['offer_status'] = $bid['bidstatus'] == 0 ? 1 : 0;   //若 bidstatus = 1 时则为开价后已经计算, bidstatus = 0 时则为开价后正在计算

                    } else {

                        $market['is_participation'] = 0;
                        $market['offer_status'] = 0;

                    }*/

                    /*$sql = "SELECT sum(PayAmount) as amount  , sum(PayDiscount) as discount
                        FROM `Customer_PayAwards` a                            
                        WHERE CashpoolId = '{$row['CashpoolId']}' AND  a.Vendorcode = '{$row['vendorcode']}' AND a.AwardStatus = 0 AND a.AwardDate = '".date('Y-m-d', time())."' ;                               
                    ";

                    $query = $this->db->query($sql);

                    if ($query->num_rows() > 0) {

                        $clear = $query->row_array();

                        $market['clearing_amount'] = isset($clear['amount']) ? $clear['amount'] : 0.00;
                        $market['noclearing_amount'] = $market['avaibale_amount'] - (isset($clear['amount']) ? $clear['amount'] : 0);
                        $market['discount'] = isset($clear['discount']) ? $clear['discount'] : 0.00;

                    } else {

                        $market['clearing_amount'] = 0.00;
                        $market['noclearing_amount'] = 0.00;
                        $market['discount'] = 0.00;
                    }

                }

                if( $amount <= 0 && $market['is_participation'] == 1){
                    $market['is_participation'] = 0;
                }*/

                //判断供应商是否已经参与了开价
                $sql = "select id,bidstatus,bidtype, bidrate,minamount,awardbid from `Supplier_Bids` where CashpoolId = '{$row['CashpoolId']}'and vendorcode='{$row['vendorcode']}' order by createtime desc limit 1;";

                $query = $this->db->query($sql);

                if ($query->num_rows() > 0) {

                    $bid = $query->row_array();

                    $market['offer_type'] = $bid['bidtype'];
                    $market['offer_value'] = $bid['bidrate'];
                    $market['avg_apr'] = isset($bid['awardbid']) ? $bid['awardbid'] : 0;

                    if ($bid['bidstatus'] >= 0) {

                        $market['is_participation'] = 1;
                        $market['offer_status'] = $bid['bidstatus'] == 0 ? 1 : 0;   //若 bidstatus = 1 时则为开价后已经计算, bidstatus = 0 时则为开价后正在计算

                    }
                }
                
                $markets[] = $market;
            }

		}
        array_multisort(array_column($markets,'buyer_status'),SORT_DESC,$markets);
        return $markets;

	}    
    
	public function setParticipation($buyerid, $is_participation = 1 )
	{

           if($buyerid == null || empty($buyerid))
           {
               return false;
           }

            $sql = "SELECT s.Vendorcode 
                    FROM `Customer_Suppliers` s
                    INNER join `Customer_Suppliers_Users` u ON u.SupplierId = s.Id
                    WHERE u.UserEmail = '{$this->profile["email"]}'
                    AND s.CashpoolCode = '{$buyerid}'  ;
                ";

            $query = $this->db->query($sql);

            if ($query->num_rows() <= 0) {
               return false;
            }

            $ret = $query->row_array();

            $vendorcode = $ret['Vendorcode'];

            $sql = "select p.*
            from `Customer_Cashpool` p 
            where p.CashpoolCode = '{$buyerid}' 
            AND p.MarketStatus >= 0            
            LIMIT 1;";

            $query = $this->db->query($sql);
            $cashpool = $query->row_array();
                        
            
            if( $query->num_rows() <= 0 || !isset($cashpool) ||   
                ( $cashpool['MarketStatus'] > 0 && (!isset($cashpool["NextPaydate"]) || empty($cashpool["NextPaydate"]) )) 
                )
            {
                return -2;
            }

            $sql = "select o.Id,o.CashpoolId, o.Vendorcode,o.BidType,o.BidRate,o.MinAmount
                from `Supplier_Bids` o                
                WHERE o.CashpoolId = '{$cashpool["Id"]}'  and o.Vendorcode = '{$vendorcode}'
                LIMIT 1;";

            $query = $this->db->query($sql);

            if( $query->num_rows() > 0 ) {

                $data = $query->row_array();

                //判断是否为市场关闭，若市场关闭则不将开价列入队列
                if($cashpool['MarketStatus'] > 0 )
                {
                    
                    $this->load->model('Offermodel');
                    $this->Offermodel->init($this->profile);
    
                    if( $is_participation == 1)
                        return $this->Offermodel->offer_bid( $data["CashpoolId"], $data["Vendorcode"], $cashpool["NextPaydate"], $data["BidType"], $data["BidRate"],  $data["MinAmount"]);
                    else
                        return $this->Offermodel->offer_bid( $data["CashpoolId"], $data["Vendorcode"], $cashpool["NextPaydate"]);

                }else{
                    
                    if( $is_participation == 1)
                        $sql = "UPDATE `Supplier_Bids` SET BidStatus = 1, ResultRate = 0.00  where Id = {$data["Id"]};";
                    else
                        $sql = "UPDATE `Supplier_Bids` SET BidStatus = -1, ResultRate = 0.00 where Id = {$data["Id"]};";

                    $result = $this->db->query($sql);
                    
                    return $result === true ? 1 : 0;
                }
                
            }else{

                return false;
            }


	}

}

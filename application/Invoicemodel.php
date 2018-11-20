<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class InvoiceModel extends CI_Model {

    protected $profile ;

    protected $vendorcode;

    protected $cashpoolId;
    private $currencyName;
    private $currencySign;
    protected $paydate;

    protected $buyerid;




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

        $this->profile = $this->cache->memcached->get('profile');
    }

    public function __init($marketid){

        $this->buyerid = $marketid;

        if(!isset($marketid) || empty($marketid)){
            return false;
        }

        $sql = "SELECT s.* 
                FROM `Customer_Suppliers` s
                INNER join `Customer_Suppliers_Users` u ON u.SupplierId = s.Id
                WHERE u.UserEmail = '{$this->profile["email"]}'
                AND s.CashpoolCode = '{$marketid}'  ;
            ";

        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {
            $ret = $query->row_array();
            $this->vendorcode = $ret['Vendorcode'];
        }

        $sql = "select p.currencyid ,p.CurrencySign, p.CurrencyName,ps.paydate,ps.id as cashpoolId
               from `Customer_CashPool_Setting` p
               inner join `Customer_CashPool_PaySchedule` ps ON ps.CashPoolId = p.Id AND ps.ScheduleStatus = 1
               where p.CashpoolCode = '{$marketid}';";
            
        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {

            $ret = $query->row_array();
            $this->buyerid = $marketid;
            $this->currencyName = $ret['CurrencyName'];
            $this->currencySign = $ret['CurrencySign'];
            $this->paydate = $ret['paydate'];
            $this->cashpoolId = $ret['cashpoolId'];
        }

    }

    public function getCurrencySign() {
        return $this->currencySign;
    }
    public function getCurrencyName() {
        return $this->currencyName;
    }
     // Get category
	public function getMarketState()
    {

        $market = array();
        $market['buyer_id'] = $this->buyerid ;

        $sql = "select  s.supplier,s.vendorcode,c.companyname as buyer,p.currencysign as currencySign,p.currencyname as currency,ps.id as cashpoolid, p.currencyid,ps.paydate
                from `Customer_Suppliers` s
                inner join `Customer_CashPool_Setting` p ON p.CashpoolCode = s.CashpoolCode 
                inner join `Customer_CashPool_PaySchedule` ps ON ps.CashPoolId = p.Id and ScheduleStatus = 1 and ps.id = '{$this->cashpoolId}'
                inner join `Base_Companys` c ON c.Id = s.CustomerId
                where s.CashpoolCode = '{$this->buyerid}' and s.vendorcode='{$this->vendorcode}'
                ORDER BY `c`.`CompanyName` ASC
            ";

        $query = $this->db->query($sql);

        //print_r($this->db->last_query()); die;

        if ($query->num_rows() > 0) {

            $row = $query->row_array();

            $market["supplier"] = $row['supplier'];
            $market["vendorcode"] = $row['vendorcode'];
            $market["buyer"] = $row['buyer'];
            $market["buyer_name"] = $row['buyer'];
            $market["company_name"] = $row['buyer'];

            $market["currency_sign"] = $row['currencySign'];
            $market["currency"] = $row['currency'];
            $market["next_paydate"] = $row['paydate'];

            $paydate = $row['paydate'];

            $sql = "SELECT Id,InvoiceNo,InvoiceAmount,EstPaydate  
                    FROM `Customer_Payments`
                    where CashpoolCode = '{$this->buyerid}'                   
                    and Vendorcode = '{$this->vendorcode}'
                    and InvoiceStatus = 1
                    and EstPaydate > '{$this->paydate}';
                ";

            $query = $this->db->query($sql);

            if ($query->num_rows() > 0) {

                $dpe = 0;
                $amount = 0;

                $invoices = $query->result_array();

                foreach ($invoices as $inv) {

                    $amount += $inv['InvoiceAmount'];
                    $dpe += (strtotime($inv['EstPaydate']) - strtotime($paydate)) / 86400;
                }

                $market['total'] = array(
                    'available_amount'=> $amount,
                    'average_apr' => 1
                );

                //判断供应商是否已经参与了开价
                $sql = "select id,bidstatus,bidtype, bidrate,minamount,awardbid from `Supplier_Bids` where cashpoolid = '{$this->cashpoolId}'and vendorcode='{$this->vendorcode}' order by createtime desc limit 1;";

                $query = $this->db->query($sql);

                if ($query->num_rows() > 0) {

                    $bid = $query->row_array();

                    $market['offer_type'] = $bid['bidtype'];
                    $market['offer_apr'] = $bid['bidrate'];
                    $market['min_payment'] = $bid['minamount'];


                    if ($bid['bidstatus'] >= 0) {

                        $market['is_participation'] = 1;
                        $market['offer_status'] = $bid['bidstatus'] == 0 ? 1 : 0;   //若 bidstatus = 1 时则为开价后已经计算, bidstatus = 0 时则为开价后正在计算


                    } else {

                        $market['is_participation'] = 0;
                        $market['offer_status'] = 0;

                    }

                    $sql = "SELECT sum(p.InvoiceAmount) as amount  , sum(p.InvoiceAmount - a.PayAmount) as discount
                        FROM `Customer_Payments` p
                        INNER JOIN `Customer_PayAwards` a ON a.InvoiceId = p.Id AND a.BidId = '{$bid['id']}'
                        WHERE p.CashpoolCode = '{$this->buyerid}'         
                        AND p.Vendorcode = '{$row['vendorcode']}';                               
                    ";

                    $query = $this->db->query($sql);

                    if ($query->num_rows() > 0) {

                        $clear = $query->row_array();

                        $market['clearing'] = array(
                            'available_amount' => isset($clear['amount']) ? $clear['amount'] : 0.00,
                            'average_dpe' => 27.8
                        );

                        $market['nonclearing'] = array(
                            'available_amount' => $amount - (isset($clear['amount']) ? $clear['amount'] : 0),
                            'average_dpe' => 27.8
                        );
                        $market['discount'] = array(
                            'amount' => isset($clear['discount']) ? $clear['discount'] : 0.00 ,
                            'average_amount' => round(isset($clear['discount']) ? $clear['discount'] : 0.00 / $amount * 100, 2),
                            'average_apr' =>  isset($bid['awardbid']) ? $bid['awardbid'] : 0
                        );



                    } else {

                        $market['clearing'] = array(
                            'available_amount' =>  0.00,
                            'average_dpe' => 0
                        );
                        $market['nonclearing'] = array(
                            'available_amount' =>  0.00,
                            'average_dpe' => 0
                        );
                        $market['discount'] = array(
                            'amount' => 0 ,
                            'average_amount' => 0,
                            'average_apr' =>  0
                        );


                    }

                } else {

                    $market['is_participation'] = 0;
                    $market['offer_status'] = 0;
                    $market['offer_type'] = "";
                    $market['offer_apr'] = 0.0;
                    $market['clearing'] = array(
                        'available_amount' =>  0.00,
                        'average_dpe' => 0
                    );
                    $market['nonclearing'] = array(
                        'available_amount' =>  0.00,
                        'average_dpe' => 0
                    );
                    $market['discount'] = array(
                        'amount' => 0 ,
                        'average_amount' => 0,
                        'average_apr' =>  0
                    );

                    $market['avg_apr'] = 0.0;

                }


            }



            $market['history'] = array(
                'paydate' => '-',
                'amount' => 0
            );
        }
        return $market;
    }

    public function getInvoices($invoice_type){

        $status = 1;

        switch( strtolower($invoice_type)){

            case 'ineligible':
                $status = -1;
                break;
            case 'awarded':
                $satus = 2;
            case 'adjust':
                $status = 0;
                break;
            default:
                break;

        }

        $sql = "SELECT p.Id, p.InvoiceNo, p.InvoiceAmount, p.EstPaydate, p.IsIncluded,a.InvoiceId 
                    FROM `Customer_Payments` p
                    left join `Customer_PayAwards` a ON a.InvoiceId = p.Id
                    where p.CashpoolCode = '{$this->buyerid}'      
                    and p.Vendorcode = '{$this->vendorcode}'
                    and p.InvoiceStatus = '{$status}'
                    and p.EstPaydate > '{$this->paydate}';
                ";

        $query = $this->db->query($sql);
        $data = array();

        if ($query->num_rows() > 0) {

            $ret = $query->result_array();

            foreach( $ret as $row){
                $inv = array();
                $inv['inv_id'] = $row['Id'];
                $inv['is_included'] = $row['IsIncluded'];
                $inv['is_clearing'] = isset($row['InvoiceId']) && $row["InvoiceId"] != null ? 1 : 0 ;
                $inv['invoice_no'] = $row['InvoiceNo'];
                $inv['original_paydate'] = $row['EstPaydate'];
                $inv['inv_dpe'] = (strtotime($row['EstPaydate'])  - strtotime($this->paydate)) / 86400;
                $inv['invoice_amount'] = $row['InvoiceAmount'];
                $inv['discount_rate'] = 0;
                $inv['discount'] = 0;

                $data[] = $inv;
            }

        }

        return $data;

    }

    public function offer_bid($offer_type, $offer_value, $min_payment = 10000){

        $sql = "select o.Id,o.CustomerId,o.CashPoolId,o.Vendorcode,o.BidType,o.BidRate,o.MinAmount
                from `Supplier_Bids` o                
                WHERE o.CashPoolId = '{$this->cashpoolId}' AND o.Vendorcode = '{$this->vendorcode}'                
                LIMIT 1;";

        $query = $this->db->query($sql);

        $offer =  $query->row_array();

        return $this->flush_invoice( ($query->num_rows() > 0 ? $offer['Id'] : 0), $offer_type, $offer_value, $min_payment = 10000) ;

    }

    public function finish_queue(){

        //采用 Codeigniter 事务的手动模式
        $this->db->trans_strict(FALSE);
        $this->db->trans_begin();

        $sql = "UPDATE `Supplier_Bids` SET BidStatus = 1                
            WHERE CashpoolId = '{$this->cashpoolId}' AND Vendorcode = '{$this->vendorcode}';";

        $this->db->query($sql);//INSERT 新的开价

        if (!$this->db->affected_rows()) {
            $this->db->trans_rollback();
            return false;
        }

        if ($this->db->trans_status() === TRUE) {

            $this->db->trans_commit();
        }

        return true;

    }

    private function flush_invoice($offer_id, $offer_type, $offer_value, $min_payment = 10000){

        //采用 Codeigniter 事务的手动模式
        $this->db->trans_strict(FALSE);
        $this->db->trans_begin();
        $update_sql = "";

            if( $offer_id <= 0) {

                $sql = "INSERT INTO `Supplier_Bids`(CreateUser,BidStatus,CashPoolId,Vendorcode,BidType,BidRate,MinAmount)VALUES";
                $sql .= "('{$this->profile['email']}',0,'{$this->cashpoolId}','{$this->vendorcode}','{$offer_type}','{$offer_value}','{$min_payment}');" ;

                $this->db->query($sql);//INSERT 新的开价

                if (!$this->db->affected_rows()) {
                    $this->db->trans_rollback();
                    return false;
                }else{

                    $offer_id = $this->db->insert_id();
                }

                $update_sql = "UPDATE `Supplier_Bids` SET QueueId = ";

            }else {

                $update_sql = "UPDATE `Supplier_Bids` SET BidStatus = 0 ,BidType = '{$offer_type}', BidRate='{$offer_value}' , MinAmount='{$min_payment}', QueueId =";
            }


            $sql = "INSERT INTO `Supplier_Bid_Queue`(CreateUser,QueueStatus,CashPoolId,Vendorcode,BidType,BidRate,MinAmount)VALUES";
            $sql .= "('{$this->profile['email']}',0,'{$this->cashpoolId}','{$this->vendorcode}','{$offer_type}','{$offer_value}','{$min_payment}');" ;

            $this->db->query($sql);//INSERT 新的开价

            if (!$this->db->affected_rows()) {
                $this->db->trans_rollback();
                return false;
            } else {
                //上次插入操作生成的ID
                    $queueid = $this->db->insert_id();

                    $update_sql .= "'{$queueid}' WHERE Id = '{$offer_id}'";

                   $this->db->query($update_sql);//INSERT 新的开价

                   if (!$this->db->affected_rows()) {

                       $this->db->trans_rollback();
                       return false;
                   }
               

                if ($this->db->trans_status() === TRUE) {

                    $this->db->trans_commit();
                }

                return true;

            }


    }

    public function setIncluded($invoice, $isIncluded = 1){

        $sql = "UPDATE `Customer_Payments` SET `IsIncluded` = '{$isIncluded}' WHERE Id ";

        if( is_array($invoice) && count($invoice) >0 ){
            $sql .= " in (" . implode(',',$invoice).");";
        }else{
            $sql .= " = {$invoice};";
        }

        $this->db->query($sql);//INSERT 新的开价

        if (!$this->db->affected_rows()) {
            $this->db->trans_rollback();
            return false;
        }else{
            return true;
        }
    }
}

<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class InvoiceModel extends CI_Model {

    protected $profile ;

    protected $vendorcode;
    protected $supplier;
    protected $buyerName;

    protected $cashpoolId;
    private $currencyName;
    private $currencySign;
    protected $paydate;

    protected $buyerid;
    protected $buyerStatus;

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

    public function init($marketid, $profile){

        $this->buyerid = $marketid;

        if(!isset($marketid) || empty($marketid)){
            return false;
        }

        $this->profile = $profile;

        $market = $this->Cachemodel->getCacheMarkets($marketid, $this->profile['email']);

        if ( !isset($market) || $market == null) {
            return false;
        }

        $this->vendorcode = $market['Vendorcode'];
        $this->supplier = $market['Supplier'];
        $this->buyer = $market["CompanyName"];
        $this->buyerName = $market['CompanyDivision'];
        $this->buyerStatus = $market["Status"];

        $market = $this->Cachemodel->getCacheMarketSetting($marketid);

    /*
        if ( !isset($market) || empty($market) || empty($market['cashpoolId']) || $market['cashpoolId'] == null) {
            return false;
        }
    */

        $this->buyerid = $marketid;
        $this->currencyName = $market['CurrencyName'];
        $this->currencySign = $market['CurrencySign'];
        $this->paydate = $market['PayDate'];
        $this->cashpoolId = $market['CashpoolId'];

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

        //print_r($this->db->last_query()); die;

        if (isset($market) && $market !=null) {

            $market["supplier"] = $this->supplier;
            $market["vendorcode"] = $this->vendorcode;
            $market["buyer"] = $this->buyer;
            $market["buyer_name"] = $this->buyerName;
            $market["buyer_status"] = $this->buyerStatus;
            $market["company_name"] = $this->buyer;

            $market["currency_sign"] = $this->currencySign;
            $market["currency"] = $this->currencyName;
            $market["next_paydate"] = $this->paydate;
            $sql = "select * from stat_current_cashpools_vendors where CashpoolCode='".$this->buyerid."' limit 1";
            $row = $this->db->query($sql)->row_array();
            if(isset($row))
            {
                $market['total'] = array(
                    'available_amount'=> floatval($row['ValidAmount']),
                    'average_apr' => floatval($row['ValidAvgDpe'])
                );
                $market['is_participation'] = intval($row['VendorStatus']);
                $market['offer_status'] = 0;

                $market['clearing'] = array(
                    'available_amount' =>floatval($row['PayAmount']),
                    'average_dpe' => floatval($row['AvgDpe'])
                );

                $market['nonclearing'] = array(
                    'available_amount' => floatval($row['NoPayAmount']),
                    'average_dpe' => floatval($row['NoAvgDpe'])
                );

                $market['discount'] = array(
                    'amount' => floatval($row['PayDiscount']) ,
                    'average_discount' => floatval($row['AvgDiscount']) ,
                    'average_apr' =>  floatval($row['AvgAPR'])
                );
            //-----------------
                /*$paydate = $this->paydate;

                $invoices = $this->Cachemodel->getCacheInvoice($this->cashpoolId, $this->vendorcode, $this->paydate);

                if (isset($invoices) && $invoices != null) {

                    $dpe = 0;
                    $amount = 0;
                    $inv_count = 0 ;
                    foreach ($invoices as $inv) {

                        if ($inv["InvoiceStatus"] == 1 && $inv["IsIncluded"] == 1 && strtotime($inv["EstPaydate"]) > strtotime($paydate) ) {
                            $amount += $inv['InvoiceAmount'];
                            $dpe += (strtotime($inv['EstPaydate']) - strtotime($paydate)) / 86400;
                            $inv_count += 1;
                        }
                    }

                    $market['total'] = array(
                        'available_amount'=> $amount,
                        'average_apr' => $inv_count > 0 ? round($dpe /  $inv_count , 1) : 0
                    );


                    //判断供应商是否已经参与了开价

                    $bid =  $this->Cachemodel->getCacheOffer($this->cashpoolId, $this->vendorcode);

                    if ( isset($bid) && $bid != null) {

                       $market['offer_type'] = $bid['type'];
                        $market['offer_value'] = $bid['value'];
                        $market['min_payment'] = $bid['payment'];

                        if ($bid['status'] >= 0) {

                            $market['is_participation'] = 1;
                            $market['offer_status'] = $bid['status'] == 0 ? 1 : 0;   //若 bidstatus = 1 时则为开价后已经计算, bidstatus = 0 时则为开价后正在计算

                        }

                    }else {

                            $market['is_participation'] = 0;
                            $market['offer_status'] = 0;

                    }

                        $awards = $this->Cachemodel->getCacheAwards($this->cashpoolId, $this->vendorcode);

                        $clear_amount = 0 ;
                        $clear_discount = 0;
                        $avg_dpe = 0 ;
                        #$avg_apr = 0 ;
                        $avg_discount = 0 ;

                        foreach( $awards as $val){
                            $clear_amount += $val['PayAmount'];
                            $clear_discount += $val['PayDiscount'];
                            $avg_dpe += $val['PayDpe'];

                            $dpe -= $val['PayDpe'];
                        }

                        foreach( $awards as $val) {
                            $avg_discount +=   round($val['PayDiscount']*100/$clear_amount , 2);
                            #$avg_apr += round($val['PayDiscount']/$val['PayDpe']*365*100/$clear_amount, 2);
                        }

                        $avg_dpe = $avg_dpe > 0 ? round($avg_dpe*1.0/count($awards), 1) : 0 ;

                        $market['clearing'] = array(
                            'available_amount' => $clear_amount,
                            'average_dpe' => $avg_dpe
                        );

                        $market['nonclearing'] = array(
                            'available_amount' => $amount - $clear_amount,
                            'average_dpe' => $inv_count - count($awards) > 0 ?  round( $dpe*1.0/ ( $inv_count - count($awards)) , 1) : 0
                        );

                        $market['discount'] = array(
                            'amount' => $clear_discount ,
                            'average_discount' => $avg_discount ,
                            'average_apr' =>  $clear_discount > 0 && isset($bid) ? $bid["result"] : 0
                        );*/


                } else {

                    $market['is_participation'] = 0;
                    $market['offer_status'] = 0;
                    $market['offer_type'] = "";
                    $market['offer_value'] = 0.0;

                    $market['total'] = array(
                        'available_amount'=> 0.00,
                        'average_apr' => 0
                    );

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
                        'average_discount' => 0,
                        'average_apr' =>  0
                    );

                    $market['avg_apr'] = 0.0;

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
                $status = 2;
            case 'adjust':
                $status = 0;
                break;
            default:
                break;

        }

        $invoices = $this->Cachemodel->getCacheInvoice($this->cashpoolId, $this->vendorcode, $this->paydate);

        $awards = $this->Cachemodel->getCacheAwards($this->cashpoolId, $this->vendorcode);

        $offer = $this->Cachemodel->getCacheOffer( $this->cashpoolId, $this->vendorcode);

        $data = array();

        if (isset($invoices) && $invoices != null ) {

            foreach( $invoices as $row){

                if( $row ["InvoiceStatus"] ==  $status ) {

                    if ( $status == 1 && strtotime($row['EstPaydate']) <= strtotime($this->paydate) )
                        continue;

                    $inv = array();
                    $inv['inv_id'] = strval($row['Id']);
                    $inv['is_included'] = $row['IsIncluded'];
                    $inv['invoice_no'] = $row['InvoiceNo'];
                    $inv['original_paydate'] = $row['EstPaydate'];

                    $inv['inv_dpe'] = ( strtotime($row['EstPaydate']) - strtotime($this->paydate) ) / 86400;
                    $inv['invoice_amount'] = $row['InvoiceAmount'];
                    $inv['is_clearing'] = 0;

                   if( array_key_exists($row['Id'], $awards)) {
                            $val = $awards[$row['Id']];

                            $inv['is_clearing'] = 1;
                            $inv['discount'] = $val['PayDiscount'];
                            $inv['discount_rate'] = round($val['PayDiscount'] * 100 / $val['PayAmount'], 2);

                   /*
                   }else{

                       if(isset($offer) && $offer != null){

                           if( strtoupper($offer["type"]) == "APR") {
                               $inv['discount'] = round($row['InvoiceAmount']*$offer["value"]/100/365*$inv['inv_dpe'], 2); ;
                               $inv['discount_rate'] = round( $inv['discount']/$row['InvoiceAmount']*100, 2);
                           }else{
                               $inv['discount'] = round($row['InvoiceAmount']*$offer["value"]/100, 2);
                               $inv['discount_rate'] = $offer["value"];
                           }
                   */
                       }else{

                           $inv['discount'] = 0.00;
                           $inv['discount_rate'] = 0.0 ;

                   }

                    $data[] = $inv;
                }
            }

        }

        array_multisort(array_column($data,'is_clearing'),SORT_DESC,$data);

        return $data;

    }

    public function offer_bid($offer_type, $offer_value, $min_payment = -1){

        $this->load->model('Offermodel');
        $this->Offermodel->init($this->profile);

        $result = $this->Offermodel->offer_bid($this->cashpoolId, $this->vendorcode, $this->paydate, $offer_type, $offer_value, $min_payment);

        return  $result;

    }

    public function setIncluded($invoice, $isIncluded = 1){

        $where = "";
        if( is_array($invoice) && count($invoice) >0 ){
            $where = " in (" . implode(',',$invoice).") ";
        }else{
            $where = "  = {$invoice} ";
        }

        $sql = "SELECT InvoiceNo FROM Customer_Payments p
                WHERE Id {$where}
                AND (
                InvoiceStatus != 1 OR
                Vendorcode != '{$this->vendorcode}' OR
                exists (
                    SELECT Id FROM Customer_PayAwards a WHERE a.InvoiceId = p.Id 
                    AND a.AwardStatus = 1 )
                 );" ;

        $query =  $this->db->query($sql);
        $result = $query->result_array();

        $non_update = array();

        //判断是否有发票是不允许更新的
        foreach( $result as $r){
            $non_update[] = $r["InvoiceNo"];
        }

        /*
        $sql = "SELECT InvoiceNo FROM  Customer_Payments WHERE InvoiceStatus != 1 AND  Id {$where} ;" ;

        $non_update =  $this->db->query($sql);
        */
            $this->db->trans_strict(FALSE);
            $this->db->trans_begin();

            $sql = "UPDATE `Customer_Payments` SET `IsIncluded` = '{$isIncluded}' 
                    WHERE InvoiceStatus = 1  AND Id {$where} AND Id not in ( select InvoiceId from Customer_PayAwards where AwardStatus >= 1) ;";

            $result = $this->db->query($sql);

            if ( !$result ) {

                $this->db->trans_rollback();
                return false;

            }else{


                $sql = "SELECT Id, BidType, BidRate, MinAmount FROM  `Supplier_Bids`                         
                WHERE CashpoolId = '{$this->cashpoolId}' AND Vendorcode = '{$this->vendorcode}'   
                LIMIT 1;";

                $query = $this->db->query($sql);
                $offer = $query->row_array();

                #如果有发票是已经被分配
                if ( isset( $offer) && $offer["Id"] > 0 ){

                     $this->offer_bid($offer["BidType"], $offer["BidRate"], $offer["MinAmount"]) ;

                }

                $this->db->trans_commit();


                return $non_update;
            }
    }
}

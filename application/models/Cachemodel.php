<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class CacheModel extends CI_Model {

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

    }

    public function getCacheService(){

        $sql = "select StartTime,EndTime 
              from Customer_Cashpool_Service where `ServiceStatus` = 1;";

        $query = $this->db->query($sql);

        $result = $query ->row_array();

        if(  isset($result["StartTime"]) && $result["StartTime"] != null
            && isset($result["EndTime"]) && $result["EndTime"] != null
        ){

            $start_time = strtotime(  date('Y-m-d', time()).' '.date("H:i:s", strtotime($result["StartTime"]) ) );
            $end_time = strtotime(  date('Y-m-d', time()).' '.date("H:i:s", strtotime($result["EndTime"]) ));

            $close_time  = $start_time < time() && $end_time > time() ? $end_time : -1;

        }else{
            $close_time = -1;
        }

        return $close_time;

    }

    public function getCacheMarket($cashpoolId, $vendorcode){

        //$markets = $this->cache->memcached->get('markets');
        $market = array();

        $sql = "SELECT s.CashpoolCode, s.Supplier, s.Vendorcode, c.CompanyName, p.CompanyDivision , p.CurrencySign, p.CurrencyName, p.MarketStatus
        FROM `Customer_Suppliers` s      
        INNER JOIN `Customer_Cashpool` p ON p.CashpoolCode = s.CashpoolCode and p.MarketStatus >= 0
        INNER JOIN `Base_Companys` c ON c.Id = p.CompanyId
        WHERE p.Id = '{$cashpoolId}' AND s.Vendorcode = '{$vendorcode}' ;";

        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {

            $result = $query->row_array();

            $market = array(
                "CompanyName" => $result["CompanyName"] ,
                "CompanyDivision" => $result["CompanyDivision"] ,
                "Supplier" => $result["Supplier"] ,
                "Vendorcode" => $result["Vendorcode"],
                "CurrencySign" => $result["CurrencySign"],
                "CurrencyName" => $result["CurrencyName"],
                "CashpoolCode" => $result["CashpoolCode"],
                "Status" => $result["MarketStatus"]
            ) ;

        }

        // $this->cache->memcached->save('markets', $markets);


        return $market;
    }

    public function getCacheMarkets($marketid, $useremail){

       //$markets = $this->cache->memcached->get('markets');
        $markets = array();
        if( $markets == null || empty($markets)) {
            $markets = array();

            $sql = "SELECT s.CashpoolCode, s.Supplier, s.Vendorcode, u.UserEmail, c.CompanyName, p.CompanyDivision , p.CurrencySign, p.CurrencyName, p.MarketStatus
            FROM `Customer_Suppliers` s
            INNER JOIN `Customer_Suppliers_Users` u ON u.SupplierId = s.Id AND u.UserStatus = 1
            INNER JOIN `Customer_Cashpool` p ON p.CashpoolCode = s.CashpoolCode and p.MarketStatus >= 0
            INNER JOIN `Base_Companys` c ON c.Id = p.CompanyId
            ORDER BY p.Id;";

            $query = $this->db->query($sql);

            if ($query->num_rows() > 0) {
                $result = $query->result_array();

                foreach ($result as $row) {

                        if( !array_key_exists($row['CashpoolCode'] ,$markets)  ){
                            $markets[$row['CashpoolCode']] = array();
                        }

                        $markets[$row['CashpoolCode']][$this->base64url_encode($row["UserEmail"]) ] = array(
                                    "CompanyName" => $row["CompanyName"] ,
                                    "CompanyDivision" => $row["CompanyDivision"] ,
                                    "Supplier" => $row["Supplier"] ,
                                    "Vendorcode" => $row["Vendorcode"],
                                    "CurrencySign" => $row["CurrencySign"],
                                    "CurrencyName" => $row["CurrencyName"],
                                    "Status" => $row["MarketStatus"]

                            ) ;

                }
            }

           // $this->cache->memcached->save('markets', $markets);
        }

        $useremail = $this->base64url_encode($useremail);

        return array_key_exists($marketid, $markets) ? (array_key_exists($useremail, $markets[$marketid]) ? $markets[$marketid][$useremail] : null) : null ;


    }

    public function getCacheMarketSetting($marketid){

        $cashpools = $this->cache->memcached->get('cashpools');

        if( $cashpools == null || empty($cashpools)) {
            $cashpools = array();

            $sql = "select p.Id as CashpoolId, p.CashpoolCode,p.CurrencySign, p.CurrencyName,p.NextPaydate
                   from `Customer_Cashpool` p ;";

            $query = $this->db->query($sql);

            if ($query->num_rows() > 0) {
                $result = $query->result_array();

                foreach($result as $row){
                    $cashpools[$row['CashpoolCode']] = array(
                        'CurrencySign' => $row['CurrencySign'],
                        'CurrencyName' => $row['CurrencyName'],
                        'PayDate' => $row['NextPaydate'],
                        'CashpoolId' => $row['CashpoolId']
                    );
                }

            }
            $this->cache->memcached->save('cashpools', $cashpools);
        }

        return    array_key_exists($marketid, $cashpools) ? $cashpools[$marketid] : null ;

    }

    public function getCacheCompany($marketid, $vendorcode){

        $companys = $this->cache->memcached->get('companys');

        if( $companys == null || empty($companys)) {
            $companys = array();

            $sql = "select s.CashpoolCode ,s.Vendorcode,c.CompanyName,c.CompanyEnName
                    from `Base_Companys` c
                    inner join `Customer_Cashpool` p ON p.CompanyId = c.Id
                    inner join `Customer_Suppliers` s ON s.CashpoolCode = p.CashpoolCode
                    ORDER BY s.CashpoolCode;";

            $query = $this->db->query($sql);

            if ($query->num_rows() > 0) {
                $result = $query->result_array();

                foreach ($result as $row) {

                    if( !array_key_exists($row['CashpoolCode'], $companys) ) {

                        $companys[$row['CashpoolCode']] = array();
                    }

                        $companys[$row['CashpoolCode']][ $row["Vendorcode"] ] = array(

                                "CompanyName" => $row["CompanyName"] ,
                                "CompanyEnName" => $row["CompanyEnName"]

                        ) ;
                }

            }
            $this->cache->memcached->save('companys', $companys );
        }

        return array_key_exists($marketid, $companys) ? (array_key_exists($vendorcode, $companys[$marketid]) ? $companys[$marketid][$vendorcode] : null) :null ;

    }

    public function getCacheInvoice($cashpoolId,$vendorcode, $paydate){

        #$invoices = $this->cache->memcached->get('invoices');

        #if( $invoices == null || empty($invoices) || count($invoices) <= 0 ) {
        if ( !isset($paydate) || $paydate == null)
        {
            $paydate = date('Y-m-d', time());
        }

            $invoices = array();

            $sql = "SELECT p.Id, p.InvoiceStatus, p.IsIncluded, p.InvoiceNo, p.InvoiceAmount, p.EstPaydate  
                    FROM `Customer_Payments` p
                    INNER JOIN `Customer_Cashpool` c ON c.CashpoolCode = p.CashpoolCode and c.Id =  '{$cashpoolId}'
                    WHERE  p.InvoiceStatus >= 0
                    AND p.Vendorcode = '{$vendorcode}'
                    Order by IFNULL(p.IsIncluded, 0) DESC, p.EstPaydate DESC; ";

            $query = $this->db->query($sql);

            if ($query->num_rows() > 0) {
                $result = $query->result_array();

                foreach($result as $row) {

                    $invoices[] =
                        array(
                            "Id" => intval($row["Id"]),
                            "IsIncluded" => intval($row['IsIncluded']),
                            "InvoiceStatus" => intval($row["InvoiceStatus"]),
                            "InvoiceNo" => $row["InvoiceNo"],
                            "InvoiceAmount" => $row["InvoiceAmount"],
                            "EstPaydate" => $row["EstPaydate"]
                        );

                    /*
                    if (!array_key_exists($row['CashpoolCode'], $invoices)) {


                        $invoices[$row['CashpoolCode']] = array();

                    }

                    if ( !array_key_exists($row['Vendorcode'], $invoices[$row['CashpoolCode']]) ) {

                        $invoices[$row['CashpoolCode']][$row['Vendorcode']] = array();

                    }

                    $invoices[$row['CashpoolCode']][$row['Vendorcode']][] = $invoice;
                    */
                }


            }
            #$this->cache->memcached->save('invoices', $invoices , 20);
        #}
        return $invoices;
        //return    array_key_exists($marketid, $invoices) ? (    array_key_exists($vendorcode, $invoices[$marketid]) ? $invoices[$marketid][$vendorcode] : array() ) : array() ;

    }

    public function getCacheHistory($marketId, $vendorcode){

        //$invoices = $this->cache->memcached->get('history');
        $invoices = null;
        if( $invoices == null || empty($invoices) || count($invoices) <= 0 ) {

            $invoices = array();

            $sql = "SELECT  Id, CashpoolCode, Vendorcode, AwardDate, PayAmount, PayDiscount, PayDpe, InvoiceNo
                    FROM `Customer_OptimalAwards`      
                    WHERE CashpoolCode = '{$marketId}' AND Vendorcode = '{$vendorcode}'
                    Order by  AwardDate DESC; ";

            $query = $this->db->query($sql);

            if ($query->num_rows() > 0) {

                $result = $query->result_array();

                foreach($result as $row){

                    $invoice = array(
                        "Id" => $row["Id"] , "PayAmount" => floatval($row["PayAmount"]), "PayDiscount" => floatval($row["PayDiscount"]) , "PayDpe" => floatval($row["PayDpe"])
                    );

                    /*
                    if (!array_key_exists($row['CashpoolCode'], $invoices)) {

                        $invoices[$row['CashpoolCode']] = array();

                    }

                    if ( !array_key_exists($row['Vendorcode'], $invoices[$row['CashpoolCode']]) ) {

                        $invoices[$row['CashpoolCode']][$row['Vendorcode']] = array();

                    }
                    */

                    if ( !array_key_exists($row['AwardDate'], $invoices) ) {

                        $invoices[$row['AwardDate']] = array();

                    }

                    $invoices[$row['AwardDate']][] = $invoice;
                }

            }

            #$this->cache->memcached->save('history', $invoices , 20);
        }
        return $invoices;
        #return    array_key_exists($marketId, $invoices) ? ( array_key_exists($vendorcode, $invoices[$marketId]) ? $invoices[$marketId][$vendorcode] : array() ) : array() ;


    }

    public function getCacheAwardInvoices($cashpoolCode,$vendorcode,$awarddate){


        $result = array();

        $sql = "  SELECT InvoiceNo, PayDate, PayDpe, InvoiceAmount
                    FROM `Customer_OptimalAwards`  
                    WHERE  CashpoolCode = '{$cashpoolCode}' AND Vendorcode = '{$vendorcode}'  AND AwardDate = '{$awarddate}'
                    Order by PayDate ,PayDpe desc ; ";

        $query = $this->db->query($sql);

        if ($query->num_rows() > 0) {

            $result = $query->result_array();
        }

        return $result ;

    }

    public function getCacheAwards($cashpoolId,$vendorcode){

        //$invoices = $this->cache->memcached->get('awards');
        $invoices = null;
        if( $invoices == null || empty($invoices) || count($invoices) <= 0 ) {

            $invoices = array();

            $sql = "SELECT CashpoolId, Vendorcode, Id, PayAmount, PayDiscount, PayDpe, InvoiceId
                    FROM `Customer_PayAwards`      
                    WHERE AwardStatus >= 0 AND AwardDate = '".date('Y-m-d', time())."'
                    AND CashpoolId = '{$cashpoolId}' AND Vendorcode = '{$vendorcode}'
                    Order by CashpoolId,Vendorcode; ";

            $query = $this->db->query($sql);

            if ($query->num_rows() > 0) {

                $result = $query->result_array();

                foreach($result as $row){

                    $invoice = array(
                        "Id" => $row["Id"] , "PayAmount" => floatval($row["PayAmount"]), "PayDiscount" => floatval($row["PayDiscount"]) , "PayDpe" => floatval($row["PayDpe"])
                    );

                    if (!array_key_exists($row['CashpoolId'], $invoices)) {

                        $invoices[$row['CashpoolId']] = array();

                    }

                    if ( !array_key_exists($row['Vendorcode'], $invoices[$row['CashpoolId']]) ) {

                        $invoices[$row['CashpoolId']][$row['Vendorcode']] = array();

                    }

                    $invoices[$row['CashpoolId']][$row['Vendorcode']][$row["InvoiceId"]] = $invoice;
                }

            }

            $this->cache->memcached->save('awards', $invoices , 20);
        }

        return    array_key_exists($cashpoolId, $invoices) ? (    array_key_exists($vendorcode, $invoices[$cashpoolId]) ? $invoices[$cashpoolId][$vendorcode] : array() ) : array() ;

    }

    public function getCacheOffer( $cashpoolId , $vendorcode){

        //$offer = $this->cache->memcached->get( $cashpoolId.'_by_'.$vendorcode);
        $offer = null;
        if( $offer == null || empty($offer) ) {

            $sql = "select Id, bidstatus, bidtype, bidrate,case when IFNULL(ResultRate,BidRate) < bidrate then IFNULL(ResultRate,BidRate) else bidrate end as result, minamount 
                   from `Supplier_Bids` where cashpoolid = '{$cashpoolId}' and vendorcode='{$vendorcode}' order by createtime desc limit 1;";

            $query = $this->db->query($sql);

            $offer = null;

            if ($query->num_rows() > 0) {

                $bid = $query->row_array();

                    $offer = array (
                        'id' =>  $bid['Id'],
                        'type' =>  $bid['bidtype'],
                        'value' => $bid['bidrate'],
                        'result' => $bid["result"],
                        'payment' =>  $bid['minamount'],
                        'status' =>  $bid['bidstatus']
                    );
            }
        }

        $this->cache->memcached->save($cashpoolId.'_by_'.$vendorcode, $offer, 20);

        return $offer;
    }
		
}

<?php

defined('BASEPATH') OR exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
/** @noinspection PhpIncludeInspection */
require APPPATH . 'libraries/REST_Controller.php';

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
class History extends REST_Controller {

    protected $history = array();
    protected $currency = "USD";
    protected $currency_sign = "$";

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->driver('cache');

        if( $this->cache->memcached->get('history') == null ) {
            $this->load_templates();
        }

        $buyer_id = $this->get('buyer-id');
        $award_id = $this->get('award-id');

        if( (!isset($buyer_id) || empty($buyer_id)) && (!isset($award_id) || empty($award_id))){
            $this->response([
                'code' => -1,
                'data' => "Please confirm parameter is valid."
            ], REST_Controller::HTTP_BAD_REQUEST);
        }


        $source = $this->cache->memcached->get('history');

        $begindate = $this->get('startdate');
        $enddate = $this->get('enddate');

        if( $source != null){
            foreach($source as $item){
                if($begindate != null && $begindate > $item['award_date'])
                    continue;

                if($enddate != null && $enddate < $item['award_date'])
                    continue;

                $this->history[] = $item;
            }
        }


    }

    private function load_templates()
    {
        $this->cache->memcached->save('history',
            [
                array(
					'award-id' => 'xxxx-1-xxx',
					'award_date' => '2018-03-14',
					'total_paid' => 8763.56,
					'total_discount' => 131.00,
					'average_discount' => 0.32,
					'average_apr' => 6,
					'average_dpe' => 19
                ),
                array(
                    'award-id' => 'xxxx-2-xxx',
					'award_date' => '2018-03-15',
					'total_paid' => 98763.456,
					'total_discount' => 131.00,
					'average_discount' => 0.38,
					'average_apr' => 6,
					'average_dpe' => 20
				),
                array(
                    'award-id' => 'xxxx-3-xxx',
					'award_date' => '2018-03-16',
					'total_paid' => 876.00,
					'total_discount' => 95.00,
					'average_discount' => 0.32,
					'average_apr' => 6,
					'average_dpe' => 19
				),
                array(
                    'award-id' => 'xxxx-4-xxx',
					'award_date' => '2018-03-17',
					'total_paid' => 8763.56,
					'total_discount' => 124.00,
					'average_discount' => 0.32,
					'average_apr' => 6,
					'average_dpe' => 19
				),
                array(
                    'award-id' => 'xxxx-5-xxx',
					'award_date' => '2018-03-18',
					'total_paid' => 198763.456,
					'total_discount' => 545.00,
					'average_discount' => 0.38,
					'average_apr' => 6,
					'average_dpe' => 19
				),
                array(
                    'award-id' => 'xxxx-6-xxx',
					'award_date' => '2018-03-19',
					'total_paid' => 876.456,
					'total_discount' => 23.00,
					'average_discount' => 0.32,
					'average_apr' => 6,
					'average_dpe' => 19
				),
                array(
                    'award-id' => 'xxxx-7-xxx',
					'award_date' => '2018-03-20',
					'total_paid' => 8763.456,
					'total_discount' => 982.00,
					'average_discount' => 0.32,
					'average_apr' => 6,
					'average_dpe' => 19
				),
                array(
                    'award-id' => 'xxxx-8-xxx',
					'award_date' => '2018-03-21',
					'total_paid' => 98763.456,
					'total_discount' => 143.00,
					'average_discount' => 0.38,
					'average_apr' => 6,
					'average_dpe' => 19
				),
                array(
                    'award-id' => 'xxxx-9-xxx',
					'award_date' => '2018-03-22',
					'total_paid' => 876.00,
					'total_discount' => 1000.00,
					'average_discount' => 0.32,
					'average_apr' => 6,
					'average_dpe' => 19
                )
            ]);
    }

    private function init_history(){

        $sql = "
           SELECT S.CompanyName,S.CompanyEnName,A.PaymentId,A.CurrencyId,C.Vendorcode,B.BidRate,A.AwardDate,A.PayDate,sum(P.InvoiceAmount) as InvAmount,sum(A.PayAmount) as PayAmount,sum(A.PayDpe) as PayDpe
           FROM `Customer_PayAwards` A
           INNER JOIN `Customer_Payments` P ON P.Id = A.InvoiceId
           INNER JOIN `Base_Companys` S ON S.Id = A.CustomerId
           INNER JOIN `Customer_Suppliers` C ON C.CustomerId = A.CustomerId and P.Vendorcode = C.Vendorcode
           INNER JOIN `Customer_Supplier_Users` U ON U.SupplierId = C.Id AND U.UserStatus = 1
           INNER JOIN `Supplier_Bids` B ON B.Id = A.BidId
           WHERE U.UserEmail = '".$this->data['userdata'][0]['EmailAddress']."'
           GROUP BY S.CompanyName,S.CompanyEnName,A.PaymentId,A.CurrencyId,C.Vendorcode,B.BidRate,A.AwardDate DESC,A.PayDate DESC;
           ";

        $handler = $this->db->query($sql);
        $result = $handler->result_array();

        $list = array();

        foreach ( $result as $k ){

            $avg_discount = 0 ;
            $avg_apr = 0 ;
            $avg_dpe = 0 ;


            $items = $this->get_award_payment($k['PaymentId'], $k['AwardDate'], $k['Vendorcode']);

            foreach ( $items as $i){
                $avg_discount += round(  ($i['InvoiceAmount']- $i['PayAmount'])/$k['InvAmount'] , 2 );
            }

            $list[] = array(
                'PaymentId' => $k['PaymentId'],
                'AwardDate' => $k['AwardDate'],
                'CompanyName' => $k['CompanyName'],
                'CompanyEnName' => $k['CompanyEnName'],
                'PaymentId' => $k['PaymentId'],
                'PayDate' => $k['PayDate'],
                'Vendorcode' => $k['Vendorcode'],
                'BidRate' => $k['BidRate'],
                'CurrencyName' => $currency[$k['CurrencyId']]['CurrencyName'],
                'CurrencySign' => $currency[$k['CurrencyId']]['CurrencySign'],
                'InvAmount' => $k['InvAmount'],
                'PayAmount' => $k['PayAmount'],
                'Discount' => $k['InvAmount'] - $k['PayAmount'],
                'InvoiceCnt' => count($items),
                'AvgDiscount' => $avg_discount*100,
                'AvgDPE' => round($k['PayDpe']*1.0/count($items),1)
            ) ;
        }

        $this->data['result'] = $list;
    }


    public function get_market_stat_get()
    {
        $amount = 0 ;
        $discount = 0;

        foreach($this->history as $val){
            $amount += $val['total_paid'];
            $discount += $val['total_discount'];
        }

        $result = array(
            'buyer_name' => 'Early Bird',
            'currency' => $this->currency,
            'currency_sign' => $this->currency_sign,
            'awarded_amount' => $amount,
            'awarded_discount' => $discount,
            'average_discount' =>  $amount > 0 ? round($discount/$amount*100,2) : 0
        );

        $this->response([
            'code' => 1,
            'data' => $result
        ], REST_Controller::HTTP_OK);

    }



    public function get_market_graph_get()
    {
        $result = array();

        foreach($this->history as $val){
            $result[] = array(
                'date' => date('M.d',strtotime($val['award_date'])),
                'awarded_amount' => $val['total_paid'],
                'awarded_discount' => $val['total_discount']
            );
        }

        $this->response([
            'code' => 1,
            'data' => $result
        ], REST_Controller::HTTP_OK);
    }


    public function get_awarded_list_get()
    {


        $this->response([
            'code' => 1,
            'data' => $this->history
        ], REST_Controller::HTTP_OK);

    }

    public function download_awarded_detail_get(){
        $awardid = $this->get('award-id');
        $filetype = $this->get("type");

        if( $awardid != null && $filetype != null){

            $header = array(

                array(
                    'datakey' => 'InvoiceNo','colheader' => 'Invoice No','colwidth' => '16'
                ),
                array(
                    'datakey' => 'EstPaydate','colheader' => 'Original Paydate','colwidth' => '14'
                ),
                array(
                    'datakey' => 'InvoiceAmount','colheader' => 'Invoice Amount'
                )
            );


            $data = array(

                array(

                    'InvoiceNo' => '098763467',
                    'EstPaydate' => '2018-06-04',
                    'InvoiceAmount' => 106671.00
                ),
                array(
                    'InvoiceNo' => '098763468',
                    'EstPaydate' => '2018-05-14',
                    'InvoiceAmount' => 266071.00
                )

            );

            switch (strtolower($filetype)){
                case "excel" :
                    $this->load->library('PHPExcel');
                    $this->export_xls($data, $header);
                    break;
                case "csv" :
                    $this->export_csv($data, $header);
                    break;
                default:
                    break;
            }


        }else{
            $this->response([
                'code' => -1,
                'msg' => "No 'award-id' or 'type' parameter "
            ], REST_Controller::HTTP_BAD_REQUEST);
        }
    }


    private function export_csv($data,$columns = array()){
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=".date("YmdHis",time()).".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');

        $row = "";
        if(is_array($columns ) && count($columns) > 0 ){

            foreach($columns as $col){
                $row .= $col['colheader'].",";
            }

            echo (substr($row, 0, strlen($row)-1)  . "\n");
        }

        foreach($data  as $item){
            echo (implode(",", array_values($item)) . "\n");
        }

    }

    private  function export_xls($data,$columns = array())
    {
        // Create new PHPExcel object
        $objPHPExcel = new PHPExcel();

        $count = count($data);

        $col = array_keys($data[0]);

        if(!is_array($columns) || count($columns) <=0)
        {
            $columns = array();

            foreach($col as $value)
            {
                $columns[] = array(
                    'datakey' => $value,
                    'colheader' => $value,
                );
            }

        }

        $xlsCol = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $this->debuglog($columns);
        foreach($columns as $key=>$c)
        {

            $objPHPExcel->getActiveSheet()->SetCellValue($xlsCol[$key].'1', $c['colheader']);
        }

        $raw=1;
        foreach($data as $i){
            $raw++;

            foreach($columns as $key=>$c)
            {
                $objPHPExcel->getActiveSheet()->SetCellValue($xlsCol[$key].$raw, $i[$c['datakey']]);

                /*
                if(isset($c['coltype']))
                {
                    $objPHPExcel->getActiveSheet()->getStyle($xlsCol[$key].$raw)->getNumberFormat()->setFormatCode($c['coltype']);

                    //getActiveSheet()->setCellValueExplicit( $xlsCol[$key].$raw,$i[$c['datakey']],$c['coltype']);
                }
                */
            }

        }

        //设置样式

        foreach($columns as $key=>$c)
        {

            $fill = $xlsCol[$key].'2:'.$xlsCol[$key].$raw ;


            if(isset($c['colwidth']))
                $objPHPExcel->getActiveSheet()->getColumnDimension($xlsCol[$key])->setWidth($c['colwidth']);
            else
                $objPHPExcel->getActiveSheet()->getColumnDimension($xlsCol[$key])->setAutoSize(true);


            if(isset($c['colcolor']))
                $objPHPExcel->getActiveSheet()->getStyle($fill)->getFont()->getColor()->setARGB($c['colcolor']);

        }


        //选择所有数据
        $fill = $xlsCol[0].'1:'.$xlsCol[count($columns) - 1 ].$raw ;

        //设置居中
        $objPHPExcel->getActiveSheet()->getStyle($fill)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        //所有垂直居中
        $objPHPExcel->getActiveSheet()->getStyle($fill)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);



        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'.'payblelist-'.date('Y-m-d').'.xls"');
        header('Cache-Control: max-age=0');

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');


        $objWriter->save('php://output');

        exit;


    }


}

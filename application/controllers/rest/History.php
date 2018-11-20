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

    protected $buyerName = "";
    protected $currency = "USD";
    protected $currency_sign = "$";

    protected function base64url_encode($data) {

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64url_decode($data) {

        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    function __construct()
    {
        // Construct the parent class

        parent::__construct();


        $buyer_id = $this->get('buyer_id');
        $award_id = $this->get('award_id');

        if( (!isset($buyer_id) || empty($buyer_id)) && (!isset($award_id) || empty($award_id))){
            $this->response([
                'code' => -1,
                'data' => "Please confirm parameter is valid."
            ], REST_Controller::HTTP_BAD_REQUEST);
        }

        $this->load->model('Cachemodel');

        $market = $this->Cachemodel->getCacheMarkets($buyer_id, $this->profile['email']);

        if( ! isset($market) || empty($market) || $market == null){
            $this->response([
                'code' => -1,
                'data' => "Market is invalid."
            ], REST_Controller::HTTP_BAD_REQUEST);
        }

        $this->buyerName = $market['CompanyName'];
        $this->currency = $market['CurrencyName'];
        $this->currencySign = $market['CurrencySign'];

        $history = $this->Cachemodel->getCacheHistory($buyer_id, $market["Vendorcode"]);

        $begindate = $this->get('startdate');
        $enddate = $this->get('enddate');

        if( $history != null){

            foreach($history as $key => $item){

                if($begindate != null && $begindate > $key)
                    continue;
                if($enddate != null && $enddate < $key)
                    continue;

                $paid = 0 ;
                $discount = 0 ;
                $dpe = 0 ;

                foreach( $item as $val){
                    $paid += $val["PayAmount"];
                    $discount += $val["PayDiscount"];
                    $dpe += $val["PayDpe"];
                }
                $avg_discount = round($discount*100/$paid, 2);
                $avg_dpe = round($dpe/count($item), 1);
                $avg_apr = round( $discount/$dpe * 365 / $paid * 100, 2);

                $this->history[] =  array(
                    'award_id' => $this->base64url_encode($buyer_id)."_".$this->base64url_encode($market["Vendorcode"])."_".$this->base64url_encode($key),
                    'award_date' => $key,
                    'total_paid' => $paid,
                    'total_discount' => $discount,
                    'average_discount' => $avg_discount,
                    'average_apr' => $avg_apr,
                    'average_dpe' => $avg_dpe
                );
            }
        }


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
            'buyer_name' => $this->buyerName,
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

        $awardid = $this->get('award_id');
        $filetype = $this->get("type");

        if( $awardid != null && $filetype != null){


            $params = explode("_", $awardid);

            $cashpoolCode = $this->base64url_decode($params[0]);
            $vendorcode = $this->base64url_decode($params[1]);
            $awarddate = $this->base64url_decode($params[2]);

            $data = $this->Cachemodel->getCacheAwardInvoices($cashpoolCode, $vendorcode, $awarddate);

            if( !isset($data) || empty($data) || $data == null || !is_array($data) || count($data) <= 0)
            {
                $this->response([
                    'code' => -1,
                    'msg' => "No valid invoices in 'award_id'"
                ], REST_Controller::HTTP_BAD_REQUEST);
            }


            $header = array(

                array(
                    'datakey' => 'InvoiceNo','colheader' => '发票编号','colwidth' => '16'
                ),
                array(
                    'datakey' => 'InvoiceAmount','colheader' => '发票金额','colwidth' => '18'
                ), array(
                    'datakey' => 'PayDate','colheader' => '早付日期','colwidth' => '14'
                ),
                array(
                    'datakey' => 'PayDpe','colheader' => '提前天数','colwidth' => '14'
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
                'msg' => "No 'award_id' or 'type' parameter "
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

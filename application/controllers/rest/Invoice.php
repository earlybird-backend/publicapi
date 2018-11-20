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
class Invoice extends REST_Controller {

    protected $eligible = NULL;
    protected $ineligible = NULL;
    protected $awarded = NULL;
    protected $adjust = NULL;
    protected $currency = "USD";
    protected $currency_sign = "$";

    protected $filter = array(
        'is_included' => 0 ,
        'is_clearing' => 0 ,
        'invoice_dpe' => [],
        'invoice_amount' => []
    );

    function __construct()
    {
        // Construct the parent class
        parent::__construct();


        $this->load->model('Invoicemodel');

        $buyer_id = $this->get('buyer_id');

        if($_GET['buyer_id'] != null && !isset($buyer_id) ){
            $buyer_id = $_GET['buyer_id'];
        }

        if( !isset($buyer_id) || empty($buyer_id)){
            $this->response([
                'code' => -1,
                'data' => "Please confirm parameter is valid."
            ], REST_Controller::HTTP_BAD_REQUEST);
        }

        $this->Invoicemodel->init($buyer_id, $this->profile);

        $this->currency = $this->Invoicemodel->getCurrencyName();
        $this->currency_sign = $this->Invoicemodel->getCurrencySign();


         if ( $this->get('is_included') != null)
            $this->filter['is_included'] = $this->get('is_included');
        if ($this->get('is_clearing') != null)
            $this->filter['is_clearing'] = $this->get('is_clearing');

        if ($this->get('invoice_dpe') != null){
            if(is_array($this->get('invoice_dpe')) )
                $this->filter['invoice_dpe'] = $this->get('invoice_dpe');
            else
                $this->filter['invoice_dpe'] = explode(',',$this->get('invoice_dpe'));
        }

        if ( $this->get('start_date') != null && $this->get('start_date') != 'undefined' ){
            $this->filter['start_date'] = $this->get('start_date');
        }
        if ( $this->get('end_date') != null && $this->get('end_date') != 'undefined'){
            $this->filter['end_date'] = $this->get('end_date');
        }

        if ($this->get('invoice_amount') != null){
            if(is_array($this->get('invoice_amount')) )
                $this->filter['invoice_amount'] = $this->get('invoice_amount');
            else
                $this->filter['invoice_amount'] = explode(',',$this->get('invoice_amount'));
        }



    }

    public function offer_apr_post()
    {

        $offer_type = $this->post('offer_type');
        $offer_value = $this->post('offer_value');
        $min_payment = $this->post('min_payment');

        if ($offer_type == null || $offer_value == null) {
            $this->set_response([
                'code' => -1,
                'msg' => 'please confirm post offer_type and offer_value fields!'
            ], REST_Controller::HTTP_BAD_REQUEST); // CREATED (201) being the HTTP response code
        }


        if( !is_numeric( $offer_value ) || floatval($offer_value) > 99 || floatval($offer_value) < 1){

            $this->set_response([
                'code' => -1,
                'msg' => 'Value of you offer is over range!'
            ], REST_Controller::HTTP_CONFLICT);

        }else {

            $result = $this->Invoicemodel->offer_bid(strtolower($offer_type), $offer_value, ($min_payment == null ? -1 : $min_payment));            
            
            switch ($result) {

                case -2:
                    $this->set_response([
                        'code' => -1,
                        'msg' => 'Market had been closed .'
                    ], REST_Controller::HTTP_CONFLICT);
                    break;
                case -1:
                    $this->set_response([
                        'code' => -1,
                        'msg' => 'You had not any available invoices ,please check and confirm .'
                    ], REST_Controller::HTTP_CONFLICT);
                    break;
                case 0:
                    $this->set_response([
                        'code' => -1,
                        'msg' => 'Please waiting ,your offer had been on queue .'
                    ], REST_Controller::HTTP_CONFLICT);
                    break;
                case 1:
					sleep(30);
                    $this->set_response([
                        'code' => 1,
                        'msg' => 'success'
                    ], REST_Controller::HTTP_OK); // CREATED (201) being the HTTP response code

                break;
                default:
                    $this->set_response([
                        'code' => -1,
                        'msg' => 'There is not any problem, please wait and retry to do .'
                    ], REST_Controller::HTTP_CONFLICT);
                    break;
            }
        }

}

    public function get_market_stat_get(){

        $result = $this->Invoicemodel->getMarketState();

        $result["close_time"] = -1;

        $this->response([
            'code' => 1,
            'data' => $result
        ], REST_Controller::HTTP_OK);

    }

    private function filter_dpe($dpe){
        if($this->filter['invoice_dpe'] != null && count($this->filter['invoice_dpe']) > 0)
        {
            foreach($this->filter['invoice_dpe'] as $v){
                switch($v){
                    case 1:
                        if($dpe > 15)
                            continue;
                        else
                            return false;
                        break;
                    case 2:
                        if($dpe < 15 || $dpe >= 30)
                            continue;
                        else
                            return false;
                        break;
                    case 3:
                        if($dpe < 30 || $dpe >= 45)
                            continue;
                        else
                            return false;
                        break;
                    case 4:
                        if($dpe < 45 )
                            continue;
                        else
                            return false;
                        break;
                    default:
                        return false;
                        break;
                }
            }
            return true;
        }else{
            return false;
        }

    }

    private function filter_amount($amount){
        if($this->filter['invoice_amount'] != null && count($this->filter['invoice_amount']) > 0)
        {
            foreach($this->filter['invoice_amount'] as $v){
                switch($v){
                    case 1:
                        if($amount > 25000)
                            continue;
                        else
                            return false;
                        break;
                    case 2:
                        if($amount < 25000 || $amount >= 50000)
                            continue;
                        else
                            return false;
                        break;
                    case 3:
                        if($amount < 50000 || $amount >= 75000)
                            continue;
                        else
                            return false;
                        break;
                    case 4:
                        if($amount < 75000 )
                            continue;
                        else
                            return false;
                        break;
                    default:
                        return false;
                        break;
                }
            }
            return true;
        }else{
            return false;
        }

    }
    private function filter_date($estpaydate){

        if ( isset($this->filter["start_date"]) && $this->filter["start_date"] != 'null' &&  $this->filter["start_date"] > $estpaydate )
        {
                return true;
        }

         if ( isset($this->filter["end_date"]) && $this->filter["end_date"] != 'null' && $this->filter["end_date"] < $estpaydate )
         {
                return true;
        }

        return false;
    }

    public function get_invoices_with_eligible_get()
    {

        $result = array();
        $total_amount = 0 ;

        $this->eligible =   $this->Invoicemodel->getInvoices('eligible');

        foreach($this->eligible as $item){

            if($this->filter['is_included'] != 0 && $this->filter['is_included'] != $item['is_included'])
                continue;
            if($this->filter['is_clearing'] != 0 && $this->filter['is_clearing'] != $item['is_clearing'])
                continue;

            if($this->filter_date($item['original_paydate']))
                continue;

            if($this->filter_dpe($item['inv_dpe']))
                continue;
            if($this->filter_amount($item['invoice_amount']))
                continue;


            $result[] = $item;
            $total_amount += $item['invoice_amount'];

        }


        $this->response([
            'code' => 1,
            'data' => array(
                'currency' => $this->currency,
                'currency_sign' => $this->currency_sign,
                'total_amount' => $total_amount,
                'list' =>$result
            )
        ], REST_Controller::HTTP_OK);

    }

    public function get_invoices_with_ineligible_get()
    {

        $result = array();
        $total_amount = 0 ;

        $this->ineligible = $this->Invoicemodel->getInvoices('ineligible');

        foreach($this->ineligible as $item){

            if($this->filter_dpe($item['inv_dpe']))
                continue;
            if($this->filter_amount($item['invoice_amount']))
                continue;


            $result[] = $item;
            $total_amount += $item['invoice_amount'];

        }


        $this->response([
            'code' => 1,
            'data' => array(
                'currency' => $this->currency,
                'currency_sign' => $this->currency_sign,
                'total_amount' => $total_amount,
                'list' =>$result
            )
        ], REST_Controller::HTTP_OK);

    }

    public function get_invoices_with_adjust_get()
    {

        $result = array();
        $total_amount = 0 ;


        $this->adjust = $this->Invoicemodel->getInvoices('adjust');

        foreach($this->adjust as $item){

            if($this->filter_dpe($item['inv_dpe']))
                continue;
            if($this->filter_amount($item['invoice_amount']))
                continue;


            $result[] = $item;
            $total_amount += $item['invoice_amount'];

        }


        $this->response([
            'code' => 1,
            'data' => array(
                'currency' => $this->currency,
                'currency_sign' => $this->currency_sign,
                'total_amount' => $total_amount,
                'list' =>$result
            )
        ], REST_Controller::HTTP_OK);

    }

    public function get_invoices_with_awarded_get()
    {

        $result = array();
        $total_amount = 0 ;

        $this->awarded = $this->Invoicemodel->getInvoices('awarded');

        foreach($this->awarded as $item){

            if($this->filter_dpe($item['inv_dpe']))
                continue;
            if($this->filter_amount($item['invoice_amount']))
                continue;


            $result[] = $item;
            $total_amount += $item['invoice_amount'];

        }


        $this->response([
            'code' => 1,
            'data' => array(
                'currency' => $this->currency,
                'currency_sign' => $this->currency_sign,
                'total_amount' => $total_amount,
                'list' =>$result
            )
        ], REST_Controller::HTTP_OK);

    }

    public function set_invoices_inlcuded_post(){

        $invoice = $this->post('data');
        $is_included = $this->post('is_included');

        if( isset($invoice) && isset($is_included)){

            $result = $this->Invoicemodel->setIncluded($invoice, $is_included);

            if( $result !=  false || is_array($result)){

                if( !is_array($result) || count($result) <= 0) {

                    $this->response([
                        'code' => 1,
                        'msg' => 'success'
                    ], REST_Controller::HTTP_OK); // CREATED (201) being the HTTP response code
                }else{
                    $this->response([
                        'code' => 33062,
                        'msg' => $result
                    ], REST_Controller::HTTP_OK);
                }
            }else{
                $this->response([
                    'code' => -1,
                    'msg' => 'Please check you invoice parameters'
                ], REST_Controller::HTTP_BAD_REQUEST);
            }
        }else {

            $this->response([
                'code' => -1,
                'msg' => 'Please check you post data'
            ], REST_Controller::HTTP_BAD_REQUEST); // CREATED (201) being the HTTP response code
        }
    }



}

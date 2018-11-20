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
        $this->load->driver('cache');

        $buyer_id = $this->get('buyer-id');

        if($_GET['buyer-id'] != null && !isset($buyer_id) ){
            $buyer_id = $_GET['buyer-id'];
        }

        if( !isset($buyer_id) || empty($buyer_id)){
            $this->response([
                'code' => -1,
                'data' => "Please confirm parameter is valid."
            ], REST_Controller::HTTP_BAD_REQUEST);
        }


        if( $this->cache->memcached->get('eligible') == null ) {
            $this->load_templates('eligible');
        }
        if( $this->cache->memcached->get('ineligible') == null ) {
            $this->load_templates('ineligible');
        }
        if( $this->cache->memcached->get('awarded') == null ) {
            $this->load_templates('awarded');
        }
        if( $this->cache->memcached->get('adjust') == null ) {
            $this->load_templates('adjust');
        }

        $this->eligible =   $this->cache->memcached->get('eligible');
        $this->ineligible = $this->cache->memcached->get('ineligible');
        $this->awarded = $this->cache->memcached->get('awarded');
        $this->adjust = $this->cache->memcached->get('adjust');



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

            if ($this->get('invoice_amount') != null){
                if(is_array($this->get('invoice_amount')) )
                    $this->filter['invoice_amount'] = $this->get('invoice_amount');
                else
                    $this->filter['invoice_amount'] = explode(',',$this->get('invoice_amount'));
            }



    }

    public function offer_apr_post(){

        $time = 10 + rand(1, 30);

        sleep($time);

        $this->set_response([
            'code' => 1,
            'msg' => 'success'
        ], REST_Controller::HTTP_OK); // CREATED (201) being the HTTP response code
    }

    public function get_market_stat_get(){

        $result = array(
            'buyer_name' => 'Early Bird',
            'currency' => $this->currency,
            'currency_sign' => $this->currency_sign,
            'next_paydate' => '2018-4-14',
            'offer_apr' => 4.50,
            'min_payment' => 3578,
            'is_participation' => 1,
            'offer_status' => 0 ,
            'total' => array(
                'available_amount' => 39462.84,
                'average_apr' => 4.84,
            ),
            'clearing' => array(
                'available_amount' => 39462.84,
                'average_dpe' => 27.8,
            ),
            'non-clearing' => array(
                'available_amount' => 0,
                'average_dpe' => 0,
            ),
            'discount' => array(
                'amount' => 983.65,
                'average_discount' => 0.93,
                'average_apr' => 12
            )
        );
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

    public function get_invoices_with_eligible_get()
    {

        $result = array();
        $total_amount = 0 ;

        foreach($this->eligible as $item){


            if($this->filter['is_included'] != 0 && $this->filter['is_included'] != $item['is_included'])
                continue;
            if($this->filter['is_clearing'] != 0 && $this->filter['is_clearing'] != $item['is_clearing'])
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

        $invoice = $this->post('inv-id');
        $is_included = $this->post('is_included');

        if( is_array($invoice) && count($invoice) >0 ){
            foreach($invoice as $val){
                $this->update_invoice($val, $is_included);
            }
        }else{
            $this->update_invoice($invoice, $is_included);
        }

        $this->cache->memcached->save('eligible', $this->eligible);

        $this->set_response([
            'code' => 1,
            'msg' => 'success'
        ], REST_Controller::HTTP_OK); // CREATED (201) being the HTTP response code
    }

    private function update_invoice($val, $is_included){

        foreach($this->eligible as &$item){
            if($item['inv-id'] == $val) {

                $item['is_included'] = $is_included;
                break;
            }
        }
    }

    private function load_templates($load_type)
    {

        switch($load_type) {
            case 'eligible':
                #eligible
                $this->cache->memcached->save('eligible',
                    [
                        array(
                            'inv-id' => 'xxxx-1-xxx',
                            'is_included' => 1,
                            'is_clearing' => 0,
                            'invoice_id' => '098763456',
                            'original_paydate' => '2018-05-14',
                            'inv_dpe' => 59,
                            'invoice_amount' => 206671.00,
                            'discount_rate' => 0,
                            'discount' => 0
                        ),
                        array(
                            'inv-id' => 'xxxx-2-xxx',
                            'is_included' => 1,
                            'is_clearing' => 0,
                            'invoice_id' => '098763457',
                            'original_paydate' => '2018-05-18',
                            'inv_dpe' => 63,
                            'invoice_amount' => 406671.00,
                            'discount_rate' => 0,
                            'discount' => 0
                        ),
                        array(
                            'inv-id' => 'xxxx-3-xxx',
                            'is_included' => 1,
                            'is_clearing' => 0,
                            'invoice_id' => '098763458',
                            'original_paydate' => '2018-05-21',
                            'inv_dpe' => 66,
                            'invoice_amount' => 506671.00,
                            'discount_rate' => 0,
                            'discount' => 0
                        ),
                        array(
                            'inv-id' => 'xxxx-4-xxx',
                            'is_included' => 1,
                            'is_clearing' => 0,
                            'invoice_id' => '098763459',
                            'original_paydate' => '2018-05-12',
                            'inv_dpe' => 57,
                            'invoice_amount' => 706671.00,
                            'discount_rate' => 0,
                            'discount' => 0
                        ),
                        array(
                            'inv-id' => 'xxxx-5-xxx',
                            'is_included' => 1,
                            'is_clearing' => 0,
                            'invoice_id' => '098763460',
                            'original_paydate' => '2018-05-01',
                            'inv_dpe' => 45,
                            'invoice_amount' => 2006671.00,
                            'discount_rate' => 0,
                            'discount' => 0
                        ),
                        array(
                            'inv-id' => 'xxxx-6-xxx',
                            'is_included' => 1,
                            'is_clearing' => 0,
                            'invoice_id' => '098763461',
                            'original_paydate' => '2018-06-01',
                            'inv_dpe' => 76,
                            'invoice_amount' => 126671.00,
                            'discount_rate' => 0,
                            'discount' => 0
                        ),
                        array(
                            'inv-id' => 'xxxx-7-xxx',
                            'is_included' => 1,
                            'is_clearing' => 0,
                            'invoice_id' => '098763462',
                            'original_paydate' => '2018-04-24',
                            'inv_dpe' => 39,
                            'invoice_amount' => 306671.00,
                            'discount_rate' => 0,
                            'discount' => 0
                        )
                    ]);
                break;
            case 'ineligible':
                $this->cache -> memcached->save('ineligible', [
                array(

					'inv-id' => 'xxxx-10-xxx',
					'invoice_id' => '198763456',
					'original_paydate' => '2018-05-14',
					'inv_dpe' => 59,
					'invoice_amount' => 3422.00,
					'discount_rate' => '-',
					'discount' => '-'
                ),
                array(

                    'inv-id' => 'xxxx-11-xxx',
                    'invoice_id' => '198763457',
                    'original_paydate' => '2018-05-14',
                    'inv_dpe' => 59,
                    'invoice_amount' => 1911.21,
                    'discount_rate' => '-',
                    'discount' => '-'
                )
            ]);
                break;
            case 'adjust':
                $this->cache -> memcached->save('adjust', [
                    array(
                        'inv-id' => 'xxxx-12-xxx',
                        'invoice_id' => '198763456',
                        'original_paydate' => '2018-05-14',
                        'inv_dpe' => 59,
                        'invoice_amount' => 3422.00,
                        'discount_rate' => '-',
                        'discount' => '-'
                    ),array(
                        'inv-id' => 'xxxx-13-xxx',
                        'invoice_id' => '198763457',
                        'original_paydate' => '2018-05-14',
                        'inv_dpe' => 59,
                        'invoice_amount' => 1911.21,
                        'discount_rate' => '-',
                        'discount' => '-'
                    )
                    ]);
                break;
            case 'awarded':
                $this->cache -> memcached->save('awarded', [
                   array(
                        'inv-id' => 'xxxx-16-xxx',
                        'invoice_id' => '398763456',
                        'original_paydate' => '2018-05-14',
                        'inv_dpe' => 59,
                        'invoice_amount' => 206671.00,
                        'discount_rate' => 0.9,		 
                        'discount' => 1910		 
                    ),array(
                        'inv-id' => 'xxxx-17-xxx',
                        'invoice_id' => '398763457',
                        'original_paydate' => '2018-05-18',
                        'inv_dpe' => 63,
                        'invoice_amount' => 206671.00,
                        'discount_rate' => 0.9,		 
                        'discount' => 1910			 
				   ),array(
                        'inv-id' => 'xxxx-18-xxx',
                        'invoice_id' => '398763458',
                        'original_paydate' => '2018-05-21',
                        'inv_dpe' => 66,
                        'invoice_amount' => 206671.00,
                        'discount_rate' => 0.9,		 
                        'discount' => 1910			 
                    )
                ]);
                break;
            default:
                break;
        }

    }
}

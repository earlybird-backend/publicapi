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
class Market extends REST_Controller {

    #protected  $market = NULL;
    protected  $close_time = NULL;
    protected  $end_time = NULL;

    function __construct()
    {
        // Construct the parent class
        parent::__construct();

        $this->load->model('Marketmodel');
        $this->Marketmodel->init( $this->profile ) ;

        $this->get_tradding_time() ;

    }

    private function get_tradding_time(){

        $result = $this->Marketmodel->getMarketServiceTime();

        if(  isset($result["StartTime"]) && $result["StartTime"] != null
            && isset($result["EndTime"]) && $result["EndTime"] != null
        ){

            $start_time = strtotime(  date('Y-m-d', time()).' '.date("H:i:s", strtotime($result["StartTime"]) ) );
            $end_time = strtotime(  date('Y-m-d', time()).' '.date("H:i:s", strtotime($result["EndTime"]) ));

            $close_time  = $start_time < time() && $end_time > time() ? $end_time : -1;

        }else{
            $close_time = -1;
        }

        $this->close_time  = $close_time;
    }

    public function get_trading_time_get(){

        $this->response([
            'code' => 1,
            'data' => array(
                'close_time' =>  $this->close_time
            )
        ], REST_Controller::HTTP_OK);
    }

    public function get_market_list_get()
    {
        // Users from a data store e.g. database
        $markets = $this->Marketmodel->getMarkets();

        $this->response([
            'code' => 1,
            'data' => array(
                    'close_time' => $this->close_time,
                    'list' => $markets
                    )
        ], REST_Controller::HTTP_OK);

    }

    public function get_market_service(){

        $result = $this->Marketmodel->getMarketServiceTime($_GET["buyer_id"]);

        if( isset($result) && $result != null){
            $this->response([
                'code' => 1,
                'data' => array(
                    'close_time' => $result
                )
            ], REST_Controller::HTTP_OK);

        }

    }

    public function set_participation_post(){

        $buyers = $this->post('buyers');
        $is_participation = $this->post('is_participation');
        $failure = array();

        if( !isset($buyers) || empty($buyers) || !isset($is_participation))
        {
            $this->set_response([
                'code' => -1,
                'msg' => 'please confirm post buyers and is_participation fields!'
            ], REST_Controller::HTTP_BAD_REQUEST); // CREATED (201) being the HTTP response code
        }

        if( is_array($buyers) && count($buyers) >0 ){
            foreach($buyers as $val){
                $result = $this->Marketmodel->setParticipation($val, $is_participation);
            }
        }else{

            $result =  $this->Marketmodel->setParticipation($buyers, $is_participation);
        }

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

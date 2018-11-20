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

    protected  $market = NULL;
    protected  $close_time = NULL;

    function __construct()
    {

        // Construct the parent class
        parent::__construct();

        $close_time = date( strtotime(  date('Y-m-d', time()). ' 12:00:00'));

        $this->load->driver('cache');

        if( $this->cache->memcached->get('markets') != null ){

          $this->markets = $this->cache->memcached->get('markets');

        }else{

          $this->markets = [
              array(
                  "buy-id" => "xxxx-1-xxx",
                  "currency" => "USD",
                  "currency_sign" => "$",
                  "buyer" => "Amazon",
                  "vendorcode" => "1.24794-294",
                  "supplier" => "Generic Company",
                  "avaibale_amount"=> 459600.00,
				  "clearing_amount"=> 156000.00,
                  "noclearing_amount"=> 303600.00,
                  "paydate" => "Jun 9,2018",
                  "offer_status" => 0,
                  "offer_apr" => 7.5,
                  "is_participation" => 1,
                  "discount" => 3160,
                  "avg_dpe" => 37
              ),
              array(
                  "buy-id" => "xxxx-2-xxx",
                  "currency" => "USD",
                  "currency_sign" => "$",
                  "buyer" => "Costco",
                  "vendorcode" =>  "1.24794-294",
                  "supplier" => "AUTO BODY PAINT DEPO",
                  "avaibale_amount" => 128623.74,
                  "clearing_amount"=>  0.00,
                  "noclearing_amount"=>  0.00,
                  "paydate" => "Jun 12,2018",
                  "offer_status" => 0,
                  "offer_apr" => 0,
                  "is_participation" => 0,
                  "discount" => 0,
                  "avg_dpe" => 34
              ),
              array(
                  "buy-id" =>  "xxxx-3-xxx",
                  "currency" =>  "USD",
                  "currency_sign" => "$",
                  "buyer" =>  "Huarun",
                  "vendorcode" =>  "1.24794-294",
                  "supplier" => "Generic Company",
                  "avaibale_amount"=> 389700.00,
				  "clearing_amount"=>  97600.00,
				  "noclearing_amount"=>  292100.00,
                  "paydate" => "Jun 21,2018",
                  "offer_status" => 0,
                  "offer_apr" => 8.2,
                  "is_participation" => 1,
                  "discount" => 2162,
                  "avg_dpe" =>  41
              ),
              array(
                  "buy-id" =>  "xxxx-4-xxx",
                  "currency" =>  "USD",
                  "currency_sign" => "$",
                  "buyer" =>  "Tianhong",
                  "vendorcode" =>  "1.24794-294",
                  "supplier" => "Generic Company",
                  "avaibale_amount"=> 154200.00,
				  "clearing_amount"=> 72000.00,
				  "noclearing_amount"=> 82200.00,
                  "paydate" => "Jun 19,2018",
                  "offer_status" => 0,
                  "offer_apr" => 7.0,
                  "is_participation" => 0,
                  "discount" => 1656,
                  "avg_dpe" =>  36
              ),
              array(
                  "buy-id" => "xxxx-5-xxx",
                  "currency" => "EUR",
                  "currency_sign" => "€",
                  "buyer" => "Costco",
                  "vendorcode" =>  "1.24794-294",
                  "supplier" => "AUTO BODY PAINT DEPO",
                  "avaibale_amount" => 78623.74,
                  "clearing_amount"=>  0.00,
                  "noclearing_amount"=>  0.00,
                  "paydate" => "Jun 10,2018",
                  "offer_status" => 1,
                  "offer_apr" => 7.5,
                  "is_participation" => 1,
                  "discount" => 0,
                  "avg_dpe" => 41
              )
          ];

          $this->cache->memcached->save('markets', $this->markets);
        }

    }

    public function get_market_list_get()
    {
        // Users from a data store e.g. database



        $this->response([
            'code' => 1,
            'data' => array(
                    'close_time' => (strtotime($this->close_time) < time()) ? -1 : $this->close_time,
                    'list' => $this->markets
                    )
        ], REST_Controller::HTTP_OK);


    }

    public function set_participation_post(){


        $profile = $this->post('profile');
        $is_participation = $this->post('is_participation');

        if( is_array($profile) && count($profile) >0 ){
            foreach($profile as $val){
                $this->update_market($val, $is_participation);
            }
        }else{
            $this->update_market($profile, $is_participation);
        }

        $this->cache->memcached->save('markets', $this->markets);

        $this->set_response([
            'code' => 1,
            'msg' => 'success'
        ], REST_Controller::HTTP_OK); // CREATED (201) being the HTTP response code
    }

    private function update_market($val, $is_participation){
        foreach($this->markets as &$item){
            if($item['buy-id'] == $val) {

                $item['is_participation'] = $is_participation;
                break;
            }
        }
    }

}

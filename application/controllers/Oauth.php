<?php

defined('BASEPATH') OR exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
/** @noinspection PhpIncludeInspection */
require APPPATH . 'libraries/OAuth2.php';

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
class OAuth extends CI_Controller {

    protected $oauth = null;

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->oauth = new OAuth2();

    }

    public function get_access_token ()
    {

        $this->oauth->authorize();
        //$this->cache->memcached->save($access_token, array('username'=>'ares','company'=>'eb-cf') , 7190);

    }

    public function refresh_access_token(){

        $access_token = $this->get('access_token');

        if( isset($access_token) &&  !empty($access_token) )  {

            $val = $this->cache->memcached->get($access_token);

            $this->cache->memcached->delete($access_token);
            $this->cache->memcached->save($access_token, $val, 7190);
            // Set the response and exit
            $this->response([
                'code' => 1,
                'access_token' => $access_token,
                'expire_time' => time() + 7200
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code

        }

    }




}

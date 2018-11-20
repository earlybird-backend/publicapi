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
class Service extends REST_Controller {

    function __construct()
    {
        // Construct the parent class
        parent::__construct();

    }

    public function make_enquiry_post(){

        $firstname = $this->post('firstname');
        $lastname = $this->post('lastname');
        $company = $this->post('company');
        $workrole = $this->post('workrole');
        $email = $this->post('email');
        $phone = $this->post('phonenumber');
        $region = $this->post('region');
        $interested = $this->post('interested');
        $memo = $this->post('memo');

        $this->debuglog($firstname);

        if( !isset($firstname) || empty($firstname) ||
            !isset($lastname) || empty($lastname) ||
            !isset($company) || empty($company) ||
            !isset($workrole) || empty($workrole) ||
            !isset($email) || empty($email) ||
            !isset($interested) || empty($interested) ||
            !isset($region) || empty($region)
        ){
            $this->response([
                'code' => 1,
                'msg' => 'please confirm *fields not empty.'
            ], REST_Controller:: HTTP_BAD_REQUEST);
        }


        $this->response([
            'code' => 1,
            'msg' => 'success'
        ], REST_Controller::HTTP_OK);
    }


}

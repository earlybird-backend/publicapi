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
class Account extends REST_Controller {

    function __construct()
    {
        // Construct the parent class
        parent::__construct();

        $this->load->model('Usermodel');

    }

    public function get_profile_get()
    {
        //$this->data['results'] = $this->CurrencylistModel->getCurrency();

        $profile = $this->Usermodel->getProfile($this->_user_id);

        /*
        $profile = array(
            "profile"=> "xxxx-1-zzz",
            "email"=> "ares@sigma-tc.com",
            "name"=> "Jorden",
            "lastname"=> "Michael",
            "job"=> "Account",
            "department_email"=> "cfoadmin@sigma-tc.com",
            "phone"=> "+86 755 12345678",
            "fiscalyear"=> "",
            "industry"=> "",
            "country"=> "Chinese"
	    );
        */
        if($profile == null){
            // Set the response and exit
            $this->response([
                'code' => -1,
                'data' => 'Invalid User'
            ], REST_Controller::HTTP_BAD_REQUEST); // OK (200) being the HTTP response code

        }else{
            // Set the response and exit
            $this->response([
                'code' => 1,
                'data' => $profile
            ], REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        }


    }

    public function push_formid_post(){

        $app_openId = $this->post('app_openid');
        $app_formId = $this->post('app_formid');

        $post_data = array(
            "app_openid" => $app_openId,
		    "app_formid" => $app_formId
        );

        $this->set_response([
            'code' => 1,
            'msg' => 'push success\n '.json_encode($post_data, true)
        ], REST_Controller::HTTP_OK); // CREATED (201) being the HTTP response code
    }

    public function update_profile_post()
    {

		$first_name = $this->post("name");
		$last_name= $this->post("lastname");
		$job= $this->post("job");
		$department_email= $this->post("department_email");
		$phone= $this->post("phone");
        $fiscalyear= $this->post("fiscalyear");
		$industry= $this->post("industry");
		$country= $this->post( "country");

        $this->set_response([
            'code' => 1,
            'msg' => 'success'
        ], REST_Controller::HTTP_OK); // CREATED (201) being the HTTP response code
    }

    public function change_password_get(){
        $this->set_response([
            'code' => 1,
            'msg' => 'success'
        ], REST_Controller::HTTP_OK);
    }

    public function reset_password_post()
    {

        $original_password = $this->post('old_password');
        $new_password = $this->post('new_password');
        $confirm_password = $this->post('confirm_password');


        if(!isset($original_password) || !isset($new_password) || !isset($confirm_password)){
            $this->set_response([
                'code' => -200,
                'msg' => 'Please confirm your enter value'
            ], REST_Controller::HTTP_OK);
        }

        if($new_password != $confirm_password){
            $this->set_response([
                'code' => -1,
                'msg' => 'Confirm password not equal new password'
            ], REST_Controller::HTTP_OK);
        }

        $this->set_response([
            'code' => 1,
            'msg' => 'success'
        ], REST_Controller::HTTP_OK); // CREATED (201) being the HTTP response code
    }

}

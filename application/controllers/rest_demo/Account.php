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

    }

    public function get_profile_get()
    {


        $sql = "select c.*
                from apr_currency_by_admin c
                left join apr_supplier_setting s on s.CurrencyId = c.CurrencyId and SupplierId = '{$UserId}' 
                where s.SettingId is null;
                ";

        $handler = $this->db->query($sql);

        $this->data['results'] = $handler->result_array();
        //$this->data['results'] = $this->CurrencylistModel->getCurrency();




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

            // Set the response and exit
            $this->response([
                'code' => 1,
                'data' => $profile
            ], REST_Controller::HTTP_OK); // OK (200) being the HTTP response code

    }



    public function update_profile_post()
    {


        $config = array(

            array(
                'field' => 'CurrencyName',
                'label' => 'CurrencyName',
                'rules' => 'required|xss_clean'
            ),
            array(
                'field' => 'CapitalCost',
                'label' => 'CapitalCost',
                'rules' => 'trim|required|xss_clean|numeric'
            ),
        );


        $this->form_validation->set_rules($config);

        if ($this->form_validation->run() == FALSE) {

            $validation_errors = validation_errors();
            $valerrors = explode('</p>', $validation_errors);

            foreach ($valerrors as $index => $error) {
                $error = str_replace('<p>', '', $error);
                $error = trim($error);

                if (!empty($error)) {
                    $errors[$index] = $error;
                }
            }


            //echo json_encode($errors, JSON_FORCE_OBJECT);
            echo json_encode($errors, JSON_FORCE_OBJECT);

        } else {

            $data = array();

            $data['CurrencyId'] = $this->input->post('CurrencyId');
            $data['SupplierId'] = $this->session->userdata('UserId');
            $data['CurrencyName'] = $this->input->post('CurrencyName');
            $data['CapitalCost'] = $this->input->post('CapitalCost');
            $data['AddedDate'] = $this->currentDateTime;
            $result = $this->db->insert('apr_supplier_setting', $data);
            $this->session->set_flashdata('settingsuccess', 'Your supplier Setting is added successfully');
            redirect("supplier/settings", "refresh");
        }


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

        $this->set_response([
            'code' => 1,
            'msg' => 'success'
        ], REST_Controller::HTTP_OK); // CREATED (201) being the HTTP response code
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

<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Main extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
        $this->load->database();
        // $this->load->model('Main_Model');
        $this->load->helper('form');
        $this->load->library('form_validation');

    }

    function index()
    {

        $data = [
            'page' => 'mainpage',
        ];

        $this->load->view('main', $data);
    }

}
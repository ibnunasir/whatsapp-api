<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Toyyibpay {
    const DEV_URL = 'https://dev.toyyibpay.com';
    const PROD_URL = 'https://toyyibpay.com';

    protected $CI;
    protected $baseUrl;
    protected $userSecretKey;
    protected $categoryCode;

    public function __construct($params)
    {
        $this->CI =& get_instance();
        $this->CI->load->library('curl');

        $this->baseUrl = $params['sandbox'] ? self::DEV_URL : self::PROD_URL;
        $this->userSecretKey = $params['userSecretKey'];
        $this->categoryCode = $params['categoryCode'];
    }

    public function createBill($data)
    {
        $data['userSecretKey'] = $this->userSecretKey;
        $data['categoryCode'] = $this->categoryCode;

        $url = $this->baseUrl . '/index.php/api/createBill';
        $response = $this->CI->curl->simple_post($url, $data);

        return json_decode($response, true);
    }

    public function getBillTransactions($billCode)
    {
        $data = [
            'billCode' => $billCode,
            'userSecretKey' => $this->userSecretKey
        ];

        $url = $this->baseUrl . '/index.php/api/getBillTransactions';
        $response = $this->CI->curl->simple_post($url, $data);

        return json_decode($response, true);
    }

    public function billUrl($billCode)
    {
        return $this->baseUrl . '/' . $billCode;
    }

    public function verifyPayment($data)
    {
        $billCode = $data['billcode'];
        $paymentStatus = $data['status_id'];
        $paymentAmount = $data['amount'];
        $referenceNo = $data['order_id'];

        $transactions = $this->getBillTransactions($billCode);

        if (!$transactions) {
            return false;
        }

        $transaction = $transactions[0];

        if ($transaction['billpaymentStatus'] == $paymentStatus &&
            $transaction['billpaymentAmount'] == $paymentAmount &&
            $transaction['billpaymentInvoiceNo'] == $referenceNo) {
            return true;
        }

        return false;
    }
}

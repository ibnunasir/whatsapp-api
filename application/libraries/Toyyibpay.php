<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Toyyibpay
{
	const DEV_URL = 'https://dev.toyyibpay.com';
	const PROD_URL = 'https://toyyibpay.com';

	protected $CI;
	protected $baseUrl;
	protected $userSecretKey;
	protected $categoryCode;

	public function getBaseUrl()
	{
		return $this->baseUrl;
	}

	public function __construct($params)
	{
		$this->CI = &get_instance();

		$this->baseUrl = $params['sandbox'] ? self::DEV_URL : self::PROD_URL;
		$this->userSecretKey = $params['userSecretKey'];
		$this->categoryCode = $params['categoryCode'];
	}

	public function createBill($data)
	{
		$data['userSecretKey'] = $this->userSecretKey;
		$data['categoryCode'] = $this->categoryCode;

		$url = $this->baseUrl . '/index.php/api/createBill';

		// Log request URL and data
		log_message('debug', 'ToyyibPay createBill request URL: ' . $url);
		log_message('debug', 'ToyyibPay createBill request data: ' . json_encode($data));

		$response = $this->curlPost($url, $data);

		// Log raw response
		log_message('debug', 'ToyyibPay createBill raw response: ' . $response);

		$decodedResponse = json_decode($response, true);

		// Log decoded response
		log_message('debug', 'ToyyibPay createBill decoded response: ' . json_encode($decodedResponse));

		return $decodedResponse;
	}


	public function getBillTransactions($billCode)
	{
		$data = [
			'billCode' => $billCode,
			'userSecretKey' => $this->userSecretKey
		];

		$url = $this->baseUrl . '/index.php/api/getBillTransactions';
		$response = $this->curlPost($url, $data);

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
		$referenceNo = $data['transaction_id'];

		$transactions = $this->getBillTransactions($billCode);

		log_message('debug', 'Transactions from ToyyibPay: ' . json_encode($transactions));

		if (!$transactions) {
			return false;
		}

		$transaction = $transactions[0];

		log_message('debug', 'Comparing: Status ' . $transaction['billpaymentStatus'] . ' == ' . $paymentStatus .
			', Amount ' . $transaction['billpaymentAmount'] . ' == ' . $paymentAmount .
			', Invoice ' . $transaction['billpaymentInvoiceNo'] . ' == ' . $referenceNo);

		if (
			$transaction['billpaymentStatus'] == $paymentStatus &&
			$transaction['billpaymentAmount'] == $paymentAmount &&
			$transaction['billpaymentInvoiceNo'] == $referenceNo
		) {
			return true;
		}

		return false;
	}

	private function curlPost($url, $data)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$response = curl_exec($ch);

		if ($response === false) {
			log_message('error', 'Curl error: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		log_message('debug', 'ToyyibPay API HTTP response code: ' . $httpCode);

		curl_close($ch);

		return $response;
	}
}
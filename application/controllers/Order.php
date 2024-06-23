<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Order extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper('url');
		$this->load->library('session');
		$this->load->helper('form');
		$this->load->library('form_validation');
	}

	public function index()
	{
		$this->load->view('main');
	}

	public function create()
	{
		$this->load->library('form_validation');

		$this->form_validation->set_rules('name', 'Name', 'required');
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email');
		$this->form_validation->set_rules('phone', 'Phone', 'required');
		$this->form_validation->set_rules('product', 'Product', 'required');
		$this->form_validation->set_rules('quantity', 'Quantity', 'required|numeric|greater_than[0]');

		if ($this->form_validation->run() == FALSE) {
			log_message('debug', 'Form validation failed: ' . validation_errors());
			$this->load->view('main');
		} else {
			$product_prices = [
				'product1' => 10,
				'product2' => 20,
				'product3' => 30
			];

			$product = $this->input->post('product');
			$quantity = $this->input->post('quantity');
			$amount = $product_prices[$product] * $quantity;

			$order_data = [
				'name' => $this->input->post('name'),
				'email' => $this->input->post('email'),
				'phone' => $this->input->post('phone'),
				'product' => $product,
				'quantity' => $quantity,
				'amount' => $amount,
				'status' => 'pending'
			];

			$this->db->insert('orders', $order_data);
			$order_id = $this->db->insert_id();

			log_message('debug', 'Order created with ID: ' . $order_id);

			// Integrate with ToyyibPay
			$this->config->load('toyyibpay', TRUE);
			$toyyibpay_config = $this->config->item('toyyibpay');

			log_message('debug', 'ToyyibPay config loaded: ' . json_encode($toyyibpay_config));

			$this->load->library('Toyyibpay', [
				'sandbox' => $toyyibpay_config['toyyibpay_sandbox'],
				'userSecretKey' => $toyyibpay_config['toyyibpay_secret_key'],
				'categoryCode' => $toyyibpay_config['toyyibpay_category_code'],
			]);

			log_message('debug', 'ToyyibPay base URL: ' . $this->toyyibpay->getBaseUrl());

			$billData = [
				'billName' => 'Order #' . $order_id,
				'billDescription' => 'Payment for order #' . $order_id,
				'billPriceSetting' => 1,
				'billPayorInfo' => 1,
				'billAmount' => $amount * 100,
				'billReturnUrl' => base_url('order/success'),
				'billCallbackUrl' => base_url('order/callback'),
				'billExternalReferenceNo' => $order_id,
				'billTo' => $order_data['name'],
				'billEmail' => $order_data['email'],
				'billPhone' => $order_data['phone'],
			];

			log_message('debug', 'ToyyibPay bill data: ' . json_encode($billData));

			$response = $this->toyyibpay->createBill($billData);

			log_message('debug', 'ToyyibPay createBill response: ' . json_encode($response));

			if (!$response || !isset($response[0]['BillCode'])) {
				log_message('error', 'Failed to create ToyyibPay bill: ' . json_encode($response));
				$this->session->set_flashdata('error', 'Failed to create bill');
				redirect('order');
			}

			$billCode = $response[0]['BillCode'];

			// Update order with bill code
			$this->db->update('orders', ['bill_code' => $billCode], ['id' => $order_id]);

			log_message('debug', 'Order updated with bill code: ' . $billCode);

			// Redirect to ToyyibPay payment page
			$redirectUrl = $this->toyyibpay->billUrl($billCode);
			log_message('debug', 'Redirecting to ToyyibPay URL: ' . $redirectUrl);
			redirect($redirectUrl);
		}
	}

	public function callback()
	{
		log_message('debug', 'Callback received from ToyyibPay: ' . json_encode($this->input->post()));

		$this->config->load('toyyibpay', TRUE);
		$toyyibpay_config = $this->config->item('toyyibpay');

		$this->load->library('Toyyibpay', [
			'sandbox' => $toyyibpay_config['toyyibpay_sandbox'],
			'userSecretKey' => $toyyibpay_config['toyyibpay_secret_key'],
			'categoryCode' => $toyyibpay_config['toyyibpay_category_code'],
		]);

		$data = $this->input->post();

		if ($this->toyyibpay->verifyPayment($data)) {
			$orderId = $data['order_id'];
			$status = ($data['status_id'] == 1) ? 'paid' : 'cancelled';

			$this->db->update('orders', [
				'status' => $status,
				'transaction_id' => $data['transaction_id'] ?? null,
				'payment_method' => 'ToyyibPay'
			], ['id' => $orderId]);

			log_message('debug', 'Order updated after payment: ' . json_encode([
				'order_id' => $orderId,
				'status' => $status,
				'transaction_id' => $data['transaction_id'] ?? null
			]));
		} else {
			log_message('error', 'Payment verification failed for order: ' . ($data['order_id'] ?? 'unknown'));
		}
	}

	public function success()
	{
		log_message('debug', 'Success page accessed with data: ' . json_encode($this->input->get()));

		$this->config->load('toyyibpay', TRUE);
		$toyyibpay_config = $this->config->item('toyyibpay');

		$this->load->library('Toyyibpay', [
			'sandbox' => $toyyibpay_config['toyyibpay_sandbox'],
			'userSecretKey' => $toyyibpay_config['toyyibpay_secret_key'],
			'categoryCode' => $toyyibpay_config['toyyibpay_category_code'],
		]);

		$data = $this->input->get();
		$orderId = $data['order_id'];

		// Check payment status directly with ToyyibPay
		$billCode = $data['billcode'];
		$transactions = $this->toyyibpay->getBillTransactions($billCode);

		log_message('debug', 'Transactions from ToyyibPay: ' . json_encode($transactions));

		if ($transactions && $transactions[0]['billpaymentStatus'] == '1') {
			// Update order status if not already updated
			$this->db->update('orders', [
				'status' => 'paid',
				'transaction_id' => $data['transaction_id'] ?? null,
				'payment_method' => 'ToyyibPay'
			], ['id' => $orderId]);

			$order = $this->db->get_where('orders', ['id' => $orderId])->row();
			$this->load->view('order_success', ['order' => $order]);
		} else {
			$order = $this->db->get_where('orders', ['id' => $orderId])->row();
			$this->load->view('order_failed', ['order' => $order]);
		}
	}
}

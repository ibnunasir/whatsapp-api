<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Order extends CI_Controller
{

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

            // Integrate with ToyyibPay
            $this->config->load('toyyibpay', TRUE);
            $toyyibpay_config = $this->config->item('toyyibpay');

            $this->load->library('Toyyibpay', [
                'sandbox' => $toyyibpay_config['toyyibpay_sandbox'],
                'userSecretKey' => $toyyibpay_config['toyyibpay_secret_key'],
                'categoryCode' => $toyyibpay_config['toyyibpay_category_code'],
            ]);

            $billData = [
                'billName' => 'Order #' . $order_id,
                'billDescription' => 'Payment for order #' . $order_id,
                'billPriceSetting' => 1,
                'billPayorInfo' => 1,
                'billAmount' => $amount * 100, // ToyyibPay expects amount in cents
                'billReturnUrl' => base_url('order/success'),
                'billCallbackUrl' => base_url('order/callback'),
                'billExternalReferenceNo' => $order_id,
                'billTo' => $order_data['name'],
                'billEmail' => $order_data['email'],
                'billPhone' => $order_data['phone'],
            ];

            $response = $this->toyyibpay->createBill($billData);

            if (!$response || !isset($response[0]['BillCode'])) {
                $this->session->set_flashdata('error', 'Failed to create bill');
                redirect('order');
            }

            $billCode = $response[0]['BillCode'];

            // Update order with bill code
            $this->db->update('orders', ['bill_code' => $billCode], ['id' => $order_id]);

            // Redirect to ToyyibPay payment page
            redirect($this->toyyibpay->billUrl($billCode));
        }
    }

    public function callback()
    {
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
                'transaction_id' => $data['billpaymentInvoiceNo'] ?? null,
                'payment_method' => 'ToyyibPay'
            ], ['id' => $orderId]);
        }
    }

    public function success()
    {
        $this->config->load('toyyibpay', TRUE);
        $toyyibpay_config = $this->config->item('toyyibpay');

        $this->load->library('Toyyibpay', [
            'sandbox' => $toyyibpay_config['toyyibpay_sandbox'],
            'userSecretKey' => $toyyibpay_config['toyyibpay_secret_key'],
            'categoryCode' => $toyyibpay_config['toyyibpay_category_code'],
        ]);

        $data = $this->input->get();
        $orderId = $data['order_id'];

        $order = $this->db->get_where('orders', ['id' => $orderId])->row();

        if ($order && $order->status == 'paid') {
            $this->load->view('order_success', ['order' => $order]);
        } else {
            $this->load->view('order_failed', ['order' => $order]);
        }
    }
}

<?php
class ControllerExtensionRecurringSquareup extends Controller {
    public function index() {
        $this->load->language('extension/recurring/squareup');
        
        $this->load->model('account/recurring');
        $this->load->model('extension/payment/squareup');

        if (isset($this->request->get['order_recurring_id'])) {
            $order_recurring_id = $this->request->get['order_recurring_id'];
        } else {
            $order_recurring_id = 0;
        }
        
        $recurring_info = $this->model_account_recurring->getOrderRecurring($order_recurring_id);
        
        if ($recurring_info) {
            $data['cancel_url'] = html_entity_decode($this->url->link('extension/recurring/squareup/cancel', 'order_recurring_id=' . $order_recurring_id, 'SSL'), ENT_QUOTES, "UTF-8");

            $data['continue'] = $this->url->link('account/recurring', '', true);    
            
            if ($recurring_info['status'] == ModelExtensionPaymentSquareup::RECURRING_ACTIVE) {
                $data['order_recurring_id'] = $order_recurring_id;
            } else {
                $data['order_recurring_id'] = '';
            }

            return $this->load->view('extension/recurring/squareup', $data);
        }
    }
    
    public function cancel() {
        $this->load->language('extension/recurring/squareup');
        
        $this->load->model('account/recurring');
        $this->load->model('extension/payment/squareup');
        
        if (isset($this->request->get['order_recurring_id'])) {
            $order_recurring_id = $this->request->get['order_recurring_id'];
        } else {
            $order_recurring_id = 0;
        }

        $json = array();
        
        $recurring_info = $this->model_account_recurring->getOrderRecurring($order_recurring_id);

        if ($recurring_info) {
            $this->model_account_recurring->editOrderRecurringStatus($order_recurring_id, ModelExtensionPaymentSquareup::RECURRING_CANCELLED);

            $this->load->model('checkout/order');

            $order_info = $this->model_checkout_order->getOrder($recurring_info['order_id']);

            $this->model_checkout_order->addOrderHistory($recurring_info['order_id'], $order_info['order_status_id'], $this->language->get('text_order_history_cancel'), true);

            $json['success'] = $this->language->get('text_canceled');
        } else {
            $json['error'] = $this->language->get('error_not_found');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
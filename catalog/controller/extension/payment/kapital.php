<?php
class ControllerExtensionPaymentKapital extends Controller {

    protected $urlApi = 'https://epos.az/api';
    protected $params;
    protected $privateKey;

//    function __construct($publicKey = '', $privateKey = '', $params = array())
//    {
//        $this->params = $params;
//        $this->privateKey = trim($privateKey);
//        $this->params['key'] = trim($publicKey);
//    }

    /**
     * Запрос статуса платежа
     * @return mixed
     * Ответ JSON  {"result":"success","info":"Оплата успешно выполнена!","status":"1","cardnumber":"xxxxxx**xxxx"}
     * $params = array('id'=> 10000)
     */
    public function statusPayments()
    {
        $url = $this->urlApi . '/pay2me/status/?' . $this->paramsUrl();
        return $this->_getRequest($url);
    }


    /**
     * Произвести платеж
     * @return mixed
     * Ответ JSON {"result":"success","paymentUrl":"http://site.com/id/ffg3543ergerg","id":"586"}
     * $params = array('amount'=> '0.01','phone'=> '994111111111','cardType'=> '1','successUrl'=>'http://site.com/success','errorUrl'=>'http://site.com/error','payFormType'=>'DESKTOP','currency'=>'AZN');
     */
    public function pay()
    {
        $url = $this->urlApi . '/pay2me/pay/?' . $this->paramsUrl();
        return $this->_getRequest($url);
    }


    /**
     * Запрос кредитной линии, рассрочки ежемесячно
     * @return mixed
     * $params - не передаем
     * Ответ JSON{"result":"success","info":"Запрос успешно выполнен!","instalments":"1,3,18"}
     */
    public function instalments()
    {
        $url = $this->urlApi . '/pay2me/instalments/?' . $this->paramsUrl();
        return $this->_getRequest($url);
    }


    /**
     * Регистрация карты для рекуррентного платежа
     * @return mixed
     * $params = array('amount'=> '0.01','phone'=> '994111111111','cardType'=> '1','returnUrl'=>'http://site.com/success','clientid'=>'10');
     * Ответ JSON {"result":"success","paymentUrl":"http://site.com/id/ffg3543ergerg","id":"586"}
     */
    public function recurrentReg()
    {
        $url = $this->urlApi . '/recurrent/reg/?' . $this->paramsUrl();
        return $this->_getRequest($url);
    }


    /**
     * Рекуррентный платеж
     * $params = array('amount'=> '0.01','phone'=> '994111111111','cardType'=> '1','returnUrl'=>'http://site.com/success','clientid'=> '10','bindingid'=> '10');
     * Ответ JSON {"result":"success","paymentUrl":"http://site.com/id/ffg3543ergerg","id":"586"}
     */
    public function recurrentPay()
    {
        $url = $this->urlApi . '/recurrent/pay/?' . $this->paramsUrl();
        return $this->_getRequest($url);
    }


    /**
     * Двух стадийная оплата
     * $params = array('amount'=> '0.01','phone'=> '994111111111','returnUrl'=>'http://site.com/success');
     * Ответ JSON {"result":"success","paymentUrl":"http://site.com/id/ffg3543ergerg","id":"586"}
     */
    public function twoStagePayment()
    {
        $url = $this->urlApi . '/twoStagePayment/beforePay/?' . $this->paramsUrl();
        return $this->_getRequest($url);
    }


    /**
     * Возврат средств
     * $params = array('id'=> '1002');
     * Ответ JSON {"result":"success","status":"0","info" :"Успешно, в обработке!"}
     */
    public function reversal()
    {
        $url = $this->urlApi . '/pay2me/reversal/?' . $this->paramsUrl();
        return $this->_getRequest($url);
    }


    /**
     * @return bool|string
     * Формируем ссылку с параметрами
     */
    protected function paramsUrl()
    {

        $this->privateKey = $this->config->get('payment_bank_of_baku_privatekey');
        $this->params['key'] = $this->config->get('payment_bank_of_baku_publickey'); //clientId

        ksort($this->params);
        $sum = '';
        $params = '';
        foreach ($this->params as $k => $v) {
            $sum .= (string)$v;
            $params .= '&' . $k . '=' . urlencode($v);  //формируем параметры для платежной ссылки
        }
        $sum .= $this->privateKey;

        return 'sum=' . strtolower(md5($sum)) . $params;

    }


    private function _getRequest($url)
    {
        $header = array('Referer: xxx',
            'Origin: xxx',
            'Content-Type: application/x-www-form-urlencoded',
            'Connection: keep-alive',
            'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'Cache-Control: max-age=0',
            'Except:');

        $ch = curl_init($url);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; MSIE 6.0; Windows NT 5.0; MyIE2; .NET CLR 1.1.4322)");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $content = curl_exec($ch);
        curl_close($ch);

        return $content;
    }


	public function index() {

        $this->load->language('extension/payment/bank_of_baku');

		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['button_reply'] = $this->language->get('text_reply');
        $data['text_fail'] = $this->language->get('text_fail');
        $data['text_help'] = $this->language->get('text_help');

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $this->params = array(
            'amount' => number_format($this->currency->convert($this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false), $order_info['currency_code'], 'AZN'), 2, '.', ''), //mehsulun qiymeti
            'currency' => $this->config->get('payment_bank_of_baku_currency'), //mehsulun valyutasi
            'phone' => (int)preg_replace('/[^0-9]/', '', $order_info['telephone']), //musterinin nomresi
            'email' => $order_info['email'], //musterinin emaili
            'description' => $order_info['order_id'], //sifarisin ID-si
            'cardType' => $this->config->get('payment_bank_of_baku_type'),
            'taksit' => ($this->config->get('payment_bank_of_baku_type') == 2 ? $this->config->get('payment_bank_of_baku_taksit') : 0),
            'payFormType' => 'DESKTOP', // DESKTOP // MOBILE
            'successUrl' => $this->url->link('extension/payment/bank_of_baku/callback', '', true),
            'errorUrl' => $this->url->link('extension/payment/bank_of_baku/callback', '', true),
        );





//        $url = "https://3dstest.bankofbaku.com/newpayment/rest/register.do" .
//        "?amount=". number_format($this->currency->convert($this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false), $order_info['currency_code'], 'AZN'), 2, '', '') ."" .
//        "&currency=944" .
//        "&language=en" .
//        "&orderNumber=".  rand() . $order_info['order_id'] ."" .
//        "&cardType=". $this->config->get('payment_bank_of_baku_type') .
//        "&taksit=". ($this->config->get('payment_bank_of_baku_type') == 2 ? $this->config->get('payment_bank_of_baku_taksit') : 0) .
//        "&payFormType=MOBILE" .
//        "&userName=". $this->config->get('payment_bank_of_baku_publickey') ."" .
//        "&password=". $this->config->get('payment_bank_of_baku_privatekey') ."" .
//        "&returnUrl=". $this->url->link('extension/payment/bank_of_baku/callback', '', true);


//        echo "<pre>";
//        print_r( $url );
//        echo "</pre>";
//        echo "<hr>";

        $url = "https://e-commerce.kapitalbank.az:5443/exec";
        $payment_lang = 'EN';
        $kapital_price = 1 * 100;
        $currency_code = 840;
        $merchantID = "E1000010";
        $cert = '/kapital_cert/E1000010.csr';
//        $cert = '/kapital_cert/dollar/kapital.pem';
        $ssl = '/kapital_cert/dollar/kapital.key';


        //Store your XML Request in a variable
        $body = '<?xml version="1.0" encoding="UTF-8"?>
                <TKKPG>
                    <Request>
                        <Operation>CreateOrder</Operation>
                        <Language>'.$payment_lang.'</Language>
                        <Order>
                            <OrderType>Purchase</OrderType>
                            <Merchant>'.$merchantID.'</Merchant>
                            <Amount>'.$kapital_price.'</Amount>
                            <Currency>'.$currency_code.'</Currency>
                            <Description>Question Market Test</Description>

                            <ApproveURL>https://database.az/kapital/response.php</ApproveURL>
                            <CancelURL>https://database.az/kapital/response.php</CancelURL>
                            <DeclineURL>https://database.az/kapital/response.php</DeclineURL>
                        </Order>
                    </Request>
                </TKKPG>';
        $response = array(
            'body' 		=> $body,
            'cert'      => $_SERVER['DOCUMENT_ROOT'] . $cert,
            'ssl_key'   => $_SERVER['DOCUMENT_ROOT'] . $ssl
        );

        $header = array('Referer: xxx',
            'Origin: xxx',
            'Content-Type: application/x-www-form-urlencoded',
            'Connection: keep-alive',
            'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.3',
            'Cache-Control: max-age=0',
            'Except:');

//        $ch = curl_init($url);
//        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
//        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//        curl_setopt($ch, CURLOPT_HEADER, true);
//        curl_setopt($ch, CURLOPT_POST, true);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);

//        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; MSIE 6.0; Windows NT 5.0; MyIE2; .NET CLR 1.1.4322)");
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

//        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
//        $content = curl_exec($ch);
//        curl_close($ch);



        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $response);
        $content = curl_exec($ch);
        curl_close($ch);


        echo "<hr>";
        echo "<pre>";
        print_r( $response );
        echo "</pre>";
        echo "<hr>";
        echo "<hr>";
        echo "<pre>";
        print_r( $content );
        echo "</pre>";
        echo "<hr>";

        exit;

        $json = $this->pay();

        $json = json_decode($json);

		
		if ( $json->result=="success" ) {
            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], (int)$this->config->get('payment_bank_of_baku_order_status_id'), $this->language->get('text_help') ."<br />". $json->paymentUrl, true);
            $this->session->data['epos_order_id'] = (int)$json->id;
			$data['action'] = $json->paymentUrl;
		} else {

            foreach ($json->info as $json_arr)
            {
                $data['text_fail'] = $data['text_fail'] ."<br />". $json_arr[0];
            } // foreach

			$data['action'] = "";
			// $data['action_fail'] = $this->url->link('checkout/failure', '', true);
			$data['action_fail'] = "#collapse-payment-method";
		}


		return $this->load->view('extension/payment/bank_of_baku', $data);
	}

	public function callback() {

		$this->load->model('checkout/order');


//        Получение статуса платежа
        $this->params = array(
            'id'=> (int)$this->session->data['epos_order_id'],
        );


        $json = $this->statusPayments();
        $json = json_decode($json);


        if ( $json->result=="success" ) {

		    $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], (int)$this->config->get('payment_bank_of_baku_success_order_status_id'), $json->info, true );
		    $this->response->redirect($this->url->link('checkout/success', '', true));

        } else {

            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], (int)$this->config->get('payment_bank_of_baku_failure_order_status_id'), $json->info, true);
            $this->response->redirect($this->url->link('checkout/failure', '', true));

        }

	}
}
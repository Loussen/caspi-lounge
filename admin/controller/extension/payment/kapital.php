<?php



/*
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;

define('_INCLUDED_', true);
session_start();
$sid = md5("database");
// includes
@include_once('../../config.php');
@include_once('../../class.php');

@include_once('../../paypal/paypal_config.php');

// For Guzzle
require '../../vendor/autoload.php';

//error_reporting(E_ALL);//E_ALL
//ini_set('display_errors', '1');

$class = new functions($connect);

$u_id = $class->u_id($sid); //user id

// get lang from session
if(isset($_SESSION['lang'])) {
    $lang = filter_var($_SESSION['lang'], FILTER_SANITIZE_STRING);
}

// if not user
if(!$u_id){
    header('Location: '._basedir_.$lang.'/login');
    exit;
}

// if not post
if(!isset($_POST)){
    header('Location: '._basedir_.$lang.'/market');
    exit;
}


$question_id = $class->stripinput($_POST['question_id']);
$question_id_arr = explode(",", $question_id);



$price = $class->fast_fetch("SELECT count(id) cnt FROM cms_question_market_buys WHERE q_id IN (". $question_id .") AND p_id='". $u_id ."' AND payment_status='1';");
if($price['cnt'] >= 1 || (date("G") >= 22 && (date("N") < 5 || date("N") == 7)))
{
    $_SESSION['error'] = _fl('This question you have already bought.');
    header('Location: '._basedir_.$lang.'/market');
    exit;
}

foreach ($question_id_arr as $question_arr) {
    $question = $class->query("SELECT id, price FROM cms_question_market WHERE q_id = " . $question_arr . " AND status = 1");
    if ($class->num_rows($question) < 1) {

        $_SESSION['error'] = _fl('There is no such question on sale.');

        header('Location: '._basedir_.$lang.'/market');
        exit;
    }
} // foreach



//$price = $class->fast_fetch("SELECT count(id) cnt FROM cms_question_market_buys WHERE q_id = '". $question_id ."' AND payment_status='0' AND `date` > now();");
//if($price['cnt'] >= 1)  {
//
//    $_SESSION['warning'] = _fl('This question is reserved, please select another question or wait 5 minutes.');
//
//    header('Location: '._basedir_.$lang.'/market');
//    exit;
//}

$question_query = $class->query("SELECT q_id, price FROM cms_question_market WHERE q_id IN (". $question_id .") AND status = 1");
$question_price = 0;
$question_price_arr = array();
if ($class->num_rows($question_query) > 0) {
    while ($question_row = $class->fetch_array($question_query)) {

        $question_price_arr[$question_row['q_id']] = $question_row['price'];
        $question_price = $question_price + $question_row['price'];

    } // whille
} // if

if ($question_price==0) {

    $_SESSION['error'] = _fl('Price should not be zero.');

    header('Location: '._basedir_.$lang.'/market');
    exit;
} // if

//$class->debug( $question_price );


$pack_price = $question_price;

$url = 'https://e-commerce.kapitalbank.az:5443/Exec';
$payment_lang = ($lang == 'ru')? 'RU' : 'EN';
$pack_type  = 9;

if ($_POST['payment_type'] == 'kapital') {
    $kapital_price = $pack_price * 100;
    $currency_code = 840;
    $merchantID = "E1180004";
    $cert = 'dollar/kapital.pem';
    $ssl = 'dollar/kapital.key';


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
                            <Description>'. _fl('Question Market') .'</Description>

                            <ApproveURL>https://'._domain_.'/kapital/response.php</ApproveURL>
                            <CancelURL>https://'._domain_.'/kapital/response.php</CancelURL>
                            <DeclineURL>https://'._domain_.'/kapital/response.php</DeclineURL>
                        </Order>
                    </Request>
                </TKKPG>';

    $client = new GuzzleHttp\Client();
    $client->setDefaultOption('verify', false);

    try {

        $response = $client->post($url, [
            'body' 		=> $body,
            'cert'      => _abs_dir_.'kapital/'.$cert,
            'ssl_key'   => _abs_dir_.'kapital/'.$ssl
        ]);
        $response_array = new SimpleXMLElement((string) $response->getBody());
        $response_array = $response_array->Response;
        // var_dump($response_array);
        // exit();

        if (isset($response_array->Status) && ((string) $response_array->Status) === '00')
        {
            $query1 = "
                INSERT INTO `cms_persons_transaction_kapital` (
                        `user_id`,
                        `order_id`,
                        `session_id`,
                        `complete`,
                        `post_date`,
                        `pack_type`
                ) VALUES (
                        '".$u_id."',
                        '".$response_array->Order->OrderID."',
                        '".$response_array->Order->SessionID."',
                        0,
                        NOW(),
                        '".$pack_type."'
                )
            ";

            if($class->query($query1)) {

                foreach ($question_id_arr as $question_arr) {
                    $class->query("
                        INSERT INTO `cms_question_market_buys` (
                            `q_id`,
                            `p_id`,
                            `price`,
                            `payment_type`,
                            `payment_id`,
                            `payment_status`,
                            `date`
                        ) VALUES (
                            " . $question_arr . ",
                            " . $u_id . ",
                            " . $question_price_arr[$question_arr] . ",
                            2,
                            '" . $response_array->Order->OrderID . "',
                            0,
                            NOW()
                        )
                    ");
                } // foreach

            }

            $client_redirect = $response_array->Order->URL.'?OrderID='.$response_array->Order->OrderID.'&SessionID='.$response_array->Order->SessionID;
            header("Location: ".$client_redirect);
            return;
        }
        else {
            print_r($response_array->Status);
        }

    } catch (Exception $error) {
        echo "Exception : " . $error->getMessage();
    }
} elseif ($_POST['payment_type'] == 'paypal') {
    $shipping = 0;
    $total = $shipping + $pack_price;

    //echo $total.'<hr>';

    // Payer
    $payer = new Payer();
    $payer->setPaymentMethod('Paypal');

    // Item
    $item = new Item();
    $item->setName(_fl('Question Market'))
        ->setCurrency('USD')
        ->setQuantity(1)
        ->setPrice($pack_price);

    // Item list
    $itemList = new Itemlist();
    $itemList->setItems([$item]);

    // Details
    $details = new Details();
    $details->setShipping($shipping)
        ->setSubtotal($pack_price);

    // Amount
    $amount = new Amount();
    $amount->setCurrency('USD')
        ->setTotal($total)
        ->setDetails($details);

    // Transaction
    $transaction = new Transaction();
    $transaction->setAmount($amount)
        ->setItemList($itemList)
        ->setDescription('MemberShip')
        ->setInvoiceNumber(uniqid());

    // Payment
    $payment = new Payment();
    $payment->setIntent('sale')
        ->setPayer($payer)
        ->setTransactions([$transaction]);

    // Redirect Urls
    $redirectUrls = new RedirectUrls();
    $redirectUrls->setReturnUrl('https://'._domain_.'/paypal/pay.php?approved=true')
        ->setCancelUrl('https://'._domain_.'/paypal/cancelled.php?approved=false');

    $payment->setRedirectUrls($redirectUrls);

    try {
        $payment->create($api);

        // Generate and store hash
        $hash = md5($payment->getId());
        $_SESSION['paypal_hash'] = $hash;

        // Prepare and execute transaction storage
        $query = "
            INSERT INTO `cms_persons_transaction_paypal` (
                    `user_id`,
                    `payment_id`,
                    `hash`,
                    `complete`,
                    `post_date`,
                    `pack_type`
            ) VALUES (
                    ". $u_id .",
                    '". $payment->getId() ."',
                    '". $hash ."',
                    0,
                    NOW(),
                    '". $pack_type ."'
            )
        ";

        if($class->query($query)) {


            foreach ($question_id_arr as $question_arr) {
                $class->query("
                    INSERT INTO `cms_question_market_buys` (
                        `q_id`,
                        `p_id`,
                        `price`,
                        `payment_type`,
                        `payment_id`,
                        `payment_status`,
                        `date`
                    ) VALUES (
                        " . $question_arr . ",
                        " . $u_id . ",
                        " . $question_price_arr[$question_arr] . ",
                        1,
                        '" . $payment->getId() . "',
                        0,
                        NOW()
                    )
                ");
            } // foreach

        }
    } catch (PPConnectionException $ex) {
        #die($ex);
        header('Location: '._basedir_.'paypal/error.php');
        exit;
    }

    foreach ($payment->getLinks() as $link) {
        if ($link->getRel() == 'approval_url') {
            $redirectUrl = $link->getHref();
        }
    }

    header('Location: ' . $redirectUrl);
} else {
    header('Location: '._basedir_.$lang.'/market');
}
*/











class ControllerExtensionPaymentKapital extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/payment/kapital');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_kapital', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

//			$this->response->redirect($this->url->link('extension/payment', 'user_token=' . $this->session->data['user_token'], true));
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_all_zones'] = $this->language->get('text_all_zones');
		
		$data['text_optionally'] = $this->language->get('text_optionally');

        $data['entry_merchant'] = $this->language->get('entry_merchant');
        $data['entry_ssl_pem'] = $this->language->get('entry_ssl_pem');
		$data['entry_ssl_key'] = $this->language->get('entry_ssl_key');

		$data['entry_currency'] = $this->language->get('entry_currency');
		
		$data['entry_total'] = $this->language->get('entry_total');
		$data['entry_order_status'] = $this->language->get('entry_order_status');
		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');

		$data['help_total'] = $this->language->get('help_total');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

        if (isset($this->error['merchant'])) {
            $data['error_merchant'] = $this->error['merchant'];
        } else {
            $data['error_merchant'] = '';
        }

        if (isset($this->error['ssl_pem'])) {
            $data['error_ssl_pem'] = $this->error['ssl_pem'];
        } else {
            $data['error_ssl_pem'] = '';
        }

		if (isset($this->error['ssl_key'])) {
			$data['error_ssl_key'] = $this->error['ssl_key'];
		} else {
			$data['error_ssl_key'] = '';
		}

		if (isset($this->error['type'])) {
			$data['error_type'] = $this->error['type'];
		} else {
			$data['error_type'] = '';
		}

		if (isset($this->error['currency'])) {
			$data['error_currency'] = $this->error['currency'];
		} else {
			$data['error_currency'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_payment'),
			'href' => $this->url->link('extension/payment', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/kapital', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/kapital', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('extension/payment', 'user_token=' . $this->session->data['user_token'], true);

        if (isset($this->request->post['payment_kapital_merchant'])) {
            $data['payment_kapital_merchant'] = $this->request->post['payment_kapital_merchant'];
        } else {
            $data['payment_kapital_merchant'] = $this->config->get('payment_kapital_merchant');
        }

        if (isset($this->request->post['payment_kapital_ssl_pem'])) {
            $data['payment_kapital_ssl_pem'] = $this->request->post['payment_kapital_ssl_pem'];
        } else {
            $data['payment_kapital_ssl_pem'] = $this->config->get('payment_kapital_ssl_pem');
        }

		if (isset($this->request->post['payment_kapital_ssl_key'])) {
			$data['payment_kapital_ssl_key'] = $this->request->post['payment_kapital_ssl_key'];
		} else {
			$data['payment_kapital_ssl_key'] = $this->config->get('payment_kapital_ssl_key');
		}

		if (isset($this->request->post['payment_kapital_currency'])) {
			$data['payment_kapital_currency'] = $this->request->post['payment_kapital_currency'];
		} else {
			$data['payment_kapital_currency'] = $this->config->get('payment_kapital_currency');
		}

        if (isset($this->request->post['payment_kapital_total'])) {
            $data['payment_kapital_total'] = $this->request->post['payment_kapital_total'];
        } else {
            $data['payment_kapital_total'] = $this->config->get('payment_kapital_total');
        }

        if (isset($this->request->post['payment_kapital_order_status_id'])) {
            $data['payment_kapital_order_status_id'] = $this->request->post['payment_kapital_order_status_id'];
        } else {
            $data['payment_kapital_order_status_id'] = $this->config->get('payment_kapital_order_status_id');
        }

        if (isset($this->request->post['payment_kapital_success_order_status_id'])) {
            $data['payment_kapital_success_order_status_id'] = $this->request->post['payment_kapital_success_order_status_id'];
        } else {
            $data['payment_kapital_success_order_status_id'] = $this->config->get('payment_kapital_success_order_status_id');
        }

        if (isset($this->request->post['payment_kapital_failure_order_status_id'])) {
            $data['payment_kapital_failure_order_status_id'] = $this->request->post['payment_kapital_failure_order_status_id'];
        } else {
            $data['payment_kapital_failure_order_status_id'] = $this->config->get('payment_kapital_failure_order_status_id');
        }

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['payment_kapital_geo_zone_id'])) {
			$data['payment_kapital_geo_zone_id'] = $this->request->post['payment_kapital_geo_zone_id'];
		} else {
			$data['payment_kapital_geo_zone_id'] = $this->config->get('payment_kapital_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['kapital_status'])) {
			$data['payment_kapital_status'] = $this->request->post['payment_kapital_status'];
		} else {
			$data['payment_kapital_status'] = $this->config->get('payment_kapital_status');
		}

		if (isset($this->request->post['payment_kapital_sort_order'])) {
			$data['payment_kapital_sort_order'] = $this->request->post['payment_kapital_sort_order'];
		} else {
			$data['payment_kapital_sort_order'] = $this->config->get('payment_kapital_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/kapital', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/kapital')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

        if (!$this->request->post['payment_kapital_merchant']) {
            $this->error['merchant'] = $this->language->get('error_merchant');
        }

        if (!$this->request->post['payment_kapital_ssl_pem']) {
            $this->error['ssl_pem'] = $this->language->get('error_ssl_pem');
        }

		if (!$this->request->post['payment_kapital_ssl_key']) {
			$this->error['ssl_key'] = $this->language->get('error_ssl_key');
		}

		return !$this->error;
	}
}
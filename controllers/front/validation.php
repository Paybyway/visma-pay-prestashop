<?php

include_once(dirname(__FILE__).'/../../vismapay-php-lib/visma_pay_loader.php');

class VismaPayValidationModuleFrontController extends ModuleFrontController
{
	public function postProcess()
	{
		$return_code = Tools::getValue('RETURN_CODE');
		$order_number = Tools::getValue('ORDER_NUMBER');
		$incident_id = Tools::getValue('INCIDENT_ID');
		$settled = Tools::getValue('SETTLED');
		$contact_id = Tools::getValue('CONTACT_ID');
		$authcode = Tools::getValue('AUTHCODE');

		if(!isset($order_number) || !isset($return_code))
			exit();

		$authcode_confirm = $return_code .'|'. $order_number;

		if(isset($return_code) && $return_code == 0)
		{
			$authcode_confirm .= '|' . $settled;
			if(isset($contact_id) && !empty($contact_id))
				$authcode_confirm .= '|' . $contact_id;
		}
		else if(isset($incident_id) && !empty($incident_id))
			$authcode_confirm .= '|' . $incident_id;

		$authcode_confirm = Tools::strtoupper(hash_hmac('sha256', $authcode_confirm, Configuration::get('VP_PRIVATE_KEY')));

		$cart_id = Tools::getValue('id_cart');
		$cart_secure_key = Tools::getValue('key');
		$cart = new Cart((int)$cart_id);
		$amount = $cart ? $cart->getOrderTotal() : null;

		$query= Db::getInstance()->getRow('SELECT vismapay_order_number, vismapay_amount, vismapay_id FROM '._DB_PREFIX_."cart WHERE id_cart=$cart_id");
		$vismapay_order_number = $query['vismapay_order_number'];
		$vismapay_amount = $query['vismapay_amount'];

		$message = $this->module->l('Order number: ', 'validation') . $order_number . '. ';

		if($authcode_confirm !== $authcode)
		{
			if($return_code == 0)
			{
				$message .= $this->module->l('Authcode mismatch', 'validation');
				$message .= PHP_EOL . $this->module->l('Check the status of the payment from Visma Pay merchant portal!', 'validation');
				$this->module->validateOrder((int)$cart->id, Configuration::get('PS_OS_ERROR'), $vismapay_amount, $this->module->displayName, $message, array(), NULL, false, $cart_secure_key);
			}
			else
			{
				if (_PS_VERSION_ >= "1.7.0.0")
				{
					$vp_url = $cart_url = $this->context->link->getPageLink('cart',
						null,
						$this->context->language->id,
						array('action' => 'show')
					);
					$this->context->smarty->assign('vp_link', $vp_url);
					$this->context->smarty->assign('vp_error', $this->module->l('Payment failed.', 'validation')); 
					$this->setTemplate('module:vismapay/views/templates/front/paymenterror.tpl');
					return;
				}
				else
				{
					Tools::redirect('index.php?controller=order&step=1');
				}
			}
		}
		else if($vismapay_order_number != $order_number)
		{
			if($return_code == 0)
			{
				$message .= $this->module->l('Ordernumber mismatch', 'validation');
				$message .= PHP_EOL . $this->module->l('Check the status of the payment from Visma Pay merchant portal!', 'validation');
				$this->module->validateOrder((int)$cart->id, Configuration::get('PS_OS_ERROR'), $vismapay_amount, $this->module->displayName, $message, array(), NULL, false, $cart_secure_key);
			}
			else
			{
				if(Configuration::get('VP_CLEAR_CART') == 1)
				{
					$this->context->cart = $cart;
					$this->context->cookie->__set('id_cart', $cart_id);
				}

				if (_PS_VERSION_ >= "1.7.0.0")
				{
					$vp_url = $cart_url = $this->context->link->getPageLink('cart',
						null,
						$this->context->language->id,
						array('action' => 'show')
					);
					$this->context->smarty->assign('vp_link', $vp_url);
					$this->context->smarty->assign('vp_error', $this->module->l('Payment failed.', 'validation')); 
					$this->setTemplate('module:vismapay/views/templates/front/paymenterror.tpl');
					return;
				}
				else
				{
					Tools::redirect('index.php?controller=order&step=1');
				}
			}
				
		}
		else
		{
			$privatekey = Configuration::get('VP_PRIVATE_KEY');
			$apikey = Configuration::get('VP_API_KEY');

			$payment = new Visma\VismaPay($apikey, $privatekey);
			$threedsmsg = '';
			$error_message = '';
			$card_country = '';
			$client_country = '';
			$card_message = '';
			$log_msg = '';

			try
			{
				$response = $payment->checkStatusWithOrderNumber($order_number);
				if($response->source->object == "card")	{
					switch($response->source->card_verified) {
						case 'Y':
							$threedsmsg = $this->module->l('3-D Secure was used.','validation');
							$log_msg .= '3-D Secure was used.' . ' ';
							break;
						case 'N':
							$threedsmsg = $this->module->l('3-D Secure was not used.','validation');
							$log_msg .= '3-D Secure was not used.' . ' ';
							break;
						case 'A':
							$threedsmsg = $this->module->l('3-D Secure was attempted but not supported by the card issuer or the card holder is not participating.','validation');
							$log_msg .= '3-D Secure was attempted but not supported by the card issuer or the card holder is not participating.' . ' ';
							break;
						default:
							$threedsmsg = $this->module->l('3-D Secure: No connection to acquirer.','validation');
							$log_msg .= '3-D Secure: No connection to acquirer.' . ' ';
							break;
					}

					if($response->source->error_code != '') {
						switch ($response->source->error_code) {
							case '04':
								$error_message = $this->module->l('The card is reported lost or stolen.','validation');
								$log_msg .= 'The card is reported lost or stolen.' . ' ';
								break;
							case '05':
								$error_message = $this->module->l('General decline. The card holder should contact the issuer to find out why the payment failed.','validation');
								$log_msg .= 'General decline. The card holder should contact the issuer to find out why the payment failed.'. ' ';
								break;
							case '51':
								$error_message = $this->module->l('Insufficient funds. The card holder should verify that there is balance on the account and the online payments are actived.','validation');
								$log_msg .= 'Insufficient funds. The card holder should verify that there is balance on the account and the online payments are actived.' . ' ';
								break;
							case '54':
								$error_message = $this->module->l('Expired card.','validation');
								$log_msg .= 'Expired card.'. ' ';
								break;
							case '61':
								$error_message = $this->module->l('Withdrawal amount limit exceeded.','validation');
								$log_msg .= 'Withdrawal amount limit exceeded.' . ' ';
								break;
							case '62':
								$error_message = $this->module->l('Restricted card. The card holder should verify that the online payments are actived.','validation');
								$log_msg .= 'Restricted card. The card holder should verify that the online payments are actived.' . ' ';
								break;
							case '1000':
								$error_message = $this->module->l('Timeout communicating with the acquirer. The payment should be tried again later.','validation');
								$log_msg .= 'Timeout communicating with the acquirer. The payment should be tried again later.' . ' ';
								break;
							default:
								$error_message = $this->module->l('No error for code','validation') . ' \"' . $response->source->error_code . '\"' ;
								$log_msg .= 'No error for code' . ' \"' . $response->source->error_code . '\"' . ' ';
								break;
						}
					}

					if($response->source->card_country != '') {
						$card_country = $this->module->l('Card ISO 3166-1 country code:','validation') . ' ' . $response->source->card_country;
						$log_msg .= 'Card ISO 3166-1 country code:' . $response->source->card_country;
					}

					if($response->source->client_ip_country != '') {
						$client_country = $this->module->l('Client ISO 3166-1 country code:','validation') . ' ' . $response->source->client_ip_country;
						$log_msg .= 'Client ISO 3166-1 country code:' . $response->source->client_ip_country;
					}

					$message .= PHP_EOL . $this->module->l($threedsmsg,'validation') . PHP_EOL . ($error_message != '' ? $this->module->l($error_message,'validation') . PHP_EOL : '') . ($card_country != '' ? $this->module->l($card_country,'validation') . PHP_EOL : '') . ($client_country != '' ? $this->module->l($client_country,'validation') . PHP_EOL : '');
				}

				if($response->source->brand != '')
					$message .= $this->module->l('Payment method:', 'validation') . ' ' . $response->source->brand . '.' . PHP_EOL;
			}
			catch (Visma\VismaPayException $e) 
			{
				Logger::addLog('Visma Pay: Order Number: ' . $order_number . ' - Status check Exception: ' . print_r($e,true), 1, null, null, null, true);
			}

			switch ($return_code) 
			{
				case 0:
					if($settled == 0)
					{
						$order = Order::getOrderByCartId((int)$cart->id);
						if(!$order)
						{
							$message .= $this->module->l('Payment authorized.', 'validation');
							
							if($amount == $vismapay_amount)
								$status = Configuration::get('VP_OS_AUTHORIZED');
							else
								$status = Configuration::get('PS_OS_ERROR');

							$this->module->validateOrder((int)$cart->id, $status, $vismapay_amount, $this->module->displayName, $message, array(), Context::getContext()->currency->id, false, $cart_secure_key);
							
							$order = Order::getOrderByCartId((int)$cart_id);
							if($order && $amount != $vismapay_amount)
							{
								$err_msg = new Message();
								$error_message = $this->module->l('NOTE !! Paid sum does not match order sum, verify order contents from the customer or Visma Pay merchant-portal.', 'validation');
								$err_msg->message = $error_message;
								$err_msg->private = 1;
								$err_msg->id_order = (int)$order;
								$err_msg->add();
							}
						}
					}
					else
					{
						$order = Order::getOrderByCartId((int)$cart->id);
						if(!$order)
						{
							$message .= $this->module->l('Payment accepted.', 'validation');
							$this->module->validateOrder((int)$cart->id, Configuration::get('PS_OS_PAYMENT'), $vismapay_amount, $this->module->displayName, $message, array(), NULL, false, $cart_secure_key);

							$order = Order::getOrderByCartId((int)$cart_id);
							if($order && $amount != $vismapay_amount)
							{
								$err_msg = new Message();
								$error_message = $this->module->l('NOTE !! Paid sum does not match order sum, verify order contents from the customer or Visma Pay merchant-portal.', 'validation');
								$err_msg->message = $error_message;
								$err_msg->private = 1;
								$err_msg->id_order = (int)$order;
								$err_msg->add();
							}
						}
					}

					break;
				case 4:
					if(Configuration::get('VP_CLEAR_CART') == 1)
					{
						$this->context->cart = $cart;
						$this->context->cookie->__set('id_cart', $cart_id);
					}

					$failmsg = 'payment failed on order: ' . $order_number .
					Logger::addLog('Visma Pay response: ' . $failmsg . ' Transaction status could not be updated after customer returned from the web page of a bank. Please use the merchant UI to resolve the payment status.', 1, null, null, null, true);
					if (_PS_VERSION_ >= "1.7.0.0")
					{
						$vp_url = $cart_url = $this->context->link->getPageLink('cart',
							null,
							$this->context->language->id,
							array('action' => 'show')
						);
						$this->context->smarty->assign('vp_link', $vp_url);
						$this->context->smarty->assign('vp_error', $this->module->l('Payment failed.', 'validation')); 
						$this->setTemplate('module:vismapay/views/templates/front/paymenterror.tpl');
						return;
					}
					else
					{
						Tools::redirect('index.php?controller=order&step=1');
					}
					break;
				case 10:
					if(Configuration::get('VP_CLEAR_CART') == 1)
					{
						$this->context->cart = $cart;
						$this->context->cookie->__set('id_cart', $cart_id);
					}

					$failmsg = 'payment failed on order: ' . $order_number .
					Logger::addLog('Visma Pay response: ' . $failmsg . 'Maintenance break. The transaction is not created and the user has been notified and transferred back to the cancel address.', 1, null, null, null, true);
					if (_PS_VERSION_ >= "1.7.0.0")
					{
						$vp_url = $cart_url = $this->context->link->getPageLink('cart',
							null,
							$this->context->language->id,
							array('action' => 'show')
						);
						$this->context->smarty->assign('vp_link', $vp_url);
						$this->context->smarty->assign('vp_error', $this->module->l('Payment failed.', 'validation')); 
						$this->setTemplate('module:vismapay/views/templates/front/paymenterror.tpl');
						return;
					}
					else
					{
						Tools::redirect('index.php?controller=order&step=1');
					}
					break;
				default:
					if(Configuration::get('VP_CLEAR_CART') == 1)
					{
						$this->context->cart = $cart;
						$this->context->cookie->__set('id_cart', $cart_id);
					}
					
					$failmsg = 'payment failed on order: ' . $order_number . ' Payment method: ' . (isset($response->source->brand) ? $response->source->brand : '');
					Logger::addLog('Visma Pay response: ' . $failmsg . ($log_msg != '' ? ', details: ' . $log_msg : ''), 1, null, null, null, true);
					if (_PS_VERSION_ >= "1.7.0.0")
					{
						$vp_url = $cart_url = $this->context->link->getPageLink('cart',
							null,
							$this->context->language->id,
							array('action' => 'show')
						);
						$this->context->smarty->assign('vp_link', $vp_url);
						$this->context->smarty->assign('vp_error', $this->module->l('Payment failed.', 'validation')); 
						$this->setTemplate('module:vismapay/views/templates/front/paymenterror.tpl');
						return;
					}
					else
					{
						Tools::redirect('index.php?controller=order&step=1');
					}
					break;	
			}
		}

		$redirect = __PS_BASE_URI__ . 'order-confirmation.php?key='. $cart_secure_key . '&id_cart=' . (int)$cart_id . '&id_module=' . (int)$this->module->id;

		if ((int)$this->context->cookie->id_cart)
			$this->context->cookie->__unset('id_cart');

		Tools::redirectLink($redirect);
	}
}

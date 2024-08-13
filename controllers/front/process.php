<?php

include_once(dirname(__FILE__).'/../../vismapay-php-lib/visma_pay_loader.php');

class VismaPayProcessModuleFrontController extends ModuleFrontController
{
	public function initContent() 
	{
		parent::initContent();
		$this->ajax = true;
	}

	public function displayAjax()
	{
		$cart_id = Tools::getValue('cart_id');
		if($this->context->cart->id != (int)$cart_id)
			exit();

		if(Configuration::get('VP_CLEAR_CART') == 1)
		{
			$cart = new Cart($this->context->cart->id);
			$this->context->cookie->__unset('id_cart');
			unset($this->context->cart);
			$this->context->cart = new Cart();
		}
		else
			$cart = $this->context->cart;

		$privatekey = Configuration::get('VP_PRIVATE_KEY');
		$apikey = Configuration::get('VP_API_KEY');

		$raw_amount = $cart->getOrderTotal();
		$amount = $cart ? (int)(round($cart->getOrderTotal() * 100, 0)) : null;

		$order_number = $this->module->generate_order_number($cart);

		Db::getInstance()->Execute("UPDATE "._DB_PREFIX_."cart SET vismapay_order_number='$order_number' WHERE id_cart=$cart->id");
		Db::getInstance()->Execute("UPDATE "._DB_PREFIX_."cart SET vismapay_amount='$raw_amount' WHERE id_cart=$cart->id");

		$payment = new Visma\VismaPay($apikey, $privatekey);

		$customer = new Customer((int)$cart->id_customer);

		if(Configuration::get('VP_SEND_CONFIRMATION') == 1)
			$email = htmlspecialchars($customer->email);
		else
			$email = null;

		$currency = new Currency($cart->id_currency);
		$order_currency = $currency->iso_code;

		$payment->addCharge(
			array(
			'order_number' => $order_number,
			'amount' => $amount, 
			'currency' => $order_currency,
			'email' => $email
			)
		);

		$address = new Address((int)$cart->id_address_invoice);
		$shipping_address = new Address((int)$cart->id_address_delivery);
		$phone = isset($address->phone) ? $address->phone : (isset($address->phone_mobile) ? $address->phone_mobile : '');

		$payment->addCustomer(
			array(
				'firstname' => htmlspecialchars($address->firstname), 
				'lastname' => htmlspecialchars($address->lastname), 
				'email' => htmlspecialchars($customer->email), 
				'address_street' => htmlspecialchars($address->address1.' '.$address->address2),
				'address_city' => htmlspecialchars($address->city),
				'address_zip' => htmlspecialchars($address->postcode),
				'address_country' => htmlspecialchars($address->country),
				'shipping_firstname' => htmlspecialchars($shipping_address->firstname),
				'shipping_lastname' => htmlspecialchars($shipping_address->lastname),
				'shipping_email' => htmlspecialchars($customer->email),
				'shipping_address_street' => htmlspecialchars($shipping_address->address1.' '.$shipping_address->address2),
				'shipping_address_city' => htmlspecialchars($shipping_address->city),
				'shipping_address_zip' => htmlspecialchars($shipping_address->postcode),
				'shipping_address_country' => htmlspecialchars($shipping_address->country),
				'phone' => preg_replace('/[^0-9+ ]/', '', $phone),
			)
		);

		$products = array();
		$total_amount = 0;

		$cartProducts = $cart->getProducts(true);
		foreach($cartProducts as $item)
		{
			array_push($products, array(
					'id' => $item['reference'],
					'title' => $item['name'],
					'count' => $item['cart_quantity'],
					'pretax_price' => (int)(round($item['price']*100, 0)),
					'tax' => number_format($item['rate'], 2, '.', ''),
					'price' => (int)(round($item['price_wt']*100, 0)),
					'type' => 1
					)
				);
			$total_amount += (int)(round($item['price_wt']*100, 0) * $item['cart_quantity']);
		 }

		$carrier = new Carrier($cart->id_carrier);
		$shippingcost = $cart->getOrderShippingCost($cart->id_carrier);
		$shippingpretaxcost = $cart->getOrderShippingCost($cart->id_carrier, false);
		$shippingtaxrate = $carrier->getTaxesRate($shipping_address);

		array_push($products, array(
				'id' => $carrier->id_reference,
				'title' => $carrier->name,
				'count' => 1,
				'pretax_price' => (int)(round($shippingpretaxcost * 100, 0)),
				'tax' => number_format($shippingtaxrate, 2, '.', ''),
				'price' => (int)(round($shippingcost * 100, 0)),
				'type' => 2
				)
			);

		$total_amount += (int)(round($shippingcost * 100, 0));

		$summary = $cart->getSummaryDetails();
		$discounts = (int)(round($summary['total_discounts']*100, 0));

		//Discount as new item, wont be recorded if not present, presta's discount info stored as + numbers
		if(($discounts) != 0)
		{
			array_push($products, array(
				'id' => 1,
				'title' => $this->module->l('Total discounts', 'process'),
				'count' => 1,
				'pretax_price' => -$discounts,
				'tax' => 0,
				'price' => -$discounts,
				'type' => 4
				)
			);
			$total_amount -= $discounts;
		}

		$send_items = Configuration::get('VP_SEND_ITEMS');

		if(($total_amount == $amount && $send_items == 1) || $send_items == 2)
		{
			foreach($products as $product)
			{
				$payment->addProduct(
					array(
						'id' => htmlspecialchars($product['id']),
						'title' => htmlspecialchars($product['title']),
						'count' => $product['count'],
						'pretax_price' => $product['pretax_price'],
						'tax' => $product['tax'],
						'price' => $product['price'],
						'type' => $product['type']
					)
				);
			}
		}

		$lang = new Language((int)($this->context->language->id));
		$lang = $lang->iso_code;
		$lang = Tools::strtolower($lang);
		if($lang != 'fi'  && $lang != 'en' && $lang != 'sv' && $lang != 'ru')
			$lang = 'en';

		$selected = array();

		if(Configuration::get('VP_EMBEDDED') == '1' || Configuration::get('VP_EMBEDDED') == '2')
			$selected[] = Tools::getValue('selected', null);
		else
		{
			$currency = new Currency($cart->id_currency);
			$order_currency = $currency->iso_code;

			if(in_array($order_currency, array('EUR')))
			{
				if(Configuration::get('VP_SELECT_BANKS') != '')
					$selected[] .= 'banks';
				if(Configuration::get('VP_SELECT_CCARDS') != '')
					$selected[] .= 'creditcards';
				if(Configuration::get('VP_SELECT_CINVOICES') != '')
					$selected[] .= 'creditinvoices';
				if(Configuration::get('VP_SELECT_WALLETS') != '')
					$selected[] .= 'wallets';
			}
			else
			{
				try
				{
					$response = $payment->getMerchantPaymentMethods($order_currency);

					if($response->result == 0 && count($response->payment_methods) > 0)
					{
						foreach ($response->payment_methods as $method)
						{
							if($method->group == 'creditcards'  && Configuration::get('VP_SELECT_CCARDS') != '')
							{
								$selected[] = $method->group; //creditcards
							}
							else if($method->group == 'wallets' && Configuration::get('VP_SELECT_WALLETS') != '')
							{
								$selected[] = $method->selected_value;
							}
							else if($method->group == 'banks' && Configuration::get('VP_SELECT_BANKS') != '')
							{
								$selected[] = $method->selected_value;
							}
							else if($method->group == 'creditinvoices')
							{
								if(Configuration::get('VP_SELECT_LASKUYRITYKSELLE') != '' && $method->selected_value == 'laskuyritykselle')
									$selected[] = $method->selected_value;
								else if($method->selected_value != 'laskuyritykselle' && $amount >= $method->min_amount && $amount <= $method->max_amount && Configuration::get('VP_SELECT_CINVOICES') != '')
									$selected[] = $method->selected_value;
							}
						}
					}
				}
				catch (Visma\VismaPayException $e) 
				{
					$message = $e->getMessage();
					Logger::addLog('Visma Pay getMerchantPaymentMethods exception: ' . $message . ', Check your credentials in the module settings.', 3, null, null, null, true);

					$error = $this->module->l('Payment failed, please try again.', 'process');
					$error = $this->module->displayError($error);
					die(Tools::jsonEncode(array('payment_url' => null, 'error' => $error)));
				}
				
			}
			if(empty($selected)) // No payment methods available
			{
				$error = $this->module->l('Visma Pay: No payment methods available for the currency: ', 'process') . $order_currency;
				$error = $this->module->displayError($error);
				die(Tools::jsonEncode(array('payment_url' => null, 'error' => $error)));
			}		
		}

		$params = array('id_cart' => (int)($cart->id), 'key' => $cart->secure_key);
		$return_url = $this->context->link->getModuleLink('vismapay', 'validation', $params, Configuration::get('PS_SSL_ENABLED'));

		$payment->addPaymentMethod(
			array(
				'type' => 'e-payment', 
				'return_url' => $return_url,
				'notify_url' => $return_url,
				'lang' => $lang,
				'selected' => $selected
			)
		);

		$url = null;
		$error = null;

		try
		{
			$response = $payment->createCharge();

			if($response->result == 0)
				$url = Visma\VismaPay::API_URL."/token/".$response->token;
			else
			{
				$error = $this->module->l('Payment failed, please try again. (Error code ', 'process'). $response->result .')';

				if($response->result == 10)
					$error = $this->module->l('Visma Pay system is currently in maintenance. Please try again in a few minutes.', 'process');

				$error = $this->module->displayError($error);

				$validation_errors = '';
				if (count($response->errors) > 0) {
					foreach ($response->errors as $value) {
						$validation_errors .= ' ' . $value;
					}
				}
				else
					$validation_errors .= 'none';

				Logger::addLog('Visma Pay response: Validation error, vismapay order: '.$order_number.', reason(s):' . $validation_errors , 3, null, null, null, true);
			}
		}
		catch (Visma\VismaPayException $e) 
		{
			$result = $e->getCode();
			$message = $e->getMessage();

			if($result == 2)
				Logger::addLog('Visma Pay exception 2: ' . $message . ', vismapay order: '.$order_number, 3, null, null, null, true);
			elseif($result == 3)
				Logger::addLog('Visma Pay exception 3: ' . $message . ', vismapay order: '.$order_number, 3, null, null, null, true);
			elseif($result == 4)
				Logger::addLog('Visma Pay exception 4: ' . $message . ', vismapay order: '.$order_number, 3, null, null, null, true);
			elseif($result == 5)
				Logger::addLog('Visma Pay exception 5: ' . $message . ', vismapay order: '.$order_number, 3, null, null, null, true);

			$error = $this->module->l('Payment failed, please try again.', 'process');
			$error = $this->module->displayError($error);
		}

		die(Tools::jsonEncode(array('payment_url' => $url, 'error' => $error)));
	}
}

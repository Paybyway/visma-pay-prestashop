<?php

if (!defined('_PS_VERSION_')){
  	exit;
}

class VismaPay extends PaymentModule
{
	private $allowed_currencies;
	private $banner_images;

	public function __construct()
	{
		$this->name = 'vismapay';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.1';
		$this->author = 'Visma';
		$this->currencies = true;
		$this->banner_images = glob(dirname(__FILE__).'/views/img/banners/*.{jpg,jpeg,gif,png}', GLOB_BRACE);

		$this->bootstrap = true;
		$this->display = 'view';
		parent::__construct();

		$this->controllers = array('process', 'validation', 'settlement');
		$this->displayName = $this->l('Visma Pay');
		$this->description = $this->l('Accept e-payments with Visma Pay payment gateway.');

		$this->allowed_currencies = array('EUR');
	}

	public function install()
	{
		if (!parent::install() || !$this->registerHook('displayPayment') || !$this->registerHook('paymentReturn') || !$this->registerHook('displayAdminOrder')  || !$this->registerHook('displayHeader')  || !$this->registerHook('displayInvoice'))
			return false;

		if (_PS_VERSION_ >= "1.7.0.0") {
			if (!$this->registerHook('paymentOptions')) {
				return false;
			}
		}

		Configuration::updateValue('VP_PRIVATE_KEY', '');
		Configuration::updateValue('VP_API_KEY', '');
		Configuration::updateValue('VP_ORDER_PREFIX' , '');
		Configuration::updateValue('VP_SELECT_BANKS', 'BANKS');
		Configuration::updateValue('VP_SELECT_WALLETS', 'WALLETS');
		Configuration::updateValue('VP_SELECT_CCARDS', 'CREDITCARDS');
		Configuration::updateValue('VP_SELECT_CINVOICES', 'CREDITINVOICES');
		Configuration::updateValue('VP_SELECT_LASKUYRITYKSELLE', 'LASKUYRITYKSELLE');
		Configuration::updateValue('VP_SEND_ITEMS', 1);
		Configuration::updateValue('VP_SEND_CONFIRMATION', 1);
		Configuration::updateValue('VP_CLEAR_CART', 0);
		Configuration::updateValue('VP_EMBEDDED', '1');
		Configuration::updateValue('VP_BANNER_IMG', '');

		Db::getInstance()->Execute('ALTER TABLE '._DB_PREFIX_.'cart ADD vismapay_order_number varchar(255)');
		Db::getInstance()->Execute('ALTER TABLE '._DB_PREFIX_.'cart ADD vismapay_amount decimal(20,6)');
		Db::getInstance()->Execute('ALTER TABLE '._DB_PREFIX_.'cart ADD vismapay_id int(10)');

		if (!Configuration::get('VP_OS_AUTHORIZED'))
		{
			$orderState = new OrderState();
			$orderState->name = array();

			foreach (Language::getLanguages() as $language)
			{
				if (Tools::strtolower($language['iso_code']) == 'fi')
					$orderState->name[$language['id_lang']] = 'Visma Pay - Maksu varmennettu';
				else
					$orderState->name[$language['id_lang']] = 'Visma Pay - Payment authorized';
			}

			$orderState->send_email = false;
			$orderState->color = '#126cff';
			$orderState->hidden = false;
			$orderState->delivery = false;
			$orderState->logable = false;
			$orderState->invoice = false;

			if(!$orderState->add())
			{
				return false;
			}

			$source = dirname(__FILE__).'/logo.gif';
			$destination = dirname(__FILE__).'/../../img/os/'.(int)$orderState->id.'.gif';
			copy($source, $destination);
			Configuration::updateValue('VP_OS_AUTHORIZED', (int)$orderState->id);
		}

		return true;
	}

	public function uninstall()
	{
		if(!parent::uninstall())
			return false;

		Configuration::deleteByName('VP_PRIVATE_KEY');
		Configuration::deleteByName('VP_API_KEY');
		Configuration::deleteByName('VP_ORDER_PREFIX');
		Configuration::deleteByName('VP_BANNER_IMG');
		Configuration::deleteByName('VP_SELECT_BANKS');
		Configuration::deleteByName('VP_SELECT_WALLETS');
		Configuration::deleteByName('VP_SELECT_CCARDS');
		Configuration::deleteByName('VP_SELECT_CINVOICES');
		Configuration::deleteByName('VP_SELECT_LASKUYRITYKSELLE');
		Configuration::deleteByName('VP_SEND_ITEMS');
		Configuration::deleteByName('VP_SEND_CONFIRMATION');
		Configuration::deleteByName('VP_CLEAR_CART');
		Configuration::deleteByName('VP_EMBEDDED');

		Db::getInstance()->Execute('ALTER TABLE '._DB_PREFIX_.'cart DROP COLUMN vismapay_order_number');
		Db::getInstance()->Execute('ALTER TABLE '._DB_PREFIX_.'cart DROP COLUMN vismapay_amount');
		Db::getInstance()->Execute('ALTER TABLE '._DB_PREFIX_.'cart DROP COLUMN vismapay_id');

		$order_state_id = Configuration::get('VP_OS_AUTHORIZED');
		$order_state = new OrderState((int)$order_state_id);
		$order_state->delete();
		Configuration::deleteByName('VP_OS_AUTHORIZED');
		unlink(dirname(__FILE__).'/../../img/os/'. $order_state_id .'.gif');
		
		return true;
	}


	public function getContent()
	{
		$output = null;

		if (Tools::isSubmit('submit'.$this->name))
		{
			$private_key = (string)Tools::getValue('VP_PRIVATE_KEY');
			$api_key = (string)Tools::getValue('VP_API_KEY');
			$order_prefix = (string)Tools::getValue('VP_ORDER_PREFIX');
			$banner_img = (string)Tools::getValue('VP_BANNER_IMG');

			$validated = true;
			if( !empty($order_prefix) && !Validate::isConfigName($order_prefix))
			{
				$output .= $this->displayError( $this->l('Invalid order prefix'));
				$validated = false;
			}

			if(!$private_key || empty($private_key))
			{
				$output .= $this->displayError( $this->l('Invalid private key') );
				$validated = false;
			}

			if(!$api_key || empty($api_key))
			{
				$output .= $this->displayError( $this->l('Invalid api key') );
				$validated = false;
			}

			if($banner_img && !file_exists(dirname(__FILE__).'/views/img/banners/'.$banner_img))
			{
				echo $banner_img .' - ' . dirname(__FILE__).'/views/img/banners/'.$banner_img;
				$output .= $this->displayError( $this->l('Banner file not found in modules/vismapay/views/img/banners/ folder') );
				$validated = false;
			}

			if($validated)
			{
				Configuration::updateValue('VP_PRIVATE_KEY', $private_key);
				Configuration::updateValue('VP_API_KEY', $api_key);
				Configuration::updateValue('VP_ORDER_PREFIX', $order_prefix);
				Configuration::updateValue('VP_BANNER_IMG', $banner_img);
				Configuration::updateValue('VP_SELECT_BANKS', Tools::getValue('VP_SELECT_BANKS'));
				Configuration::updateValue('VP_SELECT_WALLETS', Tools::getValue('VP_SELECT_WALLETS'));
				Configuration::updateValue('VP_SELECT_CCARDS', Tools::getValue('VP_SELECT_CCARDS'));
				Configuration::updateValue('VP_SELECT_CINVOICES', Tools::getValue('VP_SELECT_CINVOICES'));
				Configuration::updateValue('VP_SELECT_LASKUYRITYKSELLE', Tools::getValue('VP_SELECT_LASKUYRITYKSELLE'));
				Configuration::updateValue('VP_SEND_ITEMS', Tools::getValue('VP_SEND_ITEMS'));
				Configuration::updateValue('VP_SEND_CONFIRMATION', Tools::getValue('VP_SEND_CONFIRMATION'));
				Configuration::updateValue('VP_CLEAR_CART', Tools::getValue('VP_CLEAR_CART'));
				Configuration::updateValue('VP_EMBEDDED', Tools::getValue('VP_EMBEDDED'));
				$output .= $this->displayConfirmation($this->l('Settings updated'));
			}
		}

		$output .= $this->renderForm();


		if (_PS_VERSION_ < "1.7.0.0") {
			$output .= $this->displayBanners();
		}		

		return $output;
	}

	public function renderForm()
	{
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		$options = array();
		foreach ($this->banner_images as $banner)
		{
			$options[] = array(
				"id_option" => basename($banner),
				"name" => basename($banner)
			);
		}
		$options[] = array(
				'id_option' => '',
				'name' => $this->l('No banner'),
			);

		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Settings'),
				'icon' => 'icon-cogs'
			),
			'input' => array(),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'button'
			)
		);

		array_push($fields_form[0]['form']['input'],
			array(
				'type' => 'text',
				'label' => $this->l('Private key'),
				'name' => 'VP_PRIVATE_KEY',
				'class' => 'fixed-width-xxl',
				'size' => 50,
				'required' => true
			),
			array(
				'type' => 'text',
				'label' => $this->l('Api key'),
				'name' => 'VP_API_KEY',
				'class' => 'fixed-width-xxl',
				'size' => 50,
				'required' => true
			),
			array(
				'type' => 'text',
				'label' => $this->l('Order number prefix'),
				'name' => 'VP_ORDER_PREFIX',
				'class' => 'fixed-width-xxl',
				'size' => 50,
				'required' => false
			)
		);

		if (_PS_VERSION_ < "1.7.0.0") {
			array_push($fields_form[0]['form']['input'],
				array(
					'type'      => 'radio',
					'label'     => $this->l('Embedded'),
					'desc'      => $this->l('Use embedded payment').'.',
					'name'      => 'VP_EMBEDDED',
					'class'     => 't', 
					'values'    => array(
						array(
							'id'    => 'active_on',
							'value' => 1,
							'label' => $this->l('Enabled')
						),
						array(
							'id'    => 'active_off',
							'value' => 0,
							'label' => $this->l('Disabled')
						)
					)
				)
			);
		}
		else {
			array_push($fields_form[0]['form']['input'],
				array(
					'type'      => 'radio',
					'label'     => $this->l('Embedded'),
					'desc'      => $this->l('Use embedded payment').". <br/>"
						.' - '.$this->l('Separated: All the payment methods on your Visma Pay merchant account are separated as their own payment method on the checkout-page.')."<br/>"
						.' - '.$this->l('Embedded: After choosing Visma Pay on the checkout-page, the payment methods and their logos are then shown.')."<br/>"
						.' - '.$this->l('Disabled: After choosing Visma Pay at the checkout-page the customer is redirected to the Visma Pay payment-page. ')."<br/>",
					'name'      => 'VP_EMBEDDED',
					'class'     => 't', 
					'values'    => array(
						array(
							'id'    => 'active_on',
							'value' => 2,
							'label' => $this->l('Separated')
						),
						array(
							'id'    => 'active_maybe',
							'value' => 1,
							'label' => $this->l('Embedded')
						),
						array(
							'id'    => 'active_off',
							'value' => 0,
							'label' => $this->l('Disabled')
						)
					)
				)
			);
		}

		array_push($fields_form[0]['form']['input'],
			array(
				'type'      => 'radio',
				'label'     => $this->l('Wallets'),
				'desc'      => $this->l('Enable wallet services in the Visma Pay payment page.'),
				'name'      => 'VP_SELECT_WALLETS',
				'class'     => 't', 
				'values'    => array(
					array(
						'id'    => 'active_on',
						'value' => 'WALLETS',
						'label' => $this->l('Enabled')
					),
					array(
						'id'    => 'active_off',
						'value' => '',
						'label' => $this->l('Disabled')
					),
				),
			),
			array(
				'type'      => 'radio',
				'label'     => $this->l('Banks'),
				'desc'      => $this->l('Enable bank payments in the Visma Pay payment page.'),
				'name'      => 'VP_SELECT_BANKS',
				'class'     => 't', 
				'values'    => array(
					array(
						'id'    => 'active_on',
						'value' => 'BANKS',
						'label' => $this->l('Enabled')
					),
					array(
						'id'    => 'active_off',
						'value' => '',
						'label' => $this->l('Disabled')
					),
				),
			),
			array(
				'type'      => 'radio',
				'label'     => $this->l('Credit cards'),
				'desc'      => $this->l('Enable credit cards in the Visma Pay payment page.'),
				'name'      => 'VP_SELECT_CCARDS',
				'class'     => 't', 
				'values'    => array(
					array(
						'id'    => 'active_on',
						'value' => 'CREDITCARDS',
						'label' => $this->l('Enabled')
					),
					array(
						'id'    => 'active_off',
						'value' => '',
						'label' => $this->l('Disabled')
					)
				),
			),
			array(
				'type'      => 'radio',
				'label'     => $this->l('Credit invoices'),
				'desc'      => $this->l('Enable credit invoices in the Visma Pay payment page.'),
				'name'      => 'VP_SELECT_CINVOICES',
				'class'     => 't', 
				'values'    => array(
					array(
						'id'    => 'active_on',
						'value' => 'CREDITINVOICES',
						'label' => $this->l('Enabled')
					),
					array(
						'id'    => 'active_off',
						'value' => '',
						'label' => $this->l('Disabled')
					)
				),
			),
			array(
				'type'      => 'radio',
				'label'     => $this->l('Fellow Yrityslasku'),
				'desc'      => $this->l('Enable Fellow Yrityslasku in the Visma Pay payment page.'),
				'name'      => 'VP_SELECT_LASKUYRITYKSELLE',
				'class'     => 't', 
				'values'    => array(
					array(
						'id'    => 'active_on',
						'value' => 'LASKUYRITYKSELLE',
						'label' => $this->l('Enabled')
					),
					array(
						'id'    => 'active_off',
						'value' => '',
						'label' => $this->l('Disabled')
					)
				)
			),
			array(
				'type'      => 'radio',
				'label'     => $this->l('Send product details to Visma Pay'),
				'desc'      => $this->l('Enable, disable or force sending product details to Visma Pay, should normally be Enable.'),
				'name'      => 'VP_SEND_ITEMS',
				'class'     => 't', 
				'values'    => array(
					array(
						'id'    => 'active_on',
						'value' => 2,
						'label' => $this->l('Forced')
					),
					array(
						'id'    => 'active_maybe',
						'value' => 1,
						'label' => $this->l('Enabled')
					),
					array(
						'id'    => 'active_off',
						'value' => 0,
						'label' => $this->l('Disabled')
					)
				)
			),
			array(
				'type'      => 'radio',
				'label'     => $this->l('Send Visma Pay payment confirmation'),
				'desc'      => $this->l('Send a payment confirmation to the customer\'s email from Visma Pay.'),
				'name'      => 'VP_SEND_CONFIRMATION',
				'class'     => 't', 
				'values'    => array(
					array(
						'id'    => 'active_on',
						'value' => 1,
						'label' => $this->l('Enabled')
					),
					array(
						'id'    => 'active_off',
						'value' => 0,
						'label' => $this->l('Disabled')
					)
				)
			),
			array(
				'type'      => 'radio',
				'label'     => $this->l('Clear customer\'s cart when they are redirected to pay'),
				'desc'      => $this->l('When this option is enabled, the customer\'s shopping cart will be emptied when they are redirected to pay for their order. The cart will be restored if the customer cancels their payment or the payment fails.'),
				'name'      => 'VP_CLEAR_CART',
				'class'     => 't', 
				'values'    => array(
					array(
						'id'    => 'active_on',
						'value' => 1,
						'label' => $this->l('Enabled')
					),
					array(
						'id'    => 'active_off',
						'value' => 0,
						'label' => $this->l('Disabled')
					)
				)
			)			
		);



		if (_PS_VERSION_ < "1.7.0.0") {
			$fields_form[0]['form']['input'][] = array(
					'type' => 'select',
					'label' => $this->l('Banner shown in payment page:'),
					'desc' => $this->l('Optional banner visible when embedded payment is disabled.'),
					'name' => 'VP_BANNER_IMG',
					'required' => true,
					'options' => array(
						'query' => $options,
						'id' => 'id_option',
						'name' => 'name'
					)
				);
		}

		$helper = new HelperForm();

		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;

		$helper->title = $this->displayName;
		$helper->show_toolbar = true;
		$helper->toolbar_scroll = true;
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' =>
			array(
			'desc' => $this->l('Save'),
			'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
			'&token='.Tools::getAdminTokenLite('AdminModules'),
			),
			'back' => array(
			'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
			'desc' => $this->l('Back to list')
			)
		);

		$helper->fields_value['VP_PRIVATE_KEY'] = Configuration::get('VP_PRIVATE_KEY');
		$helper->fields_value['VP_API_KEY'] = Configuration::get('VP_API_KEY');
		$helper->fields_value['VP_ORDER_PREFIX'] = Configuration::get('VP_ORDER_PREFIX');
		$helper->fields_value['VP_BANNER_IMG'] = Configuration::get('VP_BANNER_IMG');
		$helper->fields_value['VP_SELECT_BANKS'] = Configuration::get('VP_SELECT_BANKS');
		$helper->fields_value['VP_SELECT_WALLETS'] = Configuration::get('VP_SELECT_WALLETS');
		$helper->fields_value['VP_SELECT_CCARDS'] = Configuration::get('VP_SELECT_CCARDS');
		$helper->fields_value['VP_SELECT_CINVOICES'] = Configuration::get('VP_SELECT_CINVOICES');
		$helper->fields_value['VP_SELECT_LASKUYRITYKSELLE'] = Configuration::get('VP_SELECT_LASKUYRITYKSELLE');
		$helper->fields_value['VP_SEND_ITEMS'] = Configuration::get('VP_SEND_ITEMS');
		$helper->fields_value['VP_SEND_CONFIRMATION'] = Configuration::get('VP_SEND_CONFIRMATION');
		$helper->fields_value['VP_CLEAR_CART'] = Configuration::get('VP_CLEAR_CART');
		$helper->fields_value['VP_EMBEDDED'] = Configuration::get('VP_EMBEDDED');

		return $helper->generateForm($fields_form);
	}

	public function displayBanners()
	{
		$output = '<br/><fieldset><legend>'. $this->l('Banner images') .'</legend><div><ul>';

		foreach ($this->banner_images as $filename) {
			$filepath =  __PS_BASE_URI__.'modules/'.$this->name.'/views/img/banners/'.basename($filename);
			$output .= "<li><b>".basename($filename)."</b><br />
			<img src=$filepath>
			</li>";
		}

		$output .= '</ul></div></fieldset>';
		return $output;
	}

	public function hookDisplayHeader($params)
	{
		if(version_compare(_PS_VERSION_, '1.6', '>')) {
			$this->context->controller->addCSS($this->_path.'views/css/vismapay.css', 'all');
		}
	}

	public function hookDisplayPayment($params)
	{
		if (!$this->active)
			return;

		$cart = $params['cart'];

		$currency = new Currency($cart->id_currency);
		$order_currency = $currency->iso_code;

		$this->context->smarty->assign('cart_id', (int)($cart->id));
		$this->context->smarty->assign('process_url', $this->context->link->getModuleLink('vismapay', 'process', array(), Configuration::get('PS_SSL_ENABLED')));

		$banner_file = Configuration::get('VP_BANNER_IMG');

		if(!empty($banner_file))
			$banner_url =  __PS_BASE_URI__.'modules/'.$this->name.'/views/img/banners/'.Configuration::get('VP_BANNER_IMG');
		else
			$banner_url =  '';

		$loader_url = __PS_BASE_URI__.'modules/'.$this->name.'/loader.gif';
		$this->context->smarty->assign('loader_url' , $loader_url);		
		$this->context->smarty->assign('banner_url', $banner_url);

		if(Configuration::get('VP_EMBEDDED') == '1' || Configuration::get('VP_EMBEDDED') == '2')
		{
			$vismapay_payment_methods = array('wallets' => array(), 'banks' => array(), 'creditcards' => array(), 'creditinvoices' => array());
			$amount = $cart ? (int)(round($cart->getOrderTotal() * 100, 0)) : null;

			$this->retrieveDynamicMethods($amount, $vismapay_payment_methods, true, $order_currency);

			if(empty($vismapay_payment_methods['wallets']) && empty($vismapay_payment_methods['banks']) && empty($vismapay_payment_methods['creditcards']) && empty($vismapay_payment_methods['creditinvoices'])) //No payment methods available
				return;

			$this->context->smarty->assign('vismapay_payment_methods', $vismapay_payment_methods);
			$img_url =  __PS_BASE_URI__.'modules/'.$this->name.'/views/img/';
			$this->context->smarty->assign('img_url', $img_url);

			if (version_compare(_PS_VERSION_, '1.6', '<'))
				return $this->display(__FILE__, 'embed_payment_old.tpl');
			else
				return $this->display(__FILE__, 'embed_payment.tpl');
		}
		else
		{
			return $this->display(__FILE__, 'payment.tpl');
		}
	}

	public function hookPaymentOptions($params)
	{
		if (!$this->active) {
			return;
		}
		$cart = $params['cart'];

		$currency = new Currency($cart->id_currency);
		$order_currency = $currency->iso_code;

		$vismapay_payment_methods = array('wallets' => array(), 'banks' => array(), 'creditcards' => array(), 'creditinvoices' => array());
		$img_url = '';
		if(Configuration::get('VP_EMBEDDED') == '1' || Configuration::get('VP_EMBEDDED') == '2')
		{
			$amount = $cart ? (int)(round($cart->getOrderTotal() * 100, 0)) : null;
			$this->retrieveDynamicMethods($amount, $vismapay_payment_methods, true, $order_currency);

			if(empty($vismapay_payment_methods['wallets']) && empty($vismapay_payment_methods['banks']) && empty($vismapay_payment_methods['creditcards']) && empty($vismapay_payment_methods['creditinvoices'])) //No payment methods available
				return;

			$img_url =  __PS_BASE_URI__.'modules/'.$this->name.'/views/img/';
		}

		if(Configuration::get('VP_EMBEDDED') == '2')
		{
			foreach ($vismapay_payment_methods['creditcards'] as $key => $value) {
				$vismaPayPaymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
				$vismaPayPaymentOption->setCallToActionText($value)
					->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
					->setInputs(array(
						'selected' => array(
							'name' => 'selected',
							'type' => 'hidden',
							'value' => 'creditcards',
						)
					));
				$paymentOptions[] = $vismaPayPaymentOption;
			}

			foreach ($vismapay_payment_methods['wallets'] as $key => $value) {
				$vismaPayPaymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
				$vismaPayPaymentOption->setCallToActionText($value)
					->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
					->setInputs(array(
						'selected' => array(
							'name' => 'selected',
							'type' => 'hidden',
							'value' => $key,
						)
					));
				$paymentOptions[] = $vismaPayPaymentOption;
			}

			foreach ($vismapay_payment_methods['banks'] as $key => $value) {
				$vismaPayPaymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
				$vismaPayPaymentOption->setCallToActionText($value)
					->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
					->setInputs(array(
						'selected' => array(
							'name' => 'selected',
							'type' => 'hidden',
							'value' => $key,
						)
					));
				$paymentOptions[] = $vismaPayPaymentOption;
			}

			foreach ($vismapay_payment_methods['creditinvoices'] as $key => $value) {
				$vismaPayPaymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
				$vismaPayPaymentOption->setCallToActionText($value)
					->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
					->setInputs(array(
						'selected' => array(
							'name' => 'selected',
							'type' => 'hidden',
							'value' => $key,
						)
					));
				$paymentOptions[] = $vismaPayPaymentOption;
			}
		}
		else
		{
			$vismaPayPaymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
			$vismaPayPaymentOption->setCallToActionText("Visma Pay")
			->setForm($this->generatePaymentOptions($vismapay_payment_methods, $img_url))
			->setAdditionalInformation($this->context->smarty->fetch('module:vismapay/views/templates/front/paymentinfo.tpl'));

			$paymentOptions = array();
			$paymentOptions[] = $vismaPayPaymentOption;
		}
		return $paymentOptions;
	}

	protected function generatePaymentOptions($vismapay_payment_methods, $img_url)
	{
		$this->context->smarty->assign(array(
			'action' => $this->context->link->getModuleLink($this->name, 'payment', array(), true),
			'vismapay_payment_methods' => $vismapay_payment_methods,
			'img_url' => $img_url
			));
		return $this->context->smarty->fetch('module:vismapay/views/templates/front/paymentoptions.tpl');
	}

	public function hookPaymentReturn($params)
	{
		if (!$this->active)
			return;

		$order = null;
		if (_PS_VERSION_ >= "1.7.0.0") {
			$order = $params['order'];
		} else {
			$order = $params['objOrder'];
		}

		if($order->valid || $order->current_state == Configuration::get('VP_OS_AUTHORIZED') || $order->current_state == Configuration::get('PS_OS_OUTOFSTOCK'))
			$status = 'ok';
		else
			$status = 'failed';

		$this->context->smarty->assign('status', $status);

		$oldCart = new Cart(Order::getCartIdStatic((int)$order->id));
		$id_cart = (int)$oldCart->id;
		$query= Db::getInstance()->getRow('SELECT vismapay_order_number FROM '._DB_PREFIX_."cart WHERE id_cart=$id_cart");
		$order_number = $query['vismapay_order_number'];

		$up_order = new Order($order->id);
		$payments = $up_order->getOrderPaymentCollection();

		foreach ($payments as $payment)
		{
			if ($payment->payment_method == 'Visma Pay'){
				$payment->transaction_id = $order_number;
				$payment->update();
			}

		}
		return $this->display(__FILE__, 'payment_return.tpl');
	}

	public function hookDisplayAdminOrder($params)
	{
		if (!$this->active)
			return;

		$order = new Order((int)$params['id_order']);

		
		$current_state = $order->getCurrentState();
		
		$history = $order->getHistory($order->id_lang, Configuration::get('VP_OS_AUTHORIZED'));
		if(count($history) == 1 && $current_state == Configuration::get('PS_OS_PAYMENT'))
		{
			$message = $this->l('Settlement completed successfully.');
			$output = $this->displayConfirmation($message);
			$this->context->smarty->assign('show_button', false);
			$this->context->smarty->assign('message', $output);
			$logo_url = __PS_BASE_URI__.'modules/'.$this->name.'/logo.gif';
			$this->context->smarty->assign('logo_url' , $logo_url);
			return $this->display(__FILE__, 'settlement.tpl');
		}
		

		if($current_state !== Configuration::get('VP_OS_AUTHORIZED'))
			return;

		$output = '';
		$show_button = true;
		
		if (Tools::isSubmit('vismapay_settlement') )
		{
			$id_cart = (isset($params['cart']) && _PS_VERSION_ <= "1.7.7.0") ? (int)$params['cart']->id : (int)$order->id_cart;
			$query= Db::getInstance()->getRow('SELECT vismapay_order_number FROM '._DB_PREFIX_."cart WHERE id_cart=$id_cart");

			$order_number = $query['vismapay_order_number'];
			$privatekey = Configuration::get('VP_PRIVATE_KEY');
			$apikey = Configuration::get('VP_API_KEY');
			$error = '';

			include_once(dirname(__FILE__).'/vismapay-php-lib/visma_pay_loader.php');

			$payment = new Visma\VismaPay($apikey, $privatekey);
			
			try
			{
				$settlement = $payment->settlePayment($order_number);

				$return_code = $settlement->result;

				switch ($return_code)
				{
					case 0:
						$message = $this->l('Settlement completed successfully.');

						$order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
						$msg = new Message();
						$msg->message = $message;
						$msg->id_order = (int)$order->id;
						$msg->private = 1;
						$msg->add();
						$show_button = false;

						// Workaround for prestashop issues
						header("Refresh:0");
						exit();
						break;
					case 1:
						$message = $this->l('Request failed. Validation failed.');
						break;
					case 2:
						$message = $this->l('Payment cannot be settled. Either the payment has already been settled or the payment gateway refused to settle payment for given transaction.');
						break;
					case 3:
						$message = $this->l('Payment cannot be settled. Transaction for given order number was not found.');
						break;
					default:
						$message = $this->l('Unexpected error during the settlement.');
						break;
				}
			}
			catch (Visma\VismaPayException $e) 
			{
				$message = $e->getMessage();
			}

			if(!$show_button)
				$output = $this->displayConfirmation($message);
			else
				$output = $this->displayError($message);
		}
		
		$this->context->smarty->assign('message', $output);
		$logo_url = __PS_BASE_URI__.'modules/'.$this->name.'/logo.gif';
		$this->context->smarty->assign('logo_url' , $logo_url);
		$this->context->smarty->assign('show_button', $show_button);

		return $this->display(__FILE__, 'settlement.tpl');
	}

	public function generate_order_number($cart)
	{
		$prefix = Configuration::get('VP_ORDER_PREFIX');
		if(!empty($prefix))
			$order_number = Configuration::get('VP_ORDER_PREFIX').'_'.date('YmdHis').'_'.$cart->id;
		else
			$order_number = date('YmdHis').'_'.$cart->id;

		return $order_number;
	}

	private function vismapay_save_img($key, $img_url, $img_timestamp)
	{
		$img = dirname(__FILE__).'/views/img/'.$key.'.png';
		$timestamp = file_exists($img) ? filemtime($img) : 0;
		if(!file_exists($img) || $img_timestamp > $timestamp)
		{
			if($file = @fopen($img_url, 'r'))
			{
				if(class_exists('finfo'))
				{
					$finfo = new finfo(FILEINFO_MIME_TYPE);
					if(strpos($finfo->buffer($file_content = stream_get_contents($file)), 'image') !== false)
					{
						@file_put_contents($img, $file_content);
						touch($img, $img_timestamp);
					}
				}
				else
				{
					@file_put_contents($img, $file);
					touch($img, $img_timestamp);
				}
				@fclose($file);
			}
		}
		return;
	}

	private function retrieveDynamicMethods($amount, &$vismapay_payment_methods, $display = false, $currency = 'EUR')
	{
		$privatekey = Configuration::get('VP_PRIVATE_KEY');
		$apikey = Configuration::get('VP_API_KEY');
		include_once(dirname(__FILE__).'/vismapay-php-lib/visma_pay_loader.php');

		$payment_methods = new Visma\VismaPay($apikey, $privatekey);

		try
		{
			$response = $payment_methods->getMerchantPaymentMethods($currency);

			if($response->result == 0 && count($response->payment_methods) > 0)
			{
				foreach ($response->payment_methods as $method)
				{
					$key = $method->selected_value;
					if($method->group == 'creditcards')
						$key = strtolower($method->name);

					$this->vismapay_save_img($key, $method->img, $method->img_timestamp);

					if($method->group == 'creditcards'  && Configuration::get('VP_SELECT_CCARDS') != '')
					{
						$vismapay_payment_methods['creditcards'][$key] = $method->name;
					}
					else if($method->group == 'wallets' && Configuration::get('VP_SELECT_WALLETS') != '')
					{
						$vismapay_payment_methods['wallets'][$key] = $method->name;
					}
					else if($method->group == 'banks' && Configuration::get('VP_SELECT_BANKS') != '')
					{
						$vismapay_payment_methods['banks'][$key] = $method->name;
					}
					else if($method->group == 'creditinvoices')
					{
						if(Configuration::get('VP_SELECT_LASKUYRITYKSELLE') != '' && $key == 'laskuyritykselle')
							$vismapay_payment_methods['creditinvoices'][$key] = $method->name;
						else if($key != 'laskuyritykselle' && $amount >= $method->min_amount && $amount <= $method->max_amount && Configuration::get('VP_SELECT_CINVOICES') != '') {
							$vismapay_payment_methods['creditinvoices'][$key] = $method->name;
						}
					}
				}
			}
		}
		catch (Visma\VismaPayException $e) 
		{
			$message = $e->getMessage();
			Logger::addLog('Visma Pay getMerchantPaymentMethods exception: ' . $message . ', Check your credentials in the module settings.', 3, null, null, null, true);
		}
		return true;
	}
}

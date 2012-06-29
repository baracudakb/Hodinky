<?php
/*
* 2007-2010 PrestaShop 
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author Prestashop SA <contact@prestashop.com>
*  @copyright  2007-2010 Prestashop SA
*  @version  Release: $Revision: 1.4 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_CAN_LOAD_FILES_'))
	exit;

class CashOnDeliveryWithFee extends PaymentModule
{	
	public function __construct()
	{
		$this->name = 'cashondeliverywithfee';
		$this->tab = 'payments_gateways';
		$this->version = '1.0';
		
		$this->currencies = false;
                $this->isFee = TRUE; //fee yes

		parent::__construct();

		$this->page = basename(__FILE__, '.php');
		$this->displayName = $this->l('Cash on delivery with fee (COD)');
		$this->description = $this->l('Accept cash on delivery payments with extra fee');
	}

	public function install()
	{
		if (!parent::install() OR !$this->registerHook('payment') OR !$this->registerHook('paymentReturn'))
			return false;
		return true;
	}
	
	public function getContent()
	{
		$this->_html = '<h2>Dobírka</h2>';
		if (isset($_POST['submitCOD']))
		{
			if (empty($_POST['fee']))
				$_POST['fee'] = '0';
			if (empty($_POST['feetype']))
				$_POST['feetype'] = '0';
			if (empty($_POST['feemin']))
				$_POST['feemin'] = '0';
			if (!sizeof($this->_postErrors))
			{
				Configuration::updateValue('COD_FEE', floatval( $_POST['fee']) );
				Configuration::updateValue('COD_FEE_TYPE', floatval( $_POST['feetype']) );
				Configuration::updateValue('COD_FEE_MIN', floatval( $_POST['feemin']) );
				$this->displayConf();
			}
			else
				$this->displayErrors();
		}

		$this->displayCOD();
		$this->displayFormSettings();
		return $this->_html;
	}
	
	public function displayCOD()
	{
		$this->_html .= '
		<img src="../modules/cashondeliverywithfee/logo.gif" style="float:left; margin-right:15px;" />
		<b>'.$this->l('This module allows you to accept payments by Cash on delivery and charge a extra fee.').'</b><br /><br /><br />';
	}
	
	
	public function displayFormSettings()
	{
		$conf = Configuration::getMultiple(array('COD_FEE','COD_FEE_TYPE','COD_FEE_MIN'));
		$fee = array_key_exists('fee', $_POST) ? $_POST['fee'] : (array_key_exists('COD_FEE', $conf) ? $conf['COD_FEE'] : '');
		$feetype = array_key_exists('feetype', $_POST) ? $_POST['feetype'] : (array_key_exists('COD_FEE_TYPE', $conf) ? $conf['COD_FEE_TYPE'] : '');
		$feemin = array_key_exists('feemin', $_POST) ? $_POST['feemin'] : (array_key_exists('COD_FEE_MIN', $conf) ? $conf['COD_FEE_MIN'] : '');

		$this->_html .= '
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
		<fieldset>
			<legend><img src="../img/admin/contact.gif" />'.$this->l('Settings').'</legend>
			<label>'.$this->l('Fee').'</label>
			<div class="margin-form"><input type="text" size="33" name="fee" value="'.htmlentities($fee, ENT_COMPAT, 'UTF-8').'" /></div>
			<br />
			<label>'.$this->l('Type').'</label>
			<div class="margin-form">
			<input type="radio" name="feetype" value="0" '.(!$feetype ? 'checked="checked"' : '').' /> '.$this->l('Amount').'
			<input type="radio" name="feetype" value="1" '.($feetype ? 'checked="checked"' : '').' /> '.$this->l('%').'
			</div>
			<label>'.$this->l('Minimum Fee').'</label>
			<div class="margin-form"><input type="text" size="33" name="feemin" value="'.htmlentities($feemin, ENT_COMPAT, 'UTF-8').'" /></div>
			<br />
			<br /><center><input type="submit" name="submitCOD" value="'.$this->l('Update settings').'" class="button" /></center>
		</fieldset>
		</form><br /><br />';
	}
	


	public function displayConf()
	{
		$this->_html .= '
		<div class="conf confirm">
			<img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />
			'.$this->l('Settings updated').'
		</div>';
	}
	
	public function displayErrors()
	{
		$nbErrors = sizeof($this->_postErrors);
		$this->_html .= '
		<div class="alert error">
			<h3>'.($nbErrors > 1 ? $this->l('There are') : $this->l('There is')).' '.$nbErrors.' '.($nbErrors > 1 ? $this->l('errors') : $this->l('error')).'</h3>
			<ol>';
		foreach ($this->_postErrors AS $error)
			$this->_html .= '<li>'.$error.'</li>';
		$this->_html .= '
			</ol>
		</div>';
	}
	public function hookPayment($params)
	{
		if (!$this->active)
			return ;

		global $smarty;

		// Check if cart has product download
		foreach ($params['cart']->getProducts() AS $product)
		{
			$pd = ProductDownload::getIdFromIdProduct((int)($product['id_product']));
			if ($pd AND Validate::isUnsignedInt($pd))
				return false;
		}

                //fee check
                if($params['cart']->getOrderShippingCost() == 0)
                    $this->isFee = FALSE;


		$smarty->assign(array(
                        'isFee' =>  $this->isFee,
			'this_path' => $this->_path,
			'fee' => number_format($this->getCost($params), 2, '.', ''),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
		));
		return $this->display(__FILE__, 'payment.tpl');
	}
	
	//Return the fee cost
	function getCost($params)
	{
	if(Configuration::get('COD_FEE_TYPE')==0)
	{
		return floatval(Configuration::get('COD_FEE'));
	}
	else
	{
		$minimalfee = floatval(Configuration::get('COD_FEE_MIN'));
		$cartvalue = floatval($params['cart']->getOrderTotal(true, 3));
		$percent = floatval(Configuration::get('COD_FEE'));
		$percent = $percent / 100;
		$fee = $cartvalue * $percent;
		
		if($fee<$minimalfee)
		{
			$fee=$minimalfee;
		}
		return floatval($fee);
	}
	}
	
	//Return the fee cost
	function getCostValidated($cart)
	{
	if(Configuration::get('COD_FEE_TYPE')==0)
	{
		return floatval(Configuration::get('COD_FEE'));
	}
	else
	{
		$minimalfee = floatval(Configuration::get('COD_FEE_MIN'));
		$cartvalue = floatval($cart->getOrderTotal(true, 3));
		$percent = floatval(Configuration::get('COD_FEE'));
		$percent = $percent / 100;
		$fee = $cartvalue * $percent;
		if($fee<$minimalfee)
		{
			$fee=$minimalfee;
		}
		return floatval($fee);
	}
	}
	
	public function hookPaymentReturn($params)
	{
		if (!$this->active)
			return ;

		return $this->display(__FILE__, 'confirmation.tpl');
	}
	/**
	* Validate an order in database AND ADD EXTRA COST
	* Function called from a payment module
	*
	* @param integer $id_cart Value
	* @param integer $id_order_state Value
	* @param float $amountPaid Amount really paid by customer (in the default currency)
	* @param string $paymentMethod Payment method (eg. 'Credit cart')
	* @param string $message Message to attach to order
	*/
	public function validateOrderCOD($id_cart, $id_order_state, $amountPaid, $paymentMethod = 'Unknown', $message = NULL, $extraVars = array(), $currency_special = NULL, $dont_touch_amount = false, $secure_key = false)
	{
		global $cart;

		$cart = new Cart((int)($id_cart));

                $CODfee = $this->getCostValidated($cart);

                if ($cart->getOrderShippingCost() == 0)
                    $CODfee = 0;

                $jj = TRUE;
		
		// Does order already exists ?
		if (Validate::isLoadedObject($cart) AND $cart->OrderExists() === 0)
		{
			if ($secure_key !== false AND $secure_key != $cart->secure_key)
				die(Tools::displayError());

			// Copying data from cart
			$order = new Order();
			$order->id_carrier = (int)($cart->id_carrier);
			$order->id_customer = (int)($cart->id_customer);
			$order->id_address_invoice = (int)($cart->id_address_invoice);
			$order->id_address_delivery = (int)($cart->id_address_delivery);
			$vat_address = new Address((int)($order->id_address_delivery));
			$order->id_currency = ($currency_special ? (int)($currency_special) : (int)($cart->id_currency));
			$order->id_lang = (int)($cart->id_lang);
			$order->id_cart = (int)($cart->id);
			$customer = new Customer((int)($order->id_customer));
			$order->secure_key = ($secure_key ? pSQL($secure_key) : pSQL($customer->secure_key));
			$order->payment = Tools::substr($paymentMethod, 0, 32);
			if (isset($this->name))
				$order->module = $this->name;
			$order->recyclable = $cart->recyclable;
			$order->gift = (int)($cart->gift);
			$order->gift_message = $cart->gift_message;
			$currency = new Currency($order->id_currency);
			$order->conversion_rate = $currency->conversion_rate;
			$amountPaid = !$dont_touch_amount ? Tools::ps_round((float)($amountPaid), 2) : $amountPaid;                       
                        $order->total_paid_real = floatval(number_format($amountPaid+$CODfee, 2, '.', ''));
			$order->total_products = (float)($cart->getOrderTotal(false, Cart::ONLY_PRODUCTS));
			$order->total_products_wt = (float)($cart->getOrderTotal(true, Cart::ONLY_PRODUCTS));
			$order->total_discounts = (float)(abs($cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS)));
			$order->total_shipping = (float)($cart->getOrderShippingCost()+$CODfee);
			$order->carrier_tax_rate = (float)Tax::getCarrierTaxRate($cart->id_carrier, (int)$cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
			$order->total_wrapping = (float)(abs($cart->getOrderTotal(true, Cart::ONLY_WRAPPING)));
			$order->total_paid = (float)(Tools::ps_round((float)($cart->getOrderTotal(true, Cart::BOTH)+$CODfee), 2));
                        $order->invoice_date = '0000-00-00 00:00:00';
			$order->delivery_date = '0000-00-00 00:00:00';
			
                        // Amount paid by customer is not the right one -> Status = payment error
                        
                        //if ($order->total_paid != $order->total_paid_real)
				//$id_order_state = _PS_OS_ERROR_;
			// Creating order
			if ($cart->OrderExists() === 0)
				$result = $order->add();
			else
			{
				$errorMessage = Tools::displayError('An order has already been placed using this cart');
				Log::addLog($errorMessage, 4, '0000001', 'Cart', intval($order->id_cart));
				die($errorMessage);
			}

			// Next !
			if ($result AND isset($order->id))
			{
				if (!$secure_key)
					$message .= $this->l('Warning : the secure key is empty, check your payment account before validation');
				// Optional message to attach to this order
				if (isset($message) AND !empty($message))
				{
					$msg = new Message();
					$message = strip_tags($message, '<br>');
					if (!Validate::isCleanHtml($message))
						$message = $this->l('Payment message is not valid, please check your module!');
					$msg->message = $message;
					$msg->id_order = (int)($order->id);
					$msg->private = 1;
					$msg->add();
				}

				// Insert products from cart into order_detail table
				$products = $cart->getProducts();
				$productsList = '';
				$db = Db::getInstance();
				$query = 'INSERT INTO `'._DB_PREFIX_.'order_detail`
					(`id_order`, `product_id`, `product_attribute_id`, `product_name`, `product_quantity`, `product_quantity_in_stock`, `product_price`, `reduction_percent`, `reduction_amount`, `group_reduction`, `product_quantity_discount`, `product_ean13`, `product_upc`, `product_reference`, `product_supplier_reference`, `product_weight`, `tax_name`, `tax_rate`, `ecotax`, `ecotax_tax_rate`, `discount_quantity_applied`, `download_deadline`, `download_hash`)
				VALUES ';

				$customizedDatas = Product::getAllCustomizedDatas((int)($order->id_cart));
				Product::addCustomizationPrice($products, $customizedDatas);
				$outOfStock = false;
				foreach ($products AS $key => $product)
				{
					$productQuantity = (int)(Product::getQuantity((int)($product['id_product']), ($product['id_product_attribute'] ? (int)($product['id_product_attribute']) : NULL)));
					$quantityInStock = ($productQuantity - (int)($product['cart_quantity']) < 0) ? $productQuantity : (int)($product['cart_quantity']);
					if ($id_order_state != _PS_OS_CANCELED_ AND $id_order_state != _PS_OS_ERROR_)
					{
						if (Product::updateQuantity($product, (int)$order->id))
							$product['stock_quantity'] -= $product['cart_quantity'];

						if ($product['stock_quantity'] < 0)
							$outOfStock = true;

						Hook::updateQuantity($product, $order);
						Product::updateDefaultAttribute($product['id_product']);
					}
					$price = Product::getPriceStatic((int)($product['id_product']), false, ($product['id_product_attribute'] ? (int)($product['id_product_attribute']) : NULL), 6, NULL, false, true, $product['cart_quantity'], false, (int)($order->id_customer), (int)($order->id_cart), (int)($order->{Configuration::get('PS_TAX_ADDRESS_TYPE')}));
					$price_wt = Product::getPriceStatic((int)($product['id_product']), true, ($product['id_product_attribute'] ? (int)($product['id_product_attribute']) : NULL), 2, NULL, false, true, $product['cart_quantity'], false, (int)($order->id_customer), (int)($order->id_cart), (int)($order->{Configuration::get('PS_TAX_ADDRESS_TYPE')}));
					// Add some informations for virtual products
					$deadline = '0000-00-00 00:00:00';
					$download_hash = NULL;
					if ($id_product_download = ProductDownload::getIdFromIdProduct((int)($product['id_product'])))
					{
						$productDownload = new ProductDownload((int)($id_product_download));
						$deadline = $productDownload->getDeadLine();
						$download_hash = $productDownload->getHash();
					}

					// Exclude VAT
					if (Tax::excludeTaxeOption())
					{
						$product['tax'] = 0;
						$product['rate'] = 0;
						$tax_rate = 0;
					}
					else
						$tax_rate = Tax::getProductTaxRate((int)($product['id_product']), $cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')});

                    $ecotaxTaxRate = 0;
                    if (!empty($product['ecotax']))
                        $ecotaxTaxRate = Tax::getProductEcotaxRate($order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});

					$quantityDiscount = SpecificPrice::getQuantityDiscount((int)$product['id_product'], Shop::getCurrentShop(), (int)$cart->id_currency, (int)$vat_address->id_country, (int)$customer->id_default_group, (int)$product['cart_quantity']);
					$unitPrice = Product::getPriceStatic((int)$product['id_product'], true, ($product['id_product_attribute'] ? intval($product['id_product_attribute']) : NULL), 2, NULL, false, true, 1, false, (int)$order->id_customer, NULL, (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
					$quantityDiscountValue = $quantityDiscount ? ((Product::getTaxCalculationMethod((int)$order->id_customer) == PS_TAX_EXC ? Tools::ps_round($unitPrice, 2) : $unitPrice) - $quantityDiscount['price'] * (1 + $tax_rate / 100)) : 0.00;
					$query .= '('.(int)($order->id).',
						'.(int)($product['id_product']).',
						'.(isset($product['id_product_attribute']) ? (int)($product['id_product_attribute']) : 'NULL').',
						\''.pSQL($product['name'].((isset($product['attributes']) AND $product['attributes'] != NULL) ? ' - '.$product['attributes'] : '')).'\',
						'.(int)($product['cart_quantity']).',
						'.$quantityInStock.',
						'.(float)(Product::getPriceStatic((int)($product['id_product']), false, ($product['id_product_attribute'] ? (int)($product['id_product_attribute']) : NULL), (Product::getTaxCalculationMethod((int)($order->id_customer)) == PS_TAX_EXC ? 2 : 6), NULL, false, false, $product['cart_quantity'], false, (int)($order->id_customer), (int)($order->id_cart), (int)($order->{Configuration::get('PS_TAX_ADDRESS_TYPE')}), $specificPrice, FALSE)).',
						'.(float)(($specificPrice AND $specificPrice['reduction_type'] == 'percentage') ? $specificPrice['reduction'] * 100 : 0.00).',
						'.(float)(($specificPrice AND $specificPrice['reduction_type'] == 'amount') ? (!$specificPrice['id_currency'] ? Tools::convertPrice($specificPrice['reduction'], $order->id_currency) : $specificPrice['reduction']) : 0.00).',
						'.(float)(Group::getReduction((int)($order->id_customer))).',
						'.$quantityDiscountValue.',
						'.(empty($product['ean13']) ? 'NULL' : '\''.pSQL($product['ean13']).'\'').',
						'.(empty($product['upc']) ? 'NULL' : '\''.pSQL($product['upc']).'\'').',
						'.(empty($product['reference']) ? 'NULL' : '\''.pSQL($product['reference']).'\'').',
						'.(empty($product['supplier_reference']) ? 'NULL' : '\''.pSQL($product['supplier_reference']).'\'').',
						'.(float)($product['id_product_attribute'] ? $product['weight_attribute'] : $product['weight']).',
						\''.(empty($tax_rate) ? '' : pSQL($product['tax'])).'\',
						'.(float)($tax_rate).',
						'.(float)Tools::convertPrice(floatval($product['ecotax']), intval($order->id_currency)).',
						'.(float)$ecotaxTaxRate.',
						'.(($specificPrice AND $specificPrice['from_quantity'] > 1) ? 1 : 0).',
						\''.pSQL($deadline).'\',
						\''.pSQL($download_hash).'\'),';

					$customizationQuantity = 0;
					if (isset($customizedDatas[$product['id_product']][$product['id_product_attribute']]))
					{
						$customizationText = '';
						foreach ($customizedDatas[$product['id_product']][$product['id_product_attribute']] AS $customization)
							if (isset($customization['datas'][_CUSTOMIZE_TEXTFIELD_]))
								foreach ($customization['datas'][_CUSTOMIZE_TEXTFIELD_] AS $text)
									$customizationText .= $text['name'].$this->l(':').' '.$text['value'].', ';
						$customizationText = rtrim($customizationText, ', ');

						$customizationQuantity = (int)($product['customizationQuantityTotal']);
						$productsList .=
						'<tr style="background-color: '.($key % 2 ? '#DDE2E6' : '#EBECEE').';">
							<td style="padding: 0.6em 0.4em;">'.$product['reference'].'</td>
							<td style="padding: 0.6em 0.4em;"><strong>'.$product['name'].(isset($product['attributes_small']) ? ' '.$product['attributes_small'] : '').' - '.$this->l('Customized').(!empty($customizationText) ? ' - '.$customizationText : '').'</strong></td>
							<td style="padding: 0.6em 0.4em; text-align: right;">'.Tools::displayPrice(Product::getTaxCalculationMethod() == PS_TAX_EXC ? $price : $price_wt, $currency, false, false).'</td>
							<td style="padding: 0.6em 0.4em; text-align: center;">'.$customizationQuantity.'</td>
							<td style="padding: 0.6em 0.4em; text-align: right;">'.Tools::displayPrice($customizationQuantity * (Product::getTaxCalculationMethod() == PS_TAX_EXC ? $price : $price_wt), $currency, false, false).'</td>
						</tr>';
					}

					if (!$customizationQuantity OR (int)$product['cart_quantity'] > $customizationQuantity)
						$productsList .=
						'<tr style="background-color: '.($key % 2 ? '#DDE2E6' : '#EBECEE').';">
							<td style="padding: 0.6em 0.4em;">'.$product['reference'].'</td>
							<td style="padding: 0.6em 0.4em;"><strong>'.$product['name'].(isset($product['attributes_small']) ? ' '.$product['attributes_small'] : '').'</strong></td>
							<td style="padding: 0.6em 0.4em; text-align: right;">'.Tools::displayPrice(Product::getTaxCalculationMethod() == PS_TAX_EXC ? $price : $price_wt, $currency, false, false).'</td>
							<td style="padding: 0.6em 0.4em; text-align: center;">'.((int)($product['cart_quantity']) - $customizationQuantity).'</td>
							<td style="padding: 0.6em 0.4em; text-align: right;">'.Tools::displayPrice(((int)($product['cart_quantity']) - $customizationQuantity) * (Product::getTaxCalculationMethod() == PS_TAX_EXC ? $price : $price_wt), $currency, false, false).'</td>
						</tr>';
				} // end foreach ($products)
				$query = rtrim($query, ',');
				$result = $db->Execute($query);

				// Insert discounts from cart into order_discount table
				$discounts = $cart->getDiscounts();
				$discountsList = '';
				$total_discount_value = 0;
				$shrunk = false;
				foreach ($discounts AS $discount)
				{
					$objDiscount = new Discount((int)$discount['id_discount'], $order->id_lang);
					$value = $objDiscount->getValue(sizeof($discounts), $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS), $order->total_shipping, $cart->id);
					if ($objDiscount->id_discount_type == 2 AND in_array($objDiscount->behavior_not_exhausted, array(1,2)))
						$shrunk = true;

					if ($shrunk AND ($total_discount_value + $value) > ($order->total_products + $order->total_shipping + $order->total_wrapping))
					{
						$amount_to_add = ($order->total_products + $order->total_shipping + $order->total_wrapping) - $total_discount_value;
						if ($objDiscount->id_discount_type == 2 AND $objDiscount->behavior_not_exhausted == 2)
						{
							$voucher = new Discount();
							foreach ($objDiscount AS $key => $discountValue)
								$voucher->$key = $discountValue;
							$voucher->name = 'VSRK'.(int)$order->id_customer.'O'.(int)$order->id;
							$voucher->value = (float)$value - $amount_to_add;
							$voucher->add();
							$params['{voucher_amount}'] = Tools::displayPrice($voucher->value, $currency, false, false);
							$params['{voucher_num}'] = $voucher->name;
							@Mail::Send((int)$order->id_lang, 'voucher', html_entity_decode(Mail::l('New voucher regarding your order #').$order->id, ENT_NOQUOTES, 'UTF-8'), $params, $customer->email, $customer->firstname.' '.$customer->lastname);
						}
					}
					else
						$amount_to_add = $value;
					$order->addDiscount($objDiscount->id, $objDiscount->name, $amount_to_add);
					$total_discount_value += $amount_to_add;
					if ($id_order_state != _PS_OS_ERROR_ AND $id_order_state != _PS_OS_CANCELED_)
						$objDiscount->quantity = $objDiscount->quantity - 1;
					$objDiscount->update();

					$discountsList .=
					'<tr style="background-color:#EBECEE;">
							<td colspan="4" style="padding: 0.6em 0.4em; text-align: right;">'.$this->l('Voucher code:').' '.$objDiscount->name.'</td>
							<td style="padding: 0.6em 0.4em; text-align: right;">'.($value != 0.00 ? '-' : '').Tools::displayPrice($value, $currency, false, false).'</td>
					</tr>';
				}

				// Specify order id for message
				$oldMessage = Message::getMessageByCartId((int)($cart->id));
				if ($oldMessage)
				{
					$message = new Message((int)$oldMessage['id_message']);
					$message->id_order = (int)$order->id;
					$message->update();
				}

				// Hook new order
				$orderStatus = new OrderState((int)$id_order_state, (int)$order->id_lang);
				if (Validate::isLoadedObject($orderStatus))
				{
					Hook::newOrder($cart, $order, $customer, $currency, $orderStatus);
					foreach ($cart->getProducts() AS $product)
						if ($orderStatus->logable)
							ProductSale::addProductSale((int)$product['id_product'], (int)$product['cart_quantity']);
				}

				if (isset($outOfStock) AND $outOfStock)
				{
					$history = new OrderHistory();
					$history->id_order = (int)$order->id;
					$history->changeIdOrderState(_PS_OS_OUTOFSTOCK_, (int)$order->id);
					$history->addWithemail();
				}

				// Set order state in order history ONLY even if the "out of stock" status has not been yet reached
				// So you migth have two order states
				$new_history = new OrderHistory();
				$new_history->id_order = (int)$order->id;
				$new_history->changeIdOrderState((int)$id_order_state, (int)$order->id);
				$new_history->addWithemail(true, $extraVars);

				// Order is reloaded because the status just changed
				$order = new Order($order->id);

				// Send an e-mail to customer
				if ($id_order_state != _PS_OS_ERROR_ AND $id_order_state != _PS_OS_CANCELED_ AND $customer->id)
				{
					$invoice = new Address((int)($order->id_address_invoice));
					$delivery = new Address((int)($order->id_address_delivery));
					$carrier = new Carrier((int)($order->id_carrier), $order->id_lang);
					$delivery_state = $delivery->id_state ? new State((int)($delivery->id_state)) : false;
					$invoice_state = $invoice->id_state ? new State((int)($invoice->id_state)) : false;

					$data = array(
					'{firstname}' => $customer->firstname,
					'{lastname}' => $customer->lastname,
					'{email}' => $customer->email,
					'{delivery_company}' => $delivery->company,
					'{delivery_firstname}' => $delivery->firstname,
					'{delivery_lastname}' => $delivery->lastname,
					'{delivery_address1}' => $delivery->address1,
					'{delivery_address2}' => $delivery->address2,
					'{delivery_city}' => $delivery->city,
					'{delivery_postal_code}' => $delivery->postcode,
					'{delivery_country}' => $delivery->country,
					'{delivery_state}' => $delivery->id_state ? $delivery_state->name : '',
					'{delivery_phone}' => ($delivery->phone) ? $delivery->phone : $delivery->phone_mobile,
					'{delivery_other}' => $delivery->other,
					'{invoice_company}' => $invoice->company,
					'{invoice_vat_number}' => $invoice->vat_number,
					'{invoice_firstname}' => $invoice->firstname,
					'{invoice_lastname}' => $invoice->lastname,
					'{invoice_address2}' => $invoice->address2,
					'{invoice_address1}' => $invoice->address1,
					'{invoice_city}' => $invoice->city,
					'{invoice_postal_code}' => $invoice->postcode,
					'{invoice_country}' => $invoice->country,
					'{invoice_state}' => $invoice->id_state ? $invoice_state->name : '',
					'{invoice_phone}' => ($invoice->phone) ? $invoice->phone : $invoice->phone_mobile,
					'{invoice_other}' => $invoice->other,
					'{order_name}' => sprintf("#%06d", (int)($order->id)),
					'{date}' => Tools::displayDate(date('Y-m-d H:i:s'), (int)($order->id_lang), 1),
					'{carrier}' => $carrier->name,
					'{payment}' => $order->payment,
					'{products}' => $productsList,
					'{discounts}' => $discountsList,
					'{total_paid}' => Tools::displayPrice($order->total_paid, $currency, false, false),
					'{total_products}' => Tools::displayPrice($order->total_paid - $order->total_shipping - $order->total_wrapping + $order->total_discounts, $currency, false, false),
					'{total_discounts}' => Tools::displayPrice($order->total_discounts, $currency, false, false),
					'{total_shipping}' => Tools::displayPrice($order->total_shipping, $currency, false, false),
					'{total_wrapping}' => Tools::displayPrice($order->total_wrapping, $currency, false, false));

					if (is_array($extraVars))
						$data = array_merge($data, $extraVars);

					// Join PDF invoice
					if ((int)(Configuration::get('PS_INVOICE')) AND Validate::isLoadedObject($orderStatus) AND $orderStatus->invoice AND $order->invoice_number)
					{
						$fileAttachment['content'] = PDF::invoice($order, 'S');
						$fileAttachment['name'] = Configuration::get('PS_INVOICE_PREFIX', (int)($order->id_lang)).sprintf('%06d', $order->invoice_number).'.pdf';
						$fileAttachment['mime'] = 'application/pdf';
					}
					else
						$fileAttachment = NULL;

					if (Validate::isEmail($customer->email))
						Mail::Send((int)($order->id_lang), 'order_conf', Mail::l('Order confirmation'), $data, $customer->email, $customer->firstname.' '.$customer->lastname, NULL, NULL, $fileAttachment);
				}
				$this->currentOrder = (int)($order->id);
				
					/* Heureka - ověřeno zákazníky */
				require_once (dirname(__FILE__).'/../config/HeurekaOvereno.php');
				try {
					$overeno = new HeurekaOvereno(_ID_HEUREKA_);
					$overeno->setEmail($customer->email);
				foreach ($products AS $product) {
				 $overeno->addProduct($product['name']);
				}
					$overeno->send();
				} catch (Exception $e) {
				// nastavení pro zalogování chyby
				print $e->getMessage();
				}
				
				return true;
			}
			else
			{
				$errorMessage = Tools::displayError('Order creation failed');
				Log::addLog($errorMessage, 4, '0000002', 'Cart', intval($order->id_cart));
				die($errorMessage);
			}
		}
		else
		{
			$errorMessage = Tools::displayError('Cart can\'t be loaded or an order has already been placed using this cart');
			Log::addLog($errorMessage, 4, '0000001', 'Cart', intval($cart->id));
			die($errorMessage);
		}
	}
	
}

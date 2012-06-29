<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
include(dirname(__FILE__).'/cashondeliverywithfee.php');

$confirm = Tools::getValue('confirm');

 	$cashOnDelivery = new CashOnDeliveryWithFee();

/* Validate order */
if ($confirm)
{
	$customer = new Customer((int)($cart->id_customer));
	
	$total = floatval(number_format($cart->getOrderTotal(true, 3), 2, '.', ''));
	$cashOnDelivery->validateOrderCOD((int)($cart->id), _PS_OS_PREPARATION_, $total, $cashOnDelivery->displayName, NULL, array(), NULL, false,$customer->secure_key);
	$order = new Order(intval($cashOnDelivery->currentOrder));
	
	Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$customer->secure_key.'&id_cart='.(int)($cart->id).'&id_module='.(int)($cashOnDelivery->id).'&id_order='.(int)($cashOnDelivery->currentOrder));
	
}
else
{
/* or ask for confirmation */ 
	if($cart->getOrderShippingCost() == 0)
            $CODfee = 0;
        else
            $CODfee = $cashOnDelivery->getCostValidated($cart);

        $cartcost = $cart->getOrderTotal(true, 3);

        $total = $CODfee + $cartcost;

	$smarty->assign(array(
		'total' => number_format(floatval( $total ), 2, '.', ''),
		'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/cashondeliverywithfee/'
	));

    $smarty->assign('this_path', __PS_BASE_URI__.'modules/cashondeliverywithfee/');
	$template = 'validation.tpl';
	echo Module::display('cashondeliverywithfee', $template);	
//    echo Module::display(__FILE__, 'validation.tpl');

}

include(dirname(__FILE__).'/../../footer.php');
?>
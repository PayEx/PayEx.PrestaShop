<?php
/**
* AAIT
*
*  @author    aait.se
*  @package    PayEx
*  @copyright 2007-2015 AAIT
*  @license   http://shop.aait.se/license.txt  EULA
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/payex.php';

$module = new Payex();
$cookie = Context::getContext()->cookie;
$cart = new Cart((int)$cookie->id_cart);
if (!Validate::isLoadedObject($cart)) {
    Tools::redirect('index.php?controller=order&step=1');
}

$customer = new Customer((int)$cart->id_customer);
if (!Validate::isLoadedObject($customer)) {
    Tools::redirect('index.php?controller=order&step=1');
}

// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
$authorized = false;
foreach (Module::getPaymentModules() as $item) {
    if ($item['name'] == $module->name) {
        $authorized = true;
        break;
    }
}

if (!$authorized) {
    die($module->l('This payment method is not available.', 'validation'));
}

// Check Order Ref
$orderRef = Tools::getValue('orderRef');
if (empty($orderRef)) {
    return;
}

// Check Cart Id
$cart_id = Tools::getValue('id_cart');

// Call PxOrder.Complete
$params = array(
    'accountNumber' => '',
    'orderRef' => $orderRef
);
$result = $module->getPx()->Complete($params);
if ($result['errorCodeSimple'] !== 'OK') {
    die(Tools::displayError($module->getVerboseErrorMessage($result)));
}

if (!isset($result['transactionNumber'])) {
    $result['transactionNumber'] = '';
}

// Check Transaction
if (count($module->getTransaction($result['transactionNumber'])) > 0) {
    die(Tools::displayError($module->l('This transaction has already been registered in store.')));
}

/* Transaction statuses:
0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
switch ((int)$result['transactionStatus']) {
    case 0:
    case 6:
        $amount = $cart->getOrderTotal(true, Cart::BOTH);
        $module->validateOrder($cart_id, Configuration::get('PS_OS_PAYEX_CAPTURED'), $amount, $module->displayName, null, array(), null, true, $customer->secure_key);
        $order = new Order($module->currentOrder);
        if (!Validate::isLoadedObject($order)) {
            die(Tools::displayError($module->l('Unable to place order.')));
        }

        // Save Transaction
        $module->addTransaction($order->id, $result['transactionNumber'], $result['transactionStatus'], $result, isset($result['date']) ? strtotime($result['date']) : time());

        // Make Invoice
        $order->setInvoice(true);
        break;
    case 3:
        $module->validateOrder($cart_id, Configuration::get('PS_OS_PAYEX_AUTH'), 0, $module->displayName, null, array(), null, true, $customer->secure_key);
        $order = new Order($module->currentOrder);
        if (!Validate::isLoadedObject($order)) {
            die(Tools::displayError($module->l('Unable to place order.')));
        }

        // Save Transaction
        $module->addTransaction($order->id, $result['transactionNumber'], $result['transactionStatus'], $result, isset($result['date']) ? strtotime($result['date']) : time());
        break;
    case 4:
        // Cancel
        $module->validateOrder($cart_id, Configuration::get('PS_OS_CANCELED'), 0, $module->displayName, null, array(), null, true, $customer->secure_key);
        $order = new Order($module->currentOrder);
        if (!Validate::isLoadedObject($order)) {
            die(Tools::displayError($module->l('Unable to place order.')));
        }

        // Save Transaction
        $module->addTransaction($order->id, $result['transactionNumber'], $result['transactionStatus'], $result, isset($result['date']) ? strtotime($result['date']) : time());
        break;
    case 5:
    default:
        // Cancel
        $module->validateOrder($cart_id, Configuration::get('PS_OS_ERROR'), 0, $module->displayName, null, array(), null, true, $customer->secure_key);
        $order = new Order($module->currentOrder);
        if (!Validate::isLoadedObject($order)) {
            die(Tools::displayError($module->l('Unable to place order.')));
        }

        // Save Transaction
        $module->addTransaction($order->id, $result['transactionNumber'], $result['transactionStatus'], $result, isset($result['date']) ? strtotime($result['date']) : time());
        break;
}

// Redirect to Order Confirmation
$redirectUrl = _PS_BASE_URL_ . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&key=' . $customer->secure_key . '&id_cart=' . (int)$cart_id . '&id_module=' . (int)$module->id . '&id_order=' . (int)$module->currentOrder;
Tools::redirect($redirectUrl);

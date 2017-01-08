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

/** @var array PayEx TC Spider IPs */
$allowed_ips = array(
    '82.115.146.170', // Production
    '82.115.146.10' // Test
);

$log = new FileLogger();
$log->setFilename(_PS_ROOT_DIR_ . '/log/payex_tc.log');

// Check is PayEx Request
if (!in_array(Tools::getRemoteAddr(), $allowed_ips)) {
    $log->logDebug('TC: Access denied for this request. It\'s not PayEx Spider.');
    header(sprintf('%s %s %s', 'HTTP/1.1', '403', 'Access denied. Accept PayEx Transaction Callback only.'), true, '403');
    header(sprintf('Status: %s %s', '403', 'Access denied. Accept PayEx Transaction Callback only.'), true, '403');
    exit('Error: Access denied. Accept PayEx Transaction Callback only. ');
}

// Check Post Fields
$log->logDebug('TC: Requested Params: ' . var_export($_POST, true));
if (count($_POST) == 0 || !Tools::getValue('transactionNumber')) {
    $log->logDebug('TC: Error: Empty request received.');
    header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
    header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
    exit('FAILURE');
}

// Get Transaction Details
$transactionId = Tools::getValue('transactionNumber');

// Check Transaction
if (count($module->getTransaction($transactionId)) > 0) {
    // Show "OK"
    $log->logDebug('TC: This transaction has already been registered in store.');
    header(sprintf('%s %s %s', 'HTTP/1.1', '200', 'OK'), true, '200');
    header(sprintf('Status: %s %s', '200', 'OK'), true, '200');
    exit('OK');
}

// Init Environment
$module->getPx()->setEnvironment($module->accountnumber, $module->encryptionkey, (bool)$module->mode);

// Call PxOrder.GetTransactionDetails2
$params = array(
    'accountNumber' => '',
    'transactionNumber' => $transactionId
);
$details = $module->getPx()->GetTransactionDetails2($params);
if ($details['code'] !== 'OK' || $details['description'] !== 'OK' || $details['errorCode'] !== 'OK') {
    die(Tools::displayError($module->getVerboseErrorMessage($details)));
}

$cart_id = $details['orderId'];
$transactionStatus = (int)$details['transactionStatus'];

$log->logDebug('TC: Incoming transaction: ' . $transactionId);
$log->logDebug('TC: Transaction Status: ' . $transactionStatus);
$log->logDebug('TC: Cart Id: ' . $cart_id);

// Get Cart
$cart = new Cart((int)$cart_id);
if (!Validate::isLoadedObject($cart)) {
    $log->logDebug('TC: CartID ' . $cart_id . ' don\'t exists in store.');
    header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
    header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
    exit('FAILURE');
}

// Get Customer
$customer = new Customer((int)$cart->id_customer);
if (!Validate::isLoadedObject($customer)) {
    $log->logDebug('TC: Unable to get customer');
    header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
    header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
    exit('FAILURE');
}

// Check Order
$order_id = Order::getOrderByCartId($cart_id);
if ($order_id !== false) {
    $log->logDebug('TC: OrderID ' . $order_id . ' already exists in store.');
    header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
    header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
    exit('FAILURE');
}

// Complete paid order
if (in_array($transactionStatus, array(0, 3, 6))) {
    // Call PxOrder.Complete
    $params = array(
        'accountNumber' => '',
        'orderRef' => Tools::getValue('orderRef')
    );
    $result = $module->getPx()->Complete($params);
    if ($result['errorCodeSimple'] !== 'OK') {
        $log->logDebug('TC: Error: PxOrder.Complete: ' . $module->getVerboseErrorMessage($result));
        header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
        header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
        exit('FAILURE');
    }

    $log->logDebug('TC: CartId ' . $cart_id . ' Complete with TransactionStatus ' . $result['transactionStatus']);
}

/* 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
switch ($transactionStatus) {
    case 0;
    case 6:
        $amount = $details['amount'];

        $module->validateOrder($cart_id, Configuration::get('PS_OS_PAYEX_CAPTURED'), $amount, $module->displayName, null, array(), null, true, $customer->secure_key);
        $order = new Order($module->currentOrder);
        if (!Validate::isLoadedObject($order)) {
            $log->logDebug('TC: Unable to place order.');
            header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
            header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
            exit('FAILURE');
        }

        // Save Transaction
        $module->addTransaction($order->id, $result['transactionNumber'], $result['transactionStatus'], $result, isset($result['date']) ? strtotime($result['date']) : time());

        // Make Invoice
        $order->setInvoice(true);

        $log->logDebug('TC: CartId: ' . $cart_id . ' Placed order ' . $order->id);
        break;
    case 3:
        $module->validateOrder($cart_id, Configuration::get('PS_OS_PAYEX_AUTH'), 0, $module->displayName, null, array(), null, true, $customer->secure_key);
        $order = new Order($module->currentOrder);
        if (!Validate::isLoadedObject($order)) {
            $log->logDebug('TC: Unable to place order.');
            header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
            header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
            exit('FAILURE');
        }

        // Save Transaction
        $module->addTransaction($order->id, $transactionId, $details['transactionStatus'], $details, isset($details['date']) ? strtotime($details['date']) : time());

        $log->logDebug('TC: CartId: ' . $cart_id . ' Placed order ' . $order->id);
        break;
    case 4;
        // Cancel
        $module->validateOrder($cart_id, Configuration::get('PS_OS_CANCELED'), 0, $module->displayName, null, array(), null, true, $customer->secure_key);
        $order = new Order($module->currentOrder);
        if (!Validate::isLoadedObject($order)) {
            $log->logDebug('TC: Unable to place order.');
            header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
            header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
            exit('FAILURE');
        }

        // Save Transaction
        $module->addTransaction($order->id, $transactionId, $details['transactionStatus'], $details, isset($details['date']) ? strtotime($details['date']) : time());

        $log->logDebug('TC: CartId: ' . $cart_id . ' Placed order ' . $order->id);
        break;
    case 5:
        // Cancel when Errors
        $module->validateOrder($cart_id, Configuration::get('PS_OS_ERROR'), 0, $module->displayName, null, array(), null, true, $customer->secure_key);
        $order = new Order($module->currentOrder);
        if (!Validate::isLoadedObject($order)) {
            $log->logDebug('TC: Unable to place order.');
            header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
            header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
            exit('FAILURE');
        }

        // Save Transaction
        $module->addTransaction($order->id, $transactionId, $details['transactionStatus'], $details, isset($details['date']) ? strtotime($details['date']) : time());

        $log->logDebug('TC: CartId: ' . $cart_id . ' Placed order ' . $order->id);
        break;
    default:
        $log->logDebug('TC: Unknown Transaction Status');
        header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
        header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
        exit('FAILURE');
}

// Show "OK"
$log->logDebug('TC: Done.');
header(sprintf('%s %s %s', 'HTTP/1.1', '200', 'OK'), true, '200');
header(sprintf('Status: %s %s', '200', 'OK'), true, '200');
exit('OK');

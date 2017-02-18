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
require_once dirname(__FILE__) . '/mobilepay.php';

$module = new Mobilepay();
$cookie = Context::getContext()->cookie;
$cart = new Cart((int)$cookie->id_cart);
if (!Validate::isLoadedObject($cart)) {
    Tools::redirect('index.php?controller=order&step=1');
}

$customer = new Customer((int)$cart->id_customer);
if (!Validate::isLoadedObject($customer)) {
    Tools::redirect('index.php?controller=order&step=1');
}

$currency = Currency::getCurrency((int)$cart->id_currency);
$lang = Language::getLanguage((int)$cart->id_lang);

$amount = (float)$cart->getOrderTotal(true, Cart::BOTH);

// Call PxOrder.Initialize8
$params = array(
    'accountNumber' => '',
    'purchaseOperation' => $module->transactiontype,
    'price' => round($amount * 100),
    'priceArgList' => '',
    'currency' => $currency['iso_code'],
    'vat' => 0,
    'orderID' => $cart->id,
    'productNumber' => $cart->id,
    'description' => $module->displayName,
    'clientIPAddress' => Tools::getRemoteAddr(),
    'clientIdentifier' => 'USERAGENT=' . $_SERVER['HTTP_USER_AGENT'],
    'additionalValues' => 'RESPONSIVE=1&USEMOBILEPAY=True',
    'externalID' => '',
    'returnUrl' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/mobilepay/validation.php?id_cart=' . $cart->id,
    'view' => 'CREDITCARD',
    'agreementRef' => '',
    'cancelUrl' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'index.php?controller=order&step=1',
    'clientLanguage' => $module->getLocale($lang)
);
$result = $module->getPx()->Initialize8($params);
if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
    die(Tools::displayError($module->getVerboseErrorMessage($result)));
}

$orderRef = $result['orderRef'];
$redirectUrl = $result['redirectUrl'];

// Redirect to PayEx
Tools::redirect($redirectUrl);

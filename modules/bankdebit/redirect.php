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
require_once dirname(__FILE__) . '/bankdebit.php';
$cookie = Context::getContext()->cookie;

$module = new Bankdebit();
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

// Get Selected bank
$banks = Tools::getValue('banks');
if (empty($banks)) {
    Tools::redirect('index.php?controller=order&step=1');
}

// Call PxOrder.Initialize8
$params = array(
    'accountNumber' => '',
    'purchaseOperation' => $module->transactiontype,
    'price' => 0,
    'priceArgList' => $banks . '=' . round($amount * 100),
    'currency' => $currency['iso_code'],
    'vat' => 0,
    'orderID' => $cart->id,
    'productNumber' => $cart->id,
    'description' => $module->displayName,
    'clientIPAddress' => Tools::getRemoteAddr(),
    'clientIdentifier' => 'USERAGENT=' . $_SERVER['HTTP_USER_AGENT'],
    'additionalValues' => $module->responsive ? 'RESPONSIVE=1' : '',
    'externalID' => '',
    'returnUrl' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/bankdebit/validation.php?id_cart=' . $cart->id,
    'view' => $module->paymentview,
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

if ($module->checkout_info) {
    // add Order Lines
    $i = 1;
    foreach ($cart->getProducts() as $product) {
        // Call PxOrder.AddSingleOrderLine2
        $params = array(
            'accountNumber' => '',
            'orderRef' => $orderRef,
            'itemNumber' => $i,
            'itemDescription1' => $product['name'],
            'itemDescription2' => $product['name'] . ' ' . $product['attributes_small'],
            'itemDescription3' => '',
            'itemDescription4' => '',
            'itemDescription5' => '',
            'quantity' => $product['quantity'],
            'amount' => round(100 * $product['total_wt']), //must include tax
            'vatPrice' => round(100 * round($product['total_wt'] - $product['total'], 2)),
            'vatPercent' => round(100 * $product['rate'])
        );
        $result = $module->getPx()->AddSingleOrderLine2($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            die(Tools::displayError($module->getVerboseErrorMessage($result)));
        }

        $i++;
    };

    // Add Shipping Line
    $total_shipping_tax_incl = _PS_VERSION_ >= 1.5 ? (float)$cart->getTotalShippingCost() : (float)$cart->getOrderShippingCost();
    if ($total_shipping_tax_incl > 0) {
        $carrier = new Carrier((int)$cart->id_carrier);
        $carrier_tax_rate = Tax::getCarrierTaxRate((int)$carrier->id, $cart->id_address_invoice);
        $total_shipping_tax_excl = $total_shipping_tax_incl / (($carrier_tax_rate / 100) + 1);

        $params = array(
            'accountNumber' => '',
            'orderRef' => $orderRef,
            'itemNumber' => $i,
            'itemDescription1' => $carrier->name,
            'itemDescription2' => '',
            'itemDescription3' => '',
            'itemDescription4' => '',
            'itemDescription5' => '',
            'quantity' => 1,
            'amount' => round(100 * $total_shipping_tax_incl), //must include tax
            'vatPrice' => round(100 * round($total_shipping_tax_incl - $total_shipping_tax_excl, 2)),
            'vatPercent' => round(100 * $carrier_tax_rate)
        );
        $result = $module->getPx()->AddSingleOrderLine2($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            die(Tools::displayError($module->getVerboseErrorMessage($result)));
        }

        $i++;
    }

    // Add Discounts Line
    $total_discounts_tax_incl = (float)abs($cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS, $cart->getProducts(), (int)$cart->id_carrier));
    if ($total_discounts_tax_incl > 0) {
        $total_discounts_tax_excl = (float)abs($cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS, $cart->getProducts(), (int)$cart->id_carrier));
        $total_discounts_tax_rate = (($total_discounts_tax_incl / $total_discounts_tax_excl) - 1) * 100;

        $params = array(
            'accountNumber' => '',
            'orderRef' => $orderRef,
            'itemNumber' => $i,
            'itemDescription1' => $module->l('Discount'),
            'itemDescription2' => '',
            'itemDescription3' => '',
            'itemDescription4' => '',
            'itemDescription5' => '',
            'quantity' => 1,
            'amount' => round(-100 * $total_discounts_tax_incl), //must include tax
            'vatPrice' => round(-100 * round($total_discounts_tax_incl - $total_discounts_tax_excl, 2)),
            'vatPercent' => round(100 * $total_discounts_tax_rate)
        );
        $result = $module->getPx()->AddSingleOrderLine2($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            die(Tools::displayError($module->getVerboseErrorMessage($result)));
        }

        $i++;
    }

    // Add Order Address
    $billing_address = new Address((int)$cart->id_address_invoice);

    // Call PxOrder.AddOrderAddress2
    $params = array(
        'accountNumber' => '',
        'orderRef' => $orderRef,
        'billingFirstName' => $billing_address->firstname,
        'billingLastName' => $billing_address->lastname,
        'billingAddress1' => $billing_address->address1,
        'billingAddress2' => $billing_address->address2,
        'billingAddress3' => '',
        'billingPostNumber' => $billing_address->postcode,
        'billingCity' => $billing_address->city,
        'billingState' => (string)State::getNameById($billing_address->id_state),
        'billingCountry' => $billing_address->country,
        'billingCountryCode' => (string)Country::getIsoById($billing_address->id_country),
        'billingEmail' => $customer->email,
        'billingPhone' => $billing_address->phone_mobile,
        'billingGsm' => '',
    );

    $shipping_params = array(
        'deliveryFirstName' => '',
        'deliveryLastName' => '',
        'deliveryAddress1' => '',
        'deliveryAddress2' => '',
        'deliveryAddress3' => '',
        'deliveryPostNumber' => '',
        'deliveryCity' => '',
        'deliveryState' => '',
        'deliveryCountry' => '',
        'deliveryCountryCode' => '',
        'deliveryEmail' => '',
        'deliveryPhone' => '',
        'deliveryGsm' => '',
    );

    if (!$cart->isVirtualCart()) {
        $shipping_address = new Address((int)$cart->id_address_delivery);
        $shipping_params = array(
            'deliveryFirstName' => $shipping_address->firstname,
            'deliveryLastName' => $shipping_address->lastname,
            'deliveryAddress1' => $shipping_address->address1,
            'deliveryAddress2' => $shipping_address->address2,
            'deliveryAddress3' => '',
            'deliveryPostNumber' => $shipping_address->postcode,
            'deliveryCity' => $shipping_address->city,
            'deliveryState' => (string)State::getNameById($shipping_address->id_state),
            'deliveryCountry' => $shipping_address->country,
            'deliveryCountryCode' => (string)Country::getIsoById($shipping_address->id_country),
            'deliveryEmail' => $customer->email,
            'deliveryPhone' => $shipping_address->phone_mobile,
            'deliveryGsm' => '',
        );
    }

    $params += $shipping_params;

    $result = $module->getPx()->AddOrderAddress2($params);
    if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
        die(Tools::displayError($module->getVerboseErrorMessage($result)));
    }
}

// Call PxOrder.PrepareSaleDD2
$params = array(
    'accountNumber' => '',
    'orderRef' => $orderRef,
    'userType' => 0, // Anonymous purchase
    'userRef' => '',
    'bankName' => $banks
);
$result = $module->getPx()->PrepareSaleDD2($params);
if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
    die(Tools::displayError($module->getVerboseErrorMessage($result)));
}

$redirectUrl = $result['redirectUrl'];

// Redirect to PayEx
Tools::redirect($redirectUrl);

<?php
/**
 * @package    PayEx
 * @author    aait.se
 * @copyright Copyright (C) AAIT - All rights reserved.
 * @license  http://shop.aait.se/license.txt EULA
 */

/**
 * @since 1.5.0
 */

class PayexConfirmModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === 'payex') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = new Customer((int)$this->context->cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = Currency::getCurrency($cart->id_currency);
        $lang = Language::getLanguage($cart->id_lang);

        $this->context->customer->email = null; // Prevent mail sending
        $this->module->validateOrder((int)$this->context->cart->id, Configuration::get('PS_OS_PAYEX_OPEN'), 0, $this->module->displayName, null, array(), null, true, $customer->secure_key);
        $order = new Order($this->module->currentOrder);
        $amount = $cart->getOrderTotal(true, Cart::BOTH);

        $additional = $this->module->paymentview == 'PX' ? 'PAYMENTMENU=TRUE' : '';
        if ($this->module->responsive) {
            $separator = (!empty($additional) && mb_substr($additional, -1) !== '&') ? '&' : '';
            $additional .= $separator . 'USECSS=RESPONSIVEDESIGN';
        }

        $returnUrl = _PS_BASE_URL_ . __PS_BASE_URI__.'index.php?controller=order-confirmation?key='.$customer->secure_key.'&id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->module->id.'&id_order='.(int)$this->module->currentOrder;
        $cancelUrl = _PS_BASE_URL_ . __PS_BASE_URI__ . 'index.php?fc=module&module=payex&controller=cancel&id_lang=' . $cart->id_lang . '&order_id=' . $this->module->currentOrder . '&token_order=' . md5(_COOKIE_KEY_ . 'orderId_' . $this->module->currentOrder) . '&key=' . $customer->secure_key;

        // Call PxOrder.Initialize8
        $params = array(
            'accountNumber' => '',
            'purchaseOperation' => $this->module->transactiontype,
            'price' => round($amount * 100),
            'priceArgList' => '',
            'currency' => $currency['iso_code'],
            'vat' => 0,
            'orderID' => $order->reference,
            'productNumber' => $cart->id_customer, // Customer Id
            'description' => $this->module->displayName,
            'clientIPAddress' => Tools::getRemoteAddr(),
            'clientIdentifier' => 'USERAGENT=' . $_SERVER['HTTP_USER_AGENT'],
            'additionalValues' => $additional,
            'externalID' => '',
            'returnUrl' => $returnUrl,
            'view' => $this->module->paymentview,
            'agreementRef' => '',
            'cancelUrl' => $cancelUrl,
            'clientLanguage' => $this->module->getLocale($lang)
        );
        $result = $this->module->getPx()->Initialize8($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            die(Tools::displayError($this->module->getVerboseErrorMessage($result)));
        }

        $orderRef = $result['orderRef'];
        $redirectUrl = $result['redirectUrl'];

        if ($this->module->checkout_info) {
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
                $result = $this->module->getPx()->AddSingleOrderLine2($params);
                if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
                    $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                    die(Tools::displayError($this->module->getVerboseErrorMessage($result)));
                }

                $i++;
            };

            // Add Shipping Line
            if ((float)$order->total_shipping_tax_incl > 0) {
                $carrier = new Carrier((int)$order->id_carrier);

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
                    'amount' => round(100 * $order->total_shipping_tax_incl), //must include tax
                    'vatPrice' => round(100 * round($order->total_shipping_tax_incl - $order->total_shipping_tax_excl, 2)),
                    'vatPercent' => round(100 * $order->carrier_tax_rate)
                );
                $result = $this->module->getPx()->AddSingleOrderLine2($params);
                if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
                    $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                    die(Tools::displayError($this->module->getVerboseErrorMessage($result)));
                }

                $i++;
            }

            // Add Discounts Line
            if ((float)$order->total_discounts > 0) {
                $taxPrice = round($order->total_discounts_tax_incl - $order->total_discounts_tax_excl, 2);
                $taxPercent = ($taxPrice > 0) ? round(100 / ($order->total_discounts_tax_excl / $taxPrice)) : 0;

                $params = array(
                    'accountNumber' => '',
                    'orderRef' => $orderRef,
                    'itemNumber' => $i,
                    'itemDescription1' => $this->module->l('Discount'),
                    'itemDescription2' => '',
                    'itemDescription3' => '',
                    'itemDescription4' => '',
                    'itemDescription5' => '',
                    'quantity' => 1,
                    'amount' => round(-100 * $order->total_discounts_tax_incl), //must include tax
                    'vatPrice' => round(100 * $taxPrice),
                    'vatPercent' => round(100 * $taxPercent)
                );
                $result = $this->module->getPx()->AddSingleOrderLine2($params);
                if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
                    $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                    die(Tools::displayError($this->module->getVerboseErrorMessage($result)));
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
                'billingState' => (string) State::getNameById($billing_address->id_state),
                'billingCountry' => $billing_address->country,
                'billingCountryCode' => (string) Country::getIsoById($billing_address->id_country),
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

            if (!$order->isVirtual()) {
                $shipping_address = new Address((int) $cart->id_address_delivery);
                $shipping_params = array(
                    'deliveryFirstName' => $shipping_address->firstname,
                    'deliveryLastName' => $shipping_address->lastname,
                    'deliveryAddress1' => $shipping_address->address1,
                    'deliveryAddress2' => $shipping_address->address2,
                    'deliveryAddress3' => '',
                    'deliveryPostNumber' => $shipping_address->postcode,
                    'deliveryCity' => $shipping_address->city,
                    'deliveryState' => (string) State::getNameById($shipping_address->id_state),
                    'deliveryCountry' => $shipping_address->country,
                    'deliveryCountryCode' => (string) Country::getIsoById($shipping_address->id_country),
                    'deliveryEmail' => $customer->email,
                    'deliveryPhone' => $shipping_address->phone_mobile,
                    'deliveryGsm' => '',
                );
            }

            $params += $shipping_params;

            $result = $this->module->getPx()->AddOrderAddress2($params);
            if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                die(Tools::displayError($this->module->getVerboseErrorMessage($result)));
            }
        }

        // Redirect to PayEx
        Tools::redirect($redirectUrl);
    }
}

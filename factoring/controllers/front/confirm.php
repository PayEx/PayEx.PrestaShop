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

class FactoringConfirmModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        /**
         * Note:
         * To Test use: 8111032382, 195907195662, 195806045265, 197311012525
         */
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === 'factoring') {
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

        // Add Product fee to Cart
        if ($this->module->factoring_fee_price > 0 && !$this->module->isInCart($this->context->cart, $this->module->factoring_fee_product_id))
        {
            $this->context->cart->updateQty(1, $this->module->factoring_fee_product_id);
            $product= new Product($this->module->factoring_fee_product_id);
            $product->addStockMvt(1, 1);
            $product->update();
        }

        // Validate Social security number
        $ssn = Tools::getValue('social-security-number');
        if (empty($ssn)) {
            Tools::redirect(Context::getContext()->link->getModuleLink('factoring', 'payment', array(
                'error' => $this->module->l('Please enter your Social Security Number')
            )));
        }

        $billing_address = new Address((int)$cart->id_address_invoice);
        $shipping_address = new Address((int)$cart->id_address_delivery);

        // Call PxVerification.GetConsumerLegalAddress
        $params = array(
            'accountNumber' => '',
            'countryCode' => Country::getIsoById($billing_address->id_country),
            'socialSecurityNumber' => $ssn
        );
        $this->module->getPx()->setEnvironment($this->module->accountnumber, $this->module->encryptionkey, (bool)$this->module->mode);
        $legal = $this->module->getPx()->GetConsumerLegalAddress($params);
        if ($legal['code'] !== 'OK' || $legal['description'] !== 'OK' || $legal['errorCode'] !== 'OK') {
            if (preg_match('/\bInvalid parameter:SocialSecurityNumber\b/i', $legal['description'])) {
                Tools::redirect(Context::getContext()->link->getModuleLink('factoring', 'payment', array(
                    'error' => $this->module->l('Invalid Social Security Number')
                )));
            }

            Tools::redirect(Context::getContext()->link->getModuleLink('factoring', 'payment', array(
                'error' => $this->module->getVerboseErrorMessage($legal)
            )));
        }

        $currency = Currency::getCurrency($cart->id_currency);
        $lang = Language::getLanguage($cart->id_lang);

        $this->module->validateOrder((int)$this->context->cart->id, Configuration::get('PS_OS_PAYEX_OPEN'), 0, $this->module->displayName, null, array(), null, true, $customer->secure_key);
        $order = new Order($this->module->currentOrder);
        $amount = $cart->getOrderTotal(true, Cart::BOTH);

        // Selected Payment Mode
        if ($this->module->type === 'SELECT') {
            $this->module->type = Tools::getValue('factoring-menu');
        }

        // Call PxOrder.Initialize8
        $params = array(
            'accountNumber' => '',
            'purchaseOperation' => 'AUTHORIZATION',
            'price' => round($amount * 100),
            'priceArgList' => '',
            'currency' => $currency['iso_code'],
            'vat' => 0,
            'orderID' => $order->reference,
            'productNumber' => $cart->id_customer, // Customer Id
            'description' => $this->module->displayName,
            'clientIPAddress' => Tools::getRemoteAddr(),
            'clientIdentifier' => '',
            'additionalValues' => '',
            'externalID' => '',
            'returnUrl' => 'http://localhost.no/return',
            'view' => $this->module->type,
            'agreementRef' => '',
            'cancelUrl' => 'http://localhost.no/cancel',
            'clientLanguage' => 'en-US'
        );
        $this->module->getPx()->setEnvironment($this->module->accountnumber, $this->module->encryptionkey, (bool)$this->module->mode);
        $result = $this->module->getPx()->Initialize8($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            die(Tools::displayError($this->module->getVerboseErrorMessage($result)));
        }

        $orderRef = $result['orderRef'];

        // Call PxOrder.PurchaseInvoiceSale / PxOrder.PurchasePartPaymentSale
        $params = array(
            'accountNumber' => '',
            'orderRef' => $orderRef,
            'socialSecurityNumber' => $ssn,
            'legalFirstName' => $billing_address->firstname,
            'legalLastName' => $billing_address->lastname,
            'legalStreetAddress' => $billing_address->address1 . ' ' . $billing_address->address2,
            'legalCoAddress' => '',
            'legalPostNumber' => str_replace(' ', '', $billing_address->postcode),
            'legalCity' => $shipping_address->city,
            'legalCountryCode' => (string) Country::getIsoById($billing_address->id_country),
            'email' => $customer->email,
            'msisdn' => (substr($shipping_address->phone_mobile, 0, 1) === '+') ? $shipping_address->phone_mobile : '+' . $shipping_address->phone_mobile,
            'ipAddress' => Tools::getRemoteAddr(),
        );

        if ($this->module->type === 'FACTORING') {
            $result = $this->module->getPx()->PurchaseInvoiceSale($params);
        } else {
            $result = $this->module->getPx()->PurchasePartPaymentSale($params);
        }

        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            die(Tools::displayError($this->module->getVerboseErrorMessage($result)));
        }

        // Save Transaction
        $this->module->addTransaction($order->id, $result['transactionNumber'], $result['transactionStatus'], $result, isset($result['date']) ? strtotime($result['date']) : time());

        // Get Invoice Link
        /* $params = array(
            'accountNumber' => '',
            'transactionNumber' => $result['transactionNumber']
        );
        $result1 = $this->module->getPx()->InvoiceLinkGet($params); */

        $message = '';
        /* Transaction statuses:
        0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        switch ((int)$result['transactionStatus']) {
            case 0:
            case 6:
                $order->setCurrentState(Configuration::get('PS_OS_PAYEX_CAPTURED'));
                $order->setInvoice(true);
                $invoice = !empty($order->invoice_number) ? new OrderInvoice($order->invoice_number) : null;
                $order->addOrderPayment($order->total_paid, $order->payment, $result['transactionNumber'], null, date('Y-m-d H:i:s', isset($result['date']) ? strtotime($result['date']) : time()), $invoice);
                $status = 'ok';
                break;
            case 3:
                $order->setCurrentState(Configuration::get('PS_OS_PAYEX_AUTH'));
                $status = 'pending';
                break;
            case 4:
                // Cancel
                $order->setCurrentState(Configuration::get('PS_OS_CANCELED'));
                $status = 'cancel';
                break;
            case 5:
            default:
                // Cancel
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                $status = 'error';
                $message = $this->module->getVerboseErrorMessage($result);
                break;
        }

        // Redirect to Order Confirmation
        $returnUrl = _PS_BASE_URL_ . __PS_BASE_URI__.'index.php?controller=order-confirmation?key='.$customer->secure_key.'&id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->module->id.'&id_order='.(int)$this->module->currentOrder.'&status='.$status;
        if (!empty($message)) {
            $returnUrl .= '&message=' . $message;
        }
        Tools::redirect($returnUrl);
    }
}

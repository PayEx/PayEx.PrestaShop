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
class PxoneclickPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {

        $this->display_column_left = false;
        parent::initContent();

        $cart = $this->context->cart;
        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }

        // Check customer agreement
        $agreement_status = 3; // Not Exists
        $customer_id = $this->context->cart->id_customer;

        // Get Agreement Status
        $agreement = Db::getInstance()->executeS('SELECT agreement_ref FROM ' . _DB_PREFIX_ . 'payex_autopay WHERE customer_id = ' . (int)$customer_id . ';');
        if (count($agreement) > 0) {
            $agreement = array_shift($agreement);

            // Check Agreement Status
            // Call PxAgreement.AgreementCheck
            $params = array(
                'accountNumber' => '',
                'agreementRef' => $agreement['agreement_ref'],
            );
            $this->module->getPx()->setEnvironment($this->module->accountnumber, $this->module->encryptionkey, (bool)$this->module->mode);
            $result = $this->module->getPx()->AgreementCheck($params);
            if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
                die(Tools::displayError($this->module->l('Error:') . ' ' . $result['errorCode'] . ' (' . $result['description'] . ')'));
            }

            $agreement_status = (int)$result['agreementStatus'];
        }

        $this->context->smarty->assign(array(
            'agreement_status' => $agreement_status,
            'agreement_url' => $this->module->agreement_url,
            'nbProducts' => $cart->nbProducts(),
            'cust_currency' => $cart->id_currency,
            'currencies' => $this->module->getCurrency((int)$cart->id_currency),
            'total' => $cart->getOrderTotal(true, Cart::BOTH),
            'this_path' => $this->module->getPathUri(),
            'this_path_px' => $this->module->getPathUri(),
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
        ));

        $this->setTemplate('payment_execution.tpl');
    }
}

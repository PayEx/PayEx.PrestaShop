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
class BankdebitPaymentModuleFrontController extends ModuleFrontController
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

        $banks = array();
        $available_banks = $this->module->available;
        foreach($this->module->banks as $_id => $bank_code) {
            if (isset($available_banks[$bank_code])) {
                $banks[$bank_code] = $available_banks[$bank_code];
            }
        }

        $this->context->smarty->assign(array(
            'banks' => $banks,
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

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
class BankdebitCancelModuleFrontController extends ModuleFrontController
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

        $customer = new Customer((int)$this->context->cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $order_id = Tools::getValue('order_id');
        $token_order = Tools::getValue('token_order');

        if ($token_order === md5(_COOKIE_KEY_ . 'orderId_' . $order_id)) {
            // @todo Recovery cart
            // @see FrontController::recoverCart
            $cart = Cart::getCartByOrderId($order_id);
            if (Validate::isLoadedObject($cart)) {
                $customer = new Customer((int)$cart->id_customer);
                if (Validate::isLoadedObject($customer)) {
                    $this->context->cookie->id_customer = (int)$customer->id;
                    $this->context->cookie->customer_lastname = $customer->lastname;
                    $this->context->cookie->customer_firstname = $customer->firstname;
                    $this->context->cookie->logged = 1;
                    $this->context->cookie->is_guest = $customer->isGuest();
                    $this->context->cookie->passwd = $customer->passwd;
                    $this->context->cookie->email = $customer->email;
                }
            }

            // Cancel Order
            $order = new Order($order_id);
            $order->setCurrentState(Configuration::get('PS_OS_CANCELED'));
        }

        // Redirect to Cart
        Tools::redirect('index.php?controller=order&step=1');
    }
}
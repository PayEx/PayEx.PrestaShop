<?php
/**
* AAIT
*
*  @author    aait.se
*  @package   PayEx
*  @copyright Copyright (C) AAIT - All rights reserved.
*  @license   http://shop.aait.se/license.txt  EULA
*/

/**
 * @since 1.5.0
 */
class FactoringPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {

        $this->display_column_left = false;
        parent::initContent();

        // Add Product fee to Cart
        if ($this->module->factoring_fee_price > 0 && !$this->module->isInCart($this->context->cart, $this->module->factoring_fee_product_id))
        {
            $this->context->cart->updateQty(1, $this->module->factoring_fee_product_id);
            $product= new Product($this->module->factoring_fee_product_id);
            $product->addStockMvt(1, 1);
            $product->update();
        }

        $cart = $this->context->cart;
        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }

        $this->context->smarty->assign(array(
            'nbProducts' => $cart->nbProducts(),
            'cust_currency' => $cart->id_currency,
            'currencies' => $this->module->getCurrency((int)$cart->id_currency),
            'total' => $cart->getOrderTotal(true, Cart::BOTH),
            'fee' => $this->module->factoring_fee_price > 0 ? (float)Product::getPriceStatic($this->module->factoring_fee_product_id) : 0,
            'type' => $this->module->type,
            'this_path' => $this->module->getPathUri(),
            'this_path_px' => $this->module->getPathUri(),
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/',
            'error' => Tools::getValue('error')
        ));

        $this->setTemplate('payment_execution.tpl');
    }
}

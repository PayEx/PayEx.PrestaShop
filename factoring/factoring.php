<?php
/**
 * @package    PayEx
 * @author    aait.se
 * @copyright Copyright (C) AAIT - All rights reserved.
 * @license  http://shop.aait.se/license.txt EULA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Factoring extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    protected $_px;
    protected $_log;

    public $accountnumber;
    public $encryptionkey;
    public $mode;

    /**
     * Init
     */
    public function __construct()
    {
        $this->name = 'factoring';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.3';
        $this->author = 'AAIT';

        $this->currencies = true; // binding this method of payment to a specific currency
        $this->currencies_mode = 'checkbox';

        // Init Configuration
        $config = Configuration::getMultiple(array('PX_FC_ACCOUNT_NUMBER', 'PX_FC_ENCRYPTION_KEY', 'PX_FC_TESTMODE', 'PX_FC_FEE_PRODUCT_ID', 'PX_FC_FEE_PRICE', 'PX_FC_FEE_TAX_RULE', 'PX_FC_TYPE'));
        $this->accountnumber = isset($config['PX_FC_ACCOUNT_NUMBER']) ? $config['PX_FC_ACCOUNT_NUMBER'] : '';
        $this->encryptionkey = isset($config['PX_FC_ENCRYPTION_KEY']) ? $config['PX_FC_ENCRYPTION_KEY'] : '';
        $this->mode = isset($config['PX_FC_TESTMODE']) ? $config['PX_FC_TESTMODE'] : 1;
        $this->factoring_fee_product_id = isset($config['PX_FC_FEE_PRODUCT_ID']) ? $config['PX_FC_FEE_PRODUCT_ID'] : false;
        $this->factoring_fee_price = isset($config['PX_FC_FEE_PRICE']) ? $config['PX_FC_FEE_PRICE'] : 0;
        $this->factoring_fee_tax_rule = isset($config['PX_FC_FEE_TAX_RULE']) ? $config['PX_FC_FEE_TAX_RULE'] : 0;
        $this->type = isset($config['PX_FC_TYPE']) ? $config['PX_FC_TYPE'] : 'FINANCING';

        // Init PayEx
        $this->getPx()->setEnvironment($this->accountnumber, $this->encryptionkey, (bool)$this->mode);

        parent::__construct();

        $this->displayName = $this->l('Payex Factoring');
        $this->description = $this->l('Accept payments for your products using Payex Factoring.');
        $this->confirmUninstall = $this->l('Are you sure about removing these details?');

        // Some checks...
        if (empty($this->accountnumber) || empty($this->encryptionkey)) {
            $this->warning[] = $this->l('PayEx Account number and encryption key must be configured before using this module.');
        }

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning[] = $this->l('No currency has been set for this module.');
        }
    }

    /**
     * Install Action
     * @return bool
     */
    public function install()
    {
        if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('paymentReturn') || !$this->registerHook('adminOrder') || !$this->registerHook('BackOfficeHeader')) {
            return false;
        }

        /* The SOAP PHP extension must be enabled to use this module */
        if (!extension_loaded('soap')) {
            $this->_errors[] = $this->l('Sorry, this module requires the SOAP PHP Extension (http://www.php.net/soap), which is not enabled on your server. Please ask your hosting provider for assistance.');
            return false;
        }

        /* The OpenSSL PHP extension must be enabled to use this module */
        if (!extension_loaded('openssl')) {
            $this->_errors[] = $this->l('Sorry, this module requires the OpenSSL PHP Extension (http://www.php.net/manual/en/openssl.installation.php), which is not enabled on your server. Please ask your hosting provider for assistance.');
            return false;
        }

        // Install Payment Statuses
        $this->addOrderStates();

        // Create Payment Method table for Transactions
        Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "payex_factoring_transactions` (
            `id` int(10) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) DEFAULT NULL COMMENT 'Order Id',
            `transaction_id` int(11) DEFAULT NULL COMMENT 'PayEx Transaction Id',
            `transaction_status` int(11) DEFAULT NULL COMMENT 'PayEx Transaction Status',
            `transaction_data` text COMMENT 'PayEx Transaction Data',
            `date` datetime DEFAULT NULL COMMENT 'PayEx Transaction Date',
            PRIMARY KEY (`id`),
            UNIQUE KEY `transaction_id` (`transaction_id`),
            KEY `order_id` (`order_id`),
            KEY `transaction_status` (`transaction_status`),
            KEY `date` (`date`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;
        ");

        // Set Payment Settings
        Configuration::updateValue('PX_FC_ACCOUNT_NUMBER', '');
        Configuration::updateValue('PX_FC_ENCRYPTION_KEY', '');
        Configuration::updateValue('PX_FC_TESTMODE', 1);
        Configuration::updateValue('PX_FC_TRANSACTION_TYPE', 'AUTHORIZATION');
        Configuration::updateValue('PX_FC_FEE_PRODUCT_ID', false);
        Configuration::updateValue('PX_FC_FEE_PRICE', 0);
        Configuration::updateValue('PX_FC_FEE_TAX_RULE', 0);
        Configuration::updateValue('PX_FC_TYPE', 'FINANCING');
        return true;
    }

    /**
     * Uninstall Action
     * @return bool
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        // Drop the Payment Method table
        Db::getInstance()->execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "payex_factoring_transactions`; ");

        // Remove fee product
        $productId = (int)Configuration::get('PX_FC_FEE_PRODUCT_ID');
        if ($productId) {
            $product = new Product($productId);
            $product->delete();
        }

        /* Clean configuration table */
        Configuration::deleteByName('PX_FC_ACCOUNT_NUMBER');
        Configuration::deleteByName('PX_FC_ENCRYPTION_KEY');
        Configuration::deleteByName('PX_FC_TESTMODE');
        Configuration::deleteByName('PX_FC_TRANSACTION_TYPE');
        Configuration::deleteByName('PX_FC_FEE_PRODUCT_ID');
        Configuration::deleteByName('PX_FC_FEE_PRICE');
        Configuration::deleteByName('PX_FC_FEE_TAX_RULE');
        Configuration::deleteByName('PX_FC_TYPE');

        return true;
    }

    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('accountnumber')) {
                $this->_postErrors[] = $this->l('Account number are required.');
            }

            if (!Tools::getValue('encryptionkey')) {
                $this->_postErrors[] = $this->l('Encryption key is required.');
            }
        }
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {

            Configuration::updateValue('PX_FC_ACCOUNT_NUMBER', Tools::getValue('accountnumber'));
            Configuration::updateValue('PX_FC_ENCRYPTION_KEY', Tools::getValue('encryptionkey'));
            Configuration::updateValue('PX_FC_TESTMODE', Tools::getValue('mode'));
            Configuration::updateValue('PX_FC_TRANSACTION_TYPE', Tools::getValue('transactiontype'));
            Configuration::updateValue('PX_FC_FEE_PRICE', Tools::getValue('factoring_fee_price'));
            Configuration::updateValue('PX_FC_FEE_TAX_RULE', Tools::getValue('factoring_fee_tax_rule'));
            Configuration::updateValue('PX_FC_TYPE', Tools::getValue('type'));

            // Fee product
            $productId = (int)Configuration::get('PX_FC_FEE_PRODUCT_ID');
            if (!$productId) {
                $product = new Product();
                $product->out_of_stock = 1;
                $product->available_for_order = true;
                $product->id_category_default = 2;

                $languages = Language::getLanguages(false);
                foreach ($languages as $language)
                {
                    $product->name[$language['id_lang']] = $this->l('Factoring Fee');
                    $product->link_rewrite[$language['id_lang']] = 'factoring_fee' . $language['id_lang'];
                }
                $product->price = 0;
                $product->active = false;
                $product->add();
                $productId = (int)$product->id;
            }
            Configuration::updateValue('PX_FC_FEE_PRODUCT_ID', $productId);

            // Update Fee Product
            $product = new Product($productId);
            $product->price = (float)Tools::getValue('factoring_fee_price');
            $product->id_tax_rules_group = (int)Tools::getValue('factoring_fee_tax_rule');
            $product->update();
            StockAvailable::setProductOutOfStock($productId, true, null, 0);
        }

        $this->_html .= '<div class="conf confirm"> ' . $this->l('Settings updated') . '</div>';
    }

    private function _displayForm()
    {
        $this->_html .= '<img src="../modules/factoring/logo.gif" style="float:left; margin-right:15px;" width="86" height="49"><b>'
            . $this->l('This module allows you to accept secure payments by PayEx.') . '</b><br /><br />';

        $tax_rules = TaxRulesGroup::getTaxRulesGroupsForOptions();
        $tax_rules_options = '';
        foreach ($tax_rules as $tax_rule) {
            if (Tools::getValue('factoring_fee_tax_rule', $this->factoring_fee_tax_rule) == $tax_rule['id_tax_rules_group']) {
                $tax_rules_options .= '<option selected="selected" value="' . $tax_rule['id_tax_rules_group'] . '">' .  $tax_rule['name'] . '</option>';
            } else {
                $tax_rules_options .= '<option value="' . $tax_rule['id_tax_rules_group'] . '">' .  $tax_rule['name'] . '</option>';
            }
        }

        $this->_html .=
            '<form action="' . Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']) . '" method="post">
			<fieldset>
			<legend><img src="../img/admin/contact.gif" />' . $this->l('Settings') . '</legend>
				<table border="0" width="500" cellpadding="0" cellspacing="0" id="form">
					<tr>
					<td colspan="2">' . $this->l('Please specify PayEx account details.') . '.<br /><br />
					</td>
					</tr>
					<tr>
					    <td width="130" style="height: 35px;">' . $this->l('Account number') . '</td>
					    <td><input type="text" name="accountnumber" value="' . htmlentities(Tools::getValue('accountnumber', $this->accountnumber), ENT_COMPAT, 'UTF-8') . '" style="width: 300px;" /></td>
					    </tr>
					<tr>
						<td width="130" style="vertical-align: top;">' . $this->l('Encryption key') . '</td>
						<td><input type="text" name="encryptionkey" value="' . htmlentities(Tools::getValue('encryptionkey', $this->encryptionkey), ENT_COMPAT, 'UTF-8') . '" style="width: 300px;" /></td>
					</tr>
					<tr>
						<td width="130" style="vertical-align: top;">' . $this->l('Mode') . '</td>
						<td>
							<select name="mode">
                                <option ' . (Tools::getValue('mode', $this->mode) == '1' ? 'selected="selected"' : '') . 'value="1">Test</option>
                                <option ' . (Tools::getValue('mode', $this->mode) == '0' ? 'selected="selected"' : '') . 'value="0">Live</option>
                            </select>
						</td>
					</tr>
					<tr>
						<td width="130" style="vertical-align: top;">' . $this->l('Payment Type') . '</td>
						<td>
							<select name="type">
                                <option ' . (Tools::getValue('type', $this->type) == 'SELECT' ? 'selected="selected"' : '') . 'value="SELECT">User select</option>
                                <option ' . (Tools::getValue('type', $this->type) == 'FINANCING' ? 'selected="selected"' : '') . 'value="FINANCING">Financing Invoice</option>
                                <option ' . (Tools::getValue('type', $this->type) == 'FACTORING' ? 'selected="selected"' : '') . 'value="FACTORING">Invoice 2.0 (Factoring)</option>
                                <option ' . (Tools::getValue('type', $this->type) == 'CREDITACCOUNT' ? 'selected="selected"' : '') . 'value="CREDITACCOUNT">Part Payment</option>
                            </select>
						</td>
					</tr>
					<tr>
						<td width="130" style="vertical-align: top;">' . $this->l('Factoring Fee') . '</td>
						<td>
						    <input name="factoring_fee_price" type="numberic" step="any" size="11" maxlength="14" onchange="this.value = this.value.replace(/,/g, '.');" value="' . htmlentities(Tools::getValue('factoring_fee_price', $this->factoring_fee_price), ENT_COMPAT, 'UTF-8') . '" />
						</td>
					</tr>
					<tr>
						<td width="130" style="vertical-align: top;">' . $this->l('Factoring Fee: Tax Rule') . '</td>
						<td>
							<select name="factoring_fee_tax_rule">
                                ' . $tax_rules_options . '
                            </select>
						</td>
					</tr>
					<tr><td colspan="2" align="center"><input class="button" name="btnSubmit" value="' . $this->l('Update settings') . '" type="submit" /></td></tr>
				</table>
			</fieldset>
		</form>';
    }

    public function getContent()
    {
        $this->_html = '<h2>' . $this->displayName . '</h2>';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= '<div class="alert error">' . $err . '</div>';
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_displayForm();

        return $this->_html;
    }

    public function hookPayment($params)
    {

        if (!$this->active) {
            return;
        }

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_px' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
        ));
        return $this->display(__FILE__, 'payment.tpl');
    }

    /**
     * Hook: Payment Return
     * @param $params
     * @return bool
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        if ($params['objOrder']->module !== $this->name) {
            return false;
        }

        // Remove Fee product from Cart
        $cart = new Cart((int)$params['objOrder']->id_cart);
        if (Validate::isLoadedObject($cart)) {
            $cart->deleteProduct($this->factoring_fee_product_id);
            $cart->save();
        }

        $status = Tools::getValue('status');
        if (empty($status)) {
            return;
        }

        $order = $params['objOrder'];

        $this->smarty->assign(array(
            'message' => Tools::getValue('message'),
            'status' => $status,
            'id_order' => $order->id
        ));

        if (property_exists($order, 'reference') && !empty($order->reference)) {
            $this->smarty->assign('reference', $order->reference);
        }

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    /**
     * Check Currency is supported
     * @param $cart
     * @return bool
     */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get PayEx handler
     * @return Px
     */
    public function getPx()
    {
        if (!$this->_px) {
            if (!class_exists('Px')) {
                require_once dirname(__FILE__) . '/library/Px/Px.php';
            }

            $this->_px = new Px();
        }

        return $this->_px;
    }

    /**
     * Add PayEx Order Statuses
     */
    private function addOrderStates()
    {
        if (!(Configuration::get('PS_OS_PAYEX_OPEN') > 0)) {
            // Open
            $OrderState = new OrderState(null, Configuration::get('PS_LANG_DEFAULT'));
            $OrderState->name = 'PayEx: Awaiting payment';
            $OrderState->invoice = false;
            $OrderState->send_email = true;
            $OrderState->module_name = $this->name;
            $OrderState->color = '#d3d3d3';
            $OrderState->unremovable = true;
            $OrderState->hidden = false;
            $OrderState->logable = false;
            $OrderState->delivery = false;
            $OrderState->shipped = false;
            $OrderState->paid = false;
            $OrderState->deleted = false;
            $OrderState->template = 'order_changed';
            $OrderState->add();

            Configuration::updateValue('PS_OS_PAYEX_OPEN', $OrderState->id);

            if (file_exists(dirname(dirname(dirname(__FILE__))) . '/img/os/9.gif')) {
                @copy(dirname(dirname(dirname(__FILE__))) . '/img/os/9.gif', dirname(dirname(dirname(__FILE__))) . '/img/os/' . $OrderState->id . '.gif');
            }
        }

        if (!(Configuration::get('PS_OS_PAYEX_PENDING') > 0)) {
            // PENDING
            $OrderState = new OrderState(null, Configuration::get('PS_LANG_DEFAULT'));
            $OrderState->name = 'PayEx: Pending Payment';
            $OrderState->invoice = false;
            $OrderState->send_email = true;
            $OrderState->module_name = $this->name;
            $OrderState->color = 'DarkOrange';
            $OrderState->unremovable = true;
            $OrderState->hidden = false;
            $OrderState->logable = false;
            $OrderState->delivery = false;
            $OrderState->shipped = false;
            $OrderState->paid = false;
            $OrderState->deleted = false;
            $OrderState->template = 'order_changed';
            $OrderState->add();

            Configuration::updateValue('PS_OS_PAYEX_PENDING', $OrderState->id);

            if (file_exists(dirname(dirname(dirname(__FILE__))) . '/img/os/9.gif')) {
                @copy(dirname(dirname(dirname(__FILE__))) . '/img/os/9.gif', dirname(dirname(dirname(__FILE__))) . '/img/os/' . $OrderState->id . '.gif');
            }
        }

        if (!(Configuration::get('PS_OS_PAYEX_AUTH') > 0)) {
            // AUTHORIZED
            $OrderState = new OrderState(null, Configuration::get('PS_LANG_DEFAULT'));
            $OrderState->name = 'PayEx: Payment Authorized';
            $OrderState->invoice = false;
            $OrderState->send_email = true;
            $OrderState->module_name = $this->name;
            $OrderState->color = 'DarkOrange';
            $OrderState->unremovable = true;
            $OrderState->hidden = false;
            $OrderState->logable = false;
            $OrderState->delivery = false;
            $OrderState->shipped = false;
            $OrderState->paid = false;
            $OrderState->deleted = false;
            $OrderState->template = 'order_changed';
            $OrderState->add();

            Configuration::updateValue('PS_OS_PAYEX_AUTH', $OrderState->id);

            if (file_exists(dirname(dirname(dirname(__FILE__))) . '/img/os/10.gif')) {
                @copy(dirname(dirname(dirname(__FILE__))) . '/img/os/10.gif', dirname(dirname(dirname(__FILE__))) . '/img/os/' . $OrderState->id . '.gif');
            }
        }

        if (!(Configuration::get('PS_OS_PAYEX_CAPTURED') > 0)) {
            // CAPTURED
            $OrderState = new OrderState(null, Configuration::get('PS_LANG_DEFAULT'));
            $OrderState->name = 'PayEx: Payment Captured';
            $OrderState->invoice = false;
            $OrderState->send_email = true;
            $OrderState->module_name = $this->name;
            $OrderState->color = 'RoyalBlue';
            $OrderState->unremovable = true;
            $OrderState->hidden = false;
            $OrderState->logable = false;
            $OrderState->delivery = false;
            $OrderState->shipped = false;
            $OrderState->paid = false;
            $OrderState->deleted = false;
            $OrderState->template = 'order_changed';
            $OrderState->add();

            Configuration::updateValue('PS_OS_PAYEX_CAPTURED', $OrderState->id);

            if (file_exists(dirname(dirname(dirname(__FILE__))) . '/img/os/10.gif')) {
                @copy(dirname(dirname(dirname(__FILE__))) . '/img/os/10.gif', dirname(dirname(dirname(__FILE__))) . '/img/os/' . $OrderState->id . '.gif');
            }
        }
    }

    /**
     * Hook: BackOfficeHeader
     */
    public function hookBackOfficeHeader()
    {
        /* Continue only if we are on the order's details page (Back-office) */
        if (!isset($_GET['vieworder']) || !isset($_GET['id_order'])) {
            return;
        }

        $order = new Order($_GET['id_order']);
        if ($order->module !== $this->name) {
            return;
        }

        // Fetch Info Action
        if (Tools::isSubmit('process_fetch') && isset($_POST['payex_transaction_id'])) {
            $payex_transaction_id = $_POST['payex_transaction_id'];

            // Call PxOrder.GetTransactionDetails2
            $params = array(
                'accountNumber' => '',
                'transactionNumber' => $payex_transaction_id
            );
            $result = $this->getPx()->GetTransactionDetails2($params);
            if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
                $output = array(
                    'status' => 'error',
                    'message' => $this->getVerboseErrorMessage($result)
                );
                die(Tools::jsonEncode($output));
            }

            // Update Transaction Info
            if (!empty($result['transactionStatus'])) {
                if (!Db::getInstance()->Execute('UPDATE `' . _DB_PREFIX_ . 'payex_factoring_transactions` SET transaction_status=' . pSQL((int)$result['transactionStatus']) . ', transaction_data="' . pSQL(serialize($result)) . '" WHERE transaction_id=' . $payex_transaction_id . ';')) {
                    die(Tools::displayError('Error when executing database query'));
                }
            }

            $output = array(
                'status' => 'ok',
                'message' => $this->l('Transaction data successfully fetched.')
            );
            die(Tools::jsonEncode($output));
        }

        // Capture Action
        if (Tools::isSubmit('process_capture') && isset($_POST['payex_order_id']) && isset($_POST['payex_transaction_id'])) {
            $order_id = $_POST['payex_order_id'];
            $payex_transaction_id = $_POST['payex_transaction_id'];

            $order = new Order($order_id);
            $order_fields = $order->getFields();

            // Call PxOrder.Capture5
            $params = array(
                'accountNumber' => '',
                'transactionNumber' => $payex_transaction_id,
                'amount' => round(100 * $order_fields['total_paid']),
                'orderId' => $order_fields['reference'],
                'vatAmount' => round(100 * ($order_fields['total_paid_tax_incl'] - $order_fields['total_paid_tax_excl'])),
                'additionalValues' => 'FINANCINGINVOICE_ORDERLINES=' . urlencode($this->getInvoiceExtraPrintBlocksXML($order))
            );
            $result = $this->getPx()->Capture5($params);
            if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
                $output = array(
                    'status' => 'error',
                    'message' => $this->getVerboseErrorMessage($result)
                );
                die(Tools::jsonEncode($output));
            }

            // Save Transaction
            $this->addTransaction($order->id, $result['transactionNumber'], $result['transactionStatus'], $result, isset($result['date']) ? strtotime($result['date']) : time());

            // Set Order Status
            $order->setCurrentState(Configuration::get('PS_OS_PAYEX_CAPTURED'));

            // Create Invoice
            $order->setInvoice(true);

            // Add Payment Transaction
            $invoice = !empty($order->invoice_number) ? new OrderInvoice($order->invoice_number) : null;
            $order->addOrderPayment($order->total_paid, $order->payment, $result['transactionNumber'], null, date('Y-m-d H:i:s', isset($result['date']) ? strtotime($result['date']) : time()), $invoice);

            $output = array(
                'status' => 'ok',
                'message' => $this->l('Order successfully captured.')
            );
            die(Tools::jsonEncode($output));
        }

        // Cancel Action
        if (Tools::isSubmit('process_cancel') && isset($_POST['payex_order_id']) && isset($_POST['payex_transaction_id'])) {
            $order_id = $_POST['payex_order_id'];
            $payex_transaction_id = $_POST['payex_transaction_id'];

            $order = new Order($order_id);

            // Call PxOrder.Cancel2
            $params = array(
                'accountNumber' => '',
                'transactionNumber' => $payex_transaction_id
            );
            $result = $this->getPx()->Cancel2($params);
            if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
                $output = array(
                    'status' => 'error',
                    'message' => $this->getVerboseErrorMessage($result)
                );
                die(Tools::jsonEncode($output));
            }

            // Save Transaction
            $this->addTransaction($order->id, $result['transactionNumber'], $result['transactionStatus'], $result, isset($result['date']) ? strtotime($result['date']) : time());

            // Set Order Status
            $order->setCurrentState(Configuration::get('PS_OS_CANCELED'));

            $output = array(
                'status' => 'ok',
                'message' => $this->l('Order successfully canceled.')
            );
            die(Tools::jsonEncode($output));
        }

        // Refund Action
        if (Tools::isSubmit('process_refund') && isset($_POST['refund_amount']) && isset($_POST['payex_order_id']) && isset($_POST['payex_transaction_id'])) {
            //@todo Re-stock Items when Refund?
            $order_id = $_POST['payex_order_id'];
            $payex_transaction_id = $_POST['payex_transaction_id'];
            $refund_amount = (float)$_POST['refund_amount'];

            $order = new Order($order_id);
            $order_fields = $order->getFields();

            if ($refund_amount > $order_fields['total_paid'] || $refund_amount <= 0) {
                $output = array(
                    'status' => 'error',
                    'message' => $this->l('Wrong refund amount.')
                );
                die(Tools::jsonEncode($output));
            }

            // Call PxOrder.Credit5
            $params = array(
                'accountNumber' => '',
                'transactionNumber' => $payex_transaction_id,
                'amount' => round(100 * $refund_amount),
                'orderId' => $order_fields['reference'],
                'vatAmount' => round(100 * ($order_fields['total_paid_tax_incl'] - $order_fields['total_paid_tax_excl'])),
                'additionalValues' => ''
            );
            $result = $this->getPx()->Credit5($params);
            if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
                $output = array(
                    'status' => 'error',
                    'message' => $this->getVerboseErrorMessage($result)
                );
                die(Tools::jsonEncode($output));
            }

            // Save Transaction
            $this->addTransaction($order->id, $result['transactionNumber'], $result['transactionStatus'], $result, isset($result['date']) ? strtotime($result['date']) : time());

            // Set Order Status
            $order->setCurrentState(Configuration::get('PS_OS_REFUND'));

            $output = array(
                'status' => 'ok',
                'message' => $this->l('Order successfully refunded.')
            );
            die(Tools::jsonEncode($output));
        }
    }

    /**
     * Hook: AdminOrder details
     */
    public function hookAdminOrder($params)
    {
        $order_id = !empty($_GET['id_order']) ? (int)$_GET['id_order'] : 0;
        $order = new Order($order_id);

        /* Check if the order was paid with this Addon and display the Transaction details */
        if ($order->module === $this->name) {
            // Retrieve the transaction details
            // Get Last Transaction
            $transactions = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'payex_factoring_transactions WHERE order_id = ' . $order_id . ' ORDER BY date DESC;');

            // Select Transaction
            if (count($transactions) > 0) {
                $transaction = array_shift($transactions);
                $transaction_data = @unserialize($transaction['transaction_data']);

                // Transaction Fields
                $fields = array(
                    'PayEx Payment Method' => array('paymentMethod', 'cardProduct'),
                    'Masked Number' => array('maskedNumber', 'maskedCard'),
                    'Bank Hash' => array('BankHash', 'csId', 'panId'),
                    'Bank Reference' => array('bankReference'),
                    'Authenticated Status' => array('AuthenticatedStatus', 'authenticatedStatus'),
                    'Transaction Ref' => array('transactionRef'),
                    'PayEx Transaction Number' => array('transactionNumber'),
                    'PayEx Transaction Status' => array('transactionStatus'),
                    'Transaction Error Code' => array('transactionErrorCode'),
                    'Transaction Error Description' => array('transactionErrorDescription'),
                    'Transaction ThirdParty Error' => array('transactionThirdPartyError')
                );

                // Filter Transaction Data
                $result = array();
                foreach ($fields as $description => $list) {
                    foreach ($list as $key => $value) {
                        if (!empty($transaction_data[$value])) {
                            $result[$description] = $transaction_data[$value];
                            break;
                        }
                    }
                }

                $this->context->smarty->assign(array(
                    'order_id' => $order_id,
                    'order_amount' => $order->total_paid,
                    'transaction_id' => $transaction['transaction_id'],
                    'transaction_status' => (int)$transaction['transaction_status'],
                    'transaction_data' => $result
                ));

                return $this->display(__FILE__, 'views/templates/admin/admin-order.tpl');
            }
        }
    }

    /**
     * Save Transaction in PayEx Table
     * @param $order_id
     * @param $transaction_id
     * @param $transaction_status
     * @param $transaction_data
     * @param null $date
     */
    public function addTransaction($order_id, $transaction_id, $transaction_status, $transaction_data, $date = null)
    {
        if (is_null($date)) {
            $date = time();
        }

        // Save Transaction
        if (!Db::getInstance()->Execute(sprintf('INSERT INTO `' . _DB_PREFIX_ . 'payex_factoring_transactions` (order_id, transaction_id, transaction_status, transaction_data, date) VALUES (%s, %s, %s, "%s", "%s");',
            pSQL((int)$order_id), pSQL((int)$transaction_id), pSQL((int)$transaction_status), pSQL(serialize($transaction_data)), date('Y-m-d H:i:s', $date)))
        ) {
            die(Tools::displayError('Error when executing database query'));
        }
    }

    /**
     * Get Transaction
     * @param $transaction_id
     * @return mixed
     */
    public function getTransaction($transaction_id)
    {
        $transaction = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'payex_factoring_transactions WHERE transaction_id = ' . (int) $transaction_id . ';');
        if (count($transaction) > 0) {
            $transaction = array_shift($transaction);
        }

        return $transaction;
    }

    /**
     * Re-Stock Order Items
     * Use to cancel order
     * @param Order $order
     */
    public function reinjectQuantity(Order $order)
    {
        $products = $order->getProductsDetail();

        foreach ($products as $_key => $order_detail) {
            $tmp = (array)$order_detail;
            $order_detail = (object)$tmp;

            $qty_cancel_product = $order_detail->product_quantity;
            $reinjectable_quantity = (int)$order_detail->product_quantity - (int)$order_detail->product_quantity_reinjected;
            $quantity_to_reinject = $qty_cancel_product > $reinjectable_quantity ? $reinjectable_quantity : $qty_cancel_product;

            if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && $order_detail->advanced_stock_management && $order_detail->id_warehouse != 0) {
                $manager = StockManagerFactory::getManager();
                $movements = StockMvt::getNegativeStockMvts(
                    $order_detail->id_order,
                    $order_detail->product_id,
                    $order_detail->product_attribute_id,
                    $quantity_to_reinject
                );
                $left_to_reinject = $quantity_to_reinject;
                foreach ($movements as $movement) {
                    if ($left_to_reinject > $movement['physical_quantity']) {
                        $quantity_to_reinject = $movement['physical_quantity'];
                    }
                    $left_to_reinject -= $quantity_to_reinject;

                    $manager->addProduct(
                        $order_detail->product_id,
                        $order_detail->product_attribute_id,
                        new Warehouse($movement['id_warehouse']),
                        $quantity_to_reinject,
                        null,
                        $movement['price_te'],
                        true
                    );
                }
                StockAvailable::synchronize($order_detail->product_id);
            } elseif ($order_detail->id_warehouse == 0) {
                StockAvailable::updateQuantity(
                    $order_detail->product_id,
                    $order_detail->product_attribute_id,
                    $quantity_to_reinject,
                    $order_detail->id_shop
                );
            } else {
                // This product cannot be re-stocked.
            }
        }
    }

    /**
     * Get Locale for PayEx
     * @param array $lang
     * @return string
     */
    public function getLocale(array $lang)
    {
        $allowed_langs = array(
            'en' => 'en-US',
            'sv' => 'sv-SE',
            'nb' => 'nb-NO',
            'da' => 'da-DK',
            'es' => 'es-ES',
            'de' => 'de-DE',
            'fi' => 'fi-FI',
            'fr' => 'fr-FR',
            'pl' => 'pl-PL',
            'cs' => 'cs-CZ',
            'hu' => 'hu-HU'
        );

        $locale = strtolower(isset($lang['iso_code']) ? $lang['iso_code'] : 'en');

        if (isset($allowed_langs[$locale])) {
            return $allowed_langs[$locale];
        }

        return 'en-US';
    }

    /**
     * Get Invoice Extra Print Blocks XML
     * @see http://www.payexpim.com/extended-functionality/invoice-extra-print-blocks-invoice-2-0/
     * @param Order $order
     * @return string
     */
    public function getInvoiceExtraPrintBlocksXML(Order $order)
    {
        // Calculate total amount to prevent rounding issue
        $total_paid = 0;
        $order_details = $order->getOrderDetailList();
        foreach ($order_details as $order_detail) {
            $taxAmount = $order_detail['total_price_tax_incl'] - $order_detail['total_price_tax_excl'];
            $taxPercent = ($taxAmount > 0) ? round(100 / ($order_detail['total_price_tax_excl'] / $taxAmount)) : 0;
            $cost = $order_detail['unit_price_tax_excl'] * $order_detail['product_quantity'];
            $total_paid += $cost + ($order_detail['unit_price_tax_excl'] / 100 * $taxPercent);
        }
        if ((float)$order->total_shipping_tax_excl > 0) {
            $total_paid += $order->total_shipping_tax_excl + ($order->total_shipping_tax_excl /100 * $order->carrier_tax_rate);
        }
        $total_paid_adj = $order->total_paid - $total_paid;

        $dom = new DOMDocument('1.0', 'utf-8');
        $OnlineInvoice = $dom->createElement('OnlineInvoice');
        $dom->appendChild($OnlineInvoice);
        $OnlineInvoice->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $OnlineInvoice->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsd', 'http://www.w3.org/2001/XMLSchema');

        $OrderLines = $dom->createElement('OrderLines');
        $OnlineInvoice->appendChild($OrderLines);

        $order_details = $order->getOrderDetailList();
        foreach ($order_details as $order_detail) {
            $taxAmount = $order_detail['total_price_tax_incl'] - $order_detail['total_price_tax_excl'];
            $taxPercent = ($taxAmount > 0) ? round(100 / ($order_detail['total_price_tax_excl'] / $taxAmount)) : 0;

            // Apply total paid adjustment to unit price
            if (abs($total_paid_adj) !== 0) {
                $tmp = $order_detail['unit_price_tax_excl'] + $total_paid_adj;
                if (Tools::ps_round($taxAmount, 2) === Tools::ps_round($tmp / 100 * $taxPercent, 2))
                {
                    $order_detail['unit_price_tax_excl'] = Tools::ps_round($tmp, 2);
                    $total_paid_adj = 0;
                }
            }

            $OrderLine = $dom->createElement('OrderLine');
            $OrderLine->appendChild($dom->createElement('Product', $order_detail['product_name']));
            $OrderLine->appendChild($dom->createElement('Qty', $order_detail['product_quantity']));
            $OrderLine->appendChild($dom->createElement('UnitPrice', $order_detail['unit_price_tax_excl']));
            $OrderLine->appendChild($dom->createElement('VatRate', $taxPercent));
            $OrderLine->appendChild($dom->createElement('VatAmount', $taxAmount));
            $OrderLine->appendChild($dom->createElement('Amount', $order_detail['total_price_tax_incl']));
            $OrderLines->appendChild($OrderLine);
        }

        // Add Shipping Line
        if ((float)$order->total_shipping_tax_incl > 0) {
            $carrier = new Carrier((int)$order->id_carrier);
            $OrderLine = $dom->createElement('OrderLine');
            $OrderLine->appendChild($dom->createElement('Product', $carrier->name));
            $OrderLine->appendChild($dom->createElement('Qty', 1));
            $OrderLine->appendChild($dom->createElement('UnitPrice', $order->total_shipping_tax_excl));
            $OrderLine->appendChild($dom->createElement('VatRate', $order->carrier_tax_rate));
            $OrderLine->appendChild($dom->createElement('VatAmount', $order->total_shipping_tax_incl - $order->total_shipping_tax_excl));
            $OrderLine->appendChild($dom->createElement('Amount', $order->total_shipping_tax_incl));
            $OrderLines->appendChild($OrderLine);
        }

        return str_replace("\n", '', $dom->saveXML());
    }

    /**
     * Save message to Log
     * @param $message
     * @return mixed
     */
    public function log($message)
    {
        if (!$this->_log) {
            $this->_log = new FileLogger();
            $this->_log->setFilename(_PS_ROOT_DIR_ . '/log/payex_payment.log');
        }

        return $this->_log->logDebug($message);
    }

    /**
     * Get verbose error message by Error Code
     * @param $errorCode
     * @return string | false
     */
    public function getErrorMessageByCode($errorCode)
    {
        $errorMessages = array(
            'REJECTED_BY_ACQUIRER' => $this->l('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            //'Error_Generic' => $this->l('An unhandled exception occurred'),
            '3DSecureDirectoryServerError' => $this->l('A problem with Visa or MasterCards directory server, that communicates transactions for 3D-Secure verification'),
            'AcquirerComunicationError' => $this->l('Communication error with the acquiring bank'),
            'AmountNotEqualOrderLinesTotal' => $this->l('The sum of your order lines is not equal to the price set in initialize'),
            'CardNotEligible' => $this->l('Your customers card is not eligible for this kind of purchase, your customer can contact their bank for more information'),
            'CreditCard_Error' => $this->l('Some problem occurred with the credit card, your customer can contact their bank for more information'),
            'PaymentRefusedByFinancialInstitution' => $this->l('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            'Merchant_InvalidAccountNumber' => $this->l('The merchant account number sent in on request is invalid'),
            'Merchant_InvalidIpAddress' => $this->l('The IP address the request comes from is not registered in PayEx, you can set it up in PayEx Admin under Merchant profile'),
            'Access_MissingAccessProperties' => $this->l('The merchant does not have access to requested functionality'),
            'Access_DuplicateRequest' => $this->l('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            'Admin_AccountTerminated' => $this->l('The merchant account is not active'),
            'Admin_AccountDisabled' => $this->l('The merchant account is not active'),
            'ValidationError_AccountLockedOut' => $this->l('The merchant account is locked out'),
            'ValidationError_Generic' => $this->l('Generic validation error'),
            'ValidationError_HashNotValid' => $this->l('The hash on request is not valid, this might be due to the encryption key being incorrect'),
            //'ValidationError_InvalidParameter' => $this->l('One of the input parameters has invalid data. See paramName and description for more information'),
            'OperationCancelledbyCustomer' => $this->l('The operation was cancelled by the client'),
            'PaymentDeclinedDoToUnspecifiedErr' => $this->l('Unexpecter error at 3rd party'),
            'InvalidAmount' => $this->l('The amount is not valid for this operation'),
            'NoRecordFound' => $this->l('No data found'),
            'OperationNotAllowed' => $this->l('The operation is not allowed, transaction is in invalid state'),
            'ACQUIRER_HOST_OFFLINE' => $this->l('Could not get in touch with the card issuer'),
            'ARCOT_MERCHANT_PLUGIN_ERROR' => $this->l('The card could not be verified'),
            'REJECTED_BY_ACQUIRER_CARD_BLACKLISTED' => $this->l('There is a problem with this card'),
            'REJECTED_BY_ACQUIRER_CARD_EXPIRED' => $this->l('The card expired'),
            'REJECTED_BY_ACQUIRER_INSUFFICIENT_FUNDS' => $this->l('Insufficient funds'),
            'REJECTED_BY_ACQUIRER_INVALID_AMOUNT' => $this->l('Incorrect amount'),
            'USER_CANCELED' => $this->l('Payment cancelled'),
            'CardNotAcceptedForThisPurchase' => $this->l('Your Credit Card not accepted for this purchase')
        );
        $errorMessages = array_change_key_case($errorMessages, CASE_UPPER);

        $errorCode = mb_strtoupper($errorCode);
        return isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : false;
    }

    /**
     * Get Verbose Error Message
     * @param array $details
     * @return string
     */
    public function getVerboseErrorMessage(array $details)
    {
        $errorCode = isset($details['transactionErrorCode']) ? $details['transactionErrorCode'] : $details['errorCode'];
        $errorMessage = $this->getErrorMessageByCode($errorCode);
        if ($errorMessage) {
            return $errorMessage;
        }

        $errorCode = isset($details['transactionErrorCode']) ? $details['transactionErrorCode'] : '' ;
        $errorDescription = isset($details['transactionThirdPartyError']) ? $details['transactionThirdPartyError'] : '';
        if (empty($errorCode) && empty($errorDescription)) {
            $errorCode = $details['code'];
            $errorDescription = $details['description'];
        }
        return sprintf($this->l('PayEx error: %s'), $errorCode . ' (' . $errorDescription . ')');
    }

    /**
     * Check Product In Cart
     * @param $cart
     * @param $id
     * @return bool
     */
    public function isInCart($cart, $id)
    {
        foreach ($cart->getProducts() as $product) {
            if ($product['id_product'] == $id) {
                return true;
            }
        }

        return false;
    }
}
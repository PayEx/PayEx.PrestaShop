<?php
/**
 * @package    Wywallet
 * @author    aait.se
 * @copyright Copyright (C) AAIT - All rights reserved.
 * @license  http://shop.aait.se/license.txt EULA
 */

/**
 * @since 1.5.0
 */
class WywalletTransactionModuleFrontController extends ModuleFrontController
{
    /** @var array PayEx TC Spider IPs */
    static protected $_allowed_ips = array(
        '82.115.146.170', // Production
        '82.115.146.10' // Test
    );

    protected $_log;

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if (!$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check is PayEx Request
        if (!in_array(Tools::getRemoteAddr(), self::$_allowed_ips)) {
            $this->log('TC: Access denied for this request. It\'s not PayEx Spider.');
            header(sprintf('%s %s %s', 'HTTP/1.1', '403', 'Access denied. Accept PayEx Transaction Callback only.'), true, '403');
            header(sprintf('Status: %s %s', '403', 'Access denied. Accept PayEx Transaction Callback only.'), true, '403');
            exit('Error: Access denied. Accept PayEx Transaction Callback only. ');
        }

        // Check Post Fields
        $this->log('TC: Requested Params: ' . var_export($_POST, true));
        if (count($_POST) == 0 || empty($_POST['transactionNumber'])) {
            $this->log('TC: Error: Empty request received.');
            header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
            header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
            exit('FAILURE');
        }

        // Get Transaction Details
        $transactionId = $_POST['transactionNumber'];

        // Call PxOrder.GetTransactionDetails2
        $params = array(
            'accountNumber' => '',
            'transactionNumber' => $transactionId
        );
        $details = $this->module->getPx()->GetTransactionDetails2($params);
        if ($details['code'] !== 'OK' || $details['description'] !== 'OK' || $details['errorCode'] !== 'OK') {
            die(Tools::displayError($this->module->getVerboseErrorMessage($details)));
        }

        $order_id = $details['orderId'];
        $transactionStatus = (int)$details['transactionStatus'];

        $this->log('TC: Incoming transaction: ' . $transactionId);
        $this->log('TC: Transaction Status: ' . $transactionStatus);
        $this->log('TC: OrderId: ' . $order_id);

        // Load order
        $order = new Order($order_id);

        // Check orderID in Store
        if ($order->module !== 'wywallet') {
            $this->log('TC: OrderID ' . $order_id . ' not found on store.');
            header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
            header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
            exit('FAILURE');
        }

        /* 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        switch ($transactionStatus) {
            case 0;
            case 3:
                // Complete order
                $params = array(
                    'accountNumber' => '',
                    'orderRef' => $_POST['orderRef']
                );
                $result = $this->module->getPx()->Complete($params);
                if ($result['errorCodeSimple'] !== 'OK') {
                    die(Tools::displayError($this->module->getVerboseErrorMessage($result)));
                }

                // Save Transaction
                $this->module->addTransaction($order->id, $result['transactionNumber'], $result['transactionStatus'], $result, isset($result['date']) ? strtotime($result['date']) : time());

                switch ((int)$result['transactionStatus']) {
                    case 0:
                    case 6:
                        $order->setCurrentState(Configuration::get('PS_OS_PAYEX_CAPTURED'));
                        $order->setInvoice(true);
                        $invoice = !empty($order->invoice_number) ? new OrderInvoice($order->invoice_number) : null;
                        $order->addOrderPayment($order->total_paid, $order->payment, $result['transactionNumber'], null, date('Y-m-d H:i:s', isset($result['date']) ? strtotime($result['date']) : time()), $invoice);
                        break;
                    case 3:
                        $order->setCurrentState(Configuration::get('PS_OS_PAYEX_AUTH'));
                        break;
                    case 4:
                        // Cancel
                        $order->setCurrentState(Configuration::get('PS_OS_CANCELED'));
                        break;
                    case 5:
                    default:
                        // Cancel when Errors
                        $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                        break;
                }

                $this->log('TC: OrderId ' . $order_id . ' Complete with TransactionStatus ' . $result['transactionStatus'], $order_id);
                break;
            case 2:
                // Refund
                // Save Transaction
                $this->module->addTransaction($order->id, $transactionId, $details['transactionStatus'], $details, isset($details['orderCreated']) ? strtotime($details['orderCreated']) : time());

                // Set Order Status
                //@todo Re-stock Items when Refund?
                $order->setCurrentState(Configuration::get('PS_OS_REFUND'));

                $this->log('TC: OrderId ' . $order_id . ' refunded', $order_id);
                break;
            case 4;
                // Cancel
                // Save Transaction
                $this->module->addTransaction($order->id, $transactionId, $details['transactionStatus'], $details, isset($details['orderCreated']) ? strtotime($details['orderCreated']) : time());

                // Set Order Status
                $order->setCurrentState(Configuration::get('PS_OS_CANCELED'));

                $this->log('TC: OrderId ' . $order_id . ' canceled', $order_id);
                break;
            case 5:
                 // Cancel when Errors
                // Save Transaction
                $this->module->addTransaction($order->id, $transactionId, $details['transactionStatus'], $details, isset($details['orderCreated']) ? strtotime($details['orderCreated']) : time());

                // Set Order Status
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));

                $this->log('TC: OrderId ' . $order_id . ' canceled', $order_id);
                break;
            case 6:
                // Set Order Status to captured
                // Save Transaction
                $this->module->addTransaction($order->id, $transactionId, $details['transactionStatus'], $details, isset($details['orderCreated']) ? strtotime($details['orderCreated']) : time());

                $order->setCurrentState(Configuration::get('PS_OS_PAYEX_CAPTURED'));
                $order->setInvoice(true);
                $invoice = !empty($order->invoice_number) ? new OrderInvoice($order->invoice_number) : null;
                $order->addOrderPayment($order->total_paid, $order->payment, $transactionId, null, date('Y-m-d H:i:s', isset($details['orderCreated']) ? strtotime($details['orderCreated']) : time()), $invoice);

                $this->log('TC: OrderId ' . $order_id . ' captured', $order_id);
                break;
            default:
                $this->log('TC: Unknown Transaction Status', $order_id);
                header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
                header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
                exit('FAILURE');
        }

        // Show "OK"
        $this->log('TC: Done.');
        header(sprintf('%s %s %s', 'HTTP/1.1', '200', 'OK'), true, '200');
        header(sprintf('Status: %s %s', '200', 'OK'), true, '200');
        exit('OK');
    }

    /**
     * Save message to Log
     * @param $message
     * @param null $order_id
     * @return mixed
     */
    public function log($message, $order_id = null)
    {
        if (!$this->_log) {
            $this->_log = new FileLogger();
            $this->_log->setFilename(_PS_ROOT_DIR_ . '/log/wywallet_tc.log');
        }

        if ($order_id) {
            $message .= ' OrderId: ' . $order_id;
        }

        return $this->_log->logDebug($message);
    }
}

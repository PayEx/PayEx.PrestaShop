<?php
/**
 * PayEx API
 * @see http://www.payexpim.com/technical-reference/
 * Created by AAIT Team.
 */
require_once realpath(dirname(__FILE__) . '/Exception.php');
require_once realpath(dirname(__FILE__) . '/Soap.php');

class Px_Px
{
    /** @var bool PayEx Debug mode */
    protected $_debug_mode = true;

    /** @var string PayEx Account Number */
    protected $_account_number = '';

    /** @var string Encryption Key */
    protected $_encryption_key = '';

    /** @see http://www.payexpim.com/technical-reference/wsdl/wsdl-files/ */
    /** @var array WSDL Files */
    protected static $_wdsl = array(
        'PxOrderWSDL' => '',
        'PxVerificationWSDL' => '',
        'PxAgreementWDSL' => '',
        'PxRecurringWDSL' => '',
        'PxConfinedWSDL' => ''
    );

    /** @var array PayEx SOAP API List */
    protected static $_rules = array(
        /** @see http://www.payexpim.com/category/pxorder/ */
        'PxOrderWSDL' => array(
            'AddOrderAddress2', 'AddSingleOrderLine2', 'AuthorizeEVC', 'AuthorizeGC', 'AuthorizeInvoice',
            'AuthorizeInvoiceLedger', 'Cancel2', 'Capture4', 'Capture5', 'Check2', 'Complete', 'Credit4', 'Credit5', 'CreditOrderLine3',
            'GetLowestMonthlyInvoiceSaleAmount', 'GetTransactionDetails2', 'Initialize7', 'Initialize8', 'InvoiceLinkGet',
            'PrepareAuthorizeDD', 'PrepareSaleDD2', 'PurchaseActivate', 'PurchaseInvoiceCorporate', 'PurchaseInvoicePartPaymentSale',
            'PurchaseInvoicePrivate', 'PurchaseInvoiceSale', 'PurchaseOTT', 'PurchasePX', 'ReserveIVR', 'SaleEVC',
            'SaleInvoiceLedger', 'SaleIVR'
        ),
        /** @see http://www.payexpim.com/category/pxverification/ */
        'PxVerificationWSDL' => array(
            'CreditCheckCorporate2', 'CreditCheckPrivate2', 'GetConsumerLegalAddress', 'NameCheckPrivate',
            'NameCheckPrivate'
        ),
        /** @see http://www.payexpim.com/category/pxagreement/ */
        'PxAgreementWDSL' => array(
            'ActivatePxAgreement', 'AgreementCheck', 'AutoPay2', 'AutoPay3', 'CreateAgreement3', 'DeleteAgreement'
        ),
        /** @see http://www.payexpim.com/category/pxagreement/ */
        'PxRecurringWDSL' => array(
            'Check', 'Start', 'Stop'
        ),
        /** @see http://www.payexpim.com/category/pxconfined/ */
        'PxConfinedWSDL' => array(
            'PreparePurchaseCC', 'PurchaseCC'
        )
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        // Init Libraries
        set_include_path(implode(PATH_SEPARATOR, array(
            realpath(dirname(__FILE__)),
            get_include_path(),
        )));
    }

    /**
     * Get Library Version
     * @return string
     */
    public function getVersion()
    {
        return '1.0.5';
    }

    /**
     * Set PayEx Environment
     * @param string $account
     * @param string $key
     * @param bool $debug
     */
    public function setEnvironment($account, $key, $debug = true)
    {
        $this->_account_number = $account;
        $this->_encryption_key = $key;
        $this->_debug_mode = $debug;

        // Init WDSL
        $this->initWSDL($this->_debug_mode);
    }

    /**
     * Init WDSL Values
     * @param bool $debug_mode
     */
    public function initWSDL($debug_mode)
    {
        if ($debug_mode === false) {
            /* This is for PROD environment, remote wsdl files from PayEx */
            self::$_wdsl['PxOrderWSDL'] = 'https://external.payex.com/pxorder/pxorder.asmx?wsdl';
            self::$_wdsl['PxConfinedWSDL'] = 'https://confined.payex.com/PxConfined/pxorder.asmx?wsdl';
            self::$_wdsl['PxVerificationWSDL'] = 'https://external.payex.com/pxverification/pxverification.asmx?wsdl';
            self::$_wdsl['PxAgreementWDSL'] = 'https://external.payex.com/pxagreement/pxagreement.asmx?WSDL';
            self::$_wdsl['PxRecurringWDSL'] = 'https://external.payex.com/pxagreement/pxrecurring.asmx?WSDL';
        } else {
            /* This is for TEST environment, remote wsdl files from PayEx */
            self::$_wdsl['PxOrderWSDL'] = 'https://test-external.payex.com/pxorder/pxorder.asmx?wsdl';
            self::$_wdsl['PxConfinedWSDL'] = 'https://test-confined.payex.com/PxConfined/pxorder.asmx?wsdl';
            self::$_wdsl['PxVerificationWSDL'] = 'https://test-external.payex.com/PxVerification/pxverification.asmx?wsdl';
            self::$_wdsl['PxAgreementWDSL'] = 'https://test-external.payex.com/pxagreement/pxagreement.asmx?WSDL';
            self::$_wdsl['PxRecurringWDSL'] = 'https://test-external.payex.com/pxagreement/pxrecurring.asmx?WSDL';
        }
    }

    /**
     * Get WDSL File
     * @param $px_function
     * @return bool
     */
    public function getWDSL($px_function)
    {
        foreach (self::$_rules as $wdsl_type => $function_list) {
            if (in_array($px_function, $function_list)) {
                return self::$_wdsl[$wdsl_type];
            }
        }
        return false;
    }

    /**
     * Parse PayEx XML Response
     * @param $xml_body
     * @return array|bool
     */
    public function parseFields($xml_body)
    {
        // Load XML
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $status = @$doc->loadXML($xml_body);
        if ($status === false) {
            return false;
        }

        $result = array();
        $black_listed = array('header', 'id', 'status');
        $items = $doc->getElementsByTagName('payex')->item(0)->getElementsByTagName('*');
        foreach ($items as $item) {
            $key = $item->nodeName;
            $value = $item->nodeValue;
            if (!in_array($key, $black_listed)) {
                $result[$key] = $value;
            }
        }

        // Get Status codes for corrected result. It' s bugfix.
        $items = $doc->getElementsByTagName('payex')->item(0)->getElementsByTagName('status')->item(0)->getElementsByTagName('*');
        foreach ($items as $item) {
            $key = $item->nodeName;
            $value = $item->nodeValue;
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Magic Method: Call PayEx Function
     * @param $px_function
     * @param $arguments
     * @return array|bool
     * @throws Exception
     */
    public function __call($px_function, $arguments)
    {
        if (empty($this->_account_number) || empty($this->_encryption_key)) {
            throw new Px_Exception('Account number or Encryption key not defined. Use setEnvironment().');
        }

        $wdsl = $this->getWDSL($px_function);
        if ($wdsl === false || empty($wdsl)) {
            throw new Px_Exception('Unknown PayEx Method.');
        }

        if (!isset($arguments[0]) || !is_array($arguments[0])) {
            throw new Px_Exception('Invalid PayEx Method params.');
        }

        // Automatically set Account Number param
        if (isset($arguments[0]['accountNumber']) && empty($arguments[0]['accountNumber'])) {
            $arguments[0]['accountNumber'] = $this->_account_number;
        }

        // AgreementCheck used as "Check"
        if ($px_function === 'AgreementCheck') {
            $px_function = 'Check';
        }

        // Add Hash to Params
        $arguments[0]['hash'] = $this->getHash($arguments[0]);

        // Call PayEx Method
        $px = Px_Soap::getClient($wdsl);
        try {
            //$result = $px->__soapCall($px_function, $arguments);
            $result = $px->__call($px_function, $arguments);
            if (property_exists($result, $px_function . 'Result') === false) {
                throw new Px_Exception('Invalid PayEx Response.');
            }
            $result = $result->{$px_function . 'Result'};
            $result = $this->parseFields($result);
            if ($result === false) {
                throw new Px_Exception('Failed to parse PayEx Response.');
            }
            return $result;
        } catch (Exception $e) {
            throw new Px_Exception($e->getMessage());
        }

    }

    /**
     * Get Hash Params
     *
     * Hexadecimal md5 hash built up by the value of the following parameters (for Initialize7):
     * accountNumber + purchaseOperation + price + priceArgList + currency + vat + orderID +
     * productNumber + description + clientIPAddress + clientIdentifier + additionalValues +
     * externalID + returnUrl + view + agreementRef + cancelUrl + clientLanguage
     *
     * All parameters are added together – the ‘plus’ character is not included.
     * In addition the encryption key must be included at the end of the string before performing the md5-hash.
     * @param array $params
     * @return string
     */
    public function getHash($params)
    {
        $params = trim(implode('', $params));
        return strtoupper(md5($params . $this->_encryption_key));
    }

}
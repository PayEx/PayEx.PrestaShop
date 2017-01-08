<?php
/**
* AAIT
*
*  @author    aait.se
*  @package   PayEx
*  @copyright Copyright (C) AAIT - All rights reserved.
*  @license   http://shop.aait.se/license.txt  EULA
*/

require_once dirname(__FILE__) . '/../../library/parser.php';

/**
 * @since 1.5.0
 */
class SsnAddressModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $ssn = trim(Tools::getValue('ssn'));
        if (empty($ssn)) {
            $output = array(
                'success' => false,
                'message' => $this->module->l('Social security number is empty')
            );
            die(Tools::jsonEncode($output));
        }

        //$ssn = preg_replace('/[^0-9]/s', '', $ssn);

        // Get Country Code
        //$country_code = $this->getCountryCodeBySSN($ssn);
        //if (!$country_code) {
        //    $output = array(
        //        'success' => false,
        //        'message' => $this->module->l('Invalid Social Security Number')
        //    );
        //    die(Tools::jsonEncode($output));
        //}

        $country_code = trim(Tools::getValue('country_code'));
        if (empty($country_code)) {
            $output = array(
                'success' => false,
                'message' => $this->module->l('Please enter country')
            );
            die(Tools::jsonEncode($output));
        }

        if (!in_array($country_code, array('SE', 'NO'))) {
            $output = array(
                'success' => false,
                'message' => $this->module->l('This country don\'t supported')
            );
            die(Tools::jsonEncode($output));
        }

        $postcode = trim(Tools::getValue('postcode'));
        if (empty($postcode)) {
            $output = array(
                'success' => false,
                'message' => $this->module->l('Please enter postcode')
            );
            die(Tools::jsonEncode($output));
        }

        // Call PxOrder.GetAddressByPaymentMethod
        $params = array(
            'accountNumber' => '',
            'paymentMethod' => 'PXFINANCINGINVOICE' . $country_code,
            'ssn' => $ssn,
            'zipcode' => $postcode,
            'countryCode' => $country_code,
            'ipAddress' => $_SERVER['REMOTE_ADDR']
        );
        $result = $this->module->getPx()->GetAddressByPaymentMethod($params);
        if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
            $output = array(
                'success' => false,
                'message' => $result['errorCode'] . '(' . $result['description'] . ')'
            );
            die(Tools::jsonEncode($output));
        }

        // Parse name field
        $parser = new FullNameParser();
        $name = $parser->parse_name($result['name']);

        $output = array(
            'success' => true,
            'first_name' => $name['fname'],
            'last_name' => $name['lname'],
            'address_1' => $result['streetAddress'],
            'address_2' => ! empty($result['coAddress']) ? 'c/o' . $result['coAddress'] : '',
            'postcode' => $result['zipCode'],
            'city' => $result['city'],
            'country' => $result['countryCode'],
            'country_id' => Country::getByIso($result['countryCode'])
        );
        die(Tools::jsonEncode($output));
    }

    /**
     * Get Country Code by SSN
     * @param $ssn
     *
     * @return string|bool
     */
    protected function getCountryCodeBySSN($ssn) {
        $rules = array(
            'NO' => '/^[0-9]{6,6}((-[0-9]{5,5})|([0-9]{2,2}((-[0-9]{5,5})|([0-9]{1,1})|([0-9]{3,3})|([0-9]{5,5))))$/',
            'SE' => '/^[0-9]{6,6}(([0-9]{2,2}[-\+]{1,1}[0-9]{4,4})|([-\+]{1,1}[0-9]{4,4})|([0-9]{4,6}))$/',
            //'FI' => '/^[0-9]{6,6}(([A\+-]{1,1}[0-9]{3,3}[0-9A-FHJK-NPR-Y]{1,1})|([0-9]{3,3}[0-9A-FHJK-NPR-Y]{1,1})|([0-9]{1,1}-{0,1}[0-9A-FHJK-NPR-Y]{1,1}))$/i',
            //'DK' => '/^[0-9]{8,8}([0-9]{2,2})?$/',
            //'NL' => '/^[0-9]{7,9}$/'
        );

        foreach ($rules as $country_code => $pattern) {
            if ((bool)preg_match($pattern, $ssn)) {
                return $country_code;
            }
        }

        return false;
    }
}
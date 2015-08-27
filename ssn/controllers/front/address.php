<?php

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
        $ssn = Tools::getValue('ssn');
        if (empty($ssn)) {
            $output = array(
                'success' => false,
                'message' => $this->module->l('Social security number is empty')
            );
            die(Tools::jsonEncode($output));
        }

        // Call PxVerification.GetConsumerLegalAddress
        $params = array(
            'accountNumber' => '',
            'countryCode' => 'SE', // Supported only "SE"
            'socialSecurityNumber' => $ssn
        );
        $result = $this->module->getPx()->GetConsumerLegalAddress($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            if (preg_match('/\bInvalid parameter:SocialSecurityNumber\b/i', $result['description'])) {
                $output = array(
                    'success' => false,
                    'message' => $this->module->l('Invalid Social Security Number')
                );
                die(Tools::jsonEncode($output));
            }

            $output = array(
                'success' => false,
                'message' => $result['errorCode'] . '(' . $result['description'] . ')'
            );
            die(Tools::jsonEncode($output));
        }

        $output = array(
            'success' => true,
            'first_name' => $result['firstName'],
            'last_name' => $result['lastName'],
            'address_1' => $result['address1'],
            'address_2' => $result['address2'],
            'postcode' => $result['postNumber'],
            'city' => $result['city'],
            'country' => $result['country']
        );
        die(Tools::jsonEncode($output));
    }
}
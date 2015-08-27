<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Ssn extends Module
{
    private $_html = '';
    private $_postErrors = array();

    protected $_px = '';

    public $accountnumber;
    public $encryptionkey;
    public $mode;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'ssn';
        $this->tab = 'billing_invoicing';
        $this->version = 1.0;
        $this->author = 'AAIT';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Social Security Number');
        $this->description = $this->l('Get Address using Social Security Number.');

        // Init Configuration
        $config = Configuration::getMultiple(array('PX_SSN_ACCOUNT_NUMBER', 'PX_SSN_ENCRYPTION_KEY', 'PX_SSN_TESTMODE'));
        $this->accountnumber = isset($config['PX_SSN_ACCOUNT_NUMBER']) ? $config['PX_SSN_ACCOUNT_NUMBER'] : '';
        $this->encryptionkey = isset($config['PX_SSN_ENCRYPTION_KEY']) ? $config['PX_SSN_ENCRYPTION_KEY'] : '';
        $this->mode = isset($config['PX_SSN_TESTMODE']) ? $config['PX_SSN_TESTMODE'] : 1;

        // Init PayEx
        $this->getPx()->setEnvironment($this->accountnumber, $this->encryptionkey, (bool)$this->mode);
    }

    /**
     * Install
     * @return bool
     */
    public function install()
    {
        if (!parent::install() || !$this->registerHook('header')) {
            return false;
        }

        // Set Payment Settings
        Configuration::updateValue('PX_SSN_ACCOUNT_NUMBER', '');
        Configuration::updateValue('PX_SSN_ENCRYPTION_KEY', '');
        Configuration::updateValue('PX_SSN_TESTMODE', 1);

        return true;
    }

    /**
     * Uninstall
     * @return mixed
     */
    public function uninstall()
    {
        /* Clean configuration table */
        Configuration::deleteByName('PX_SSN_ACCOUNT_NUMBER');
        Configuration::deleteByName('PX_SSN_ENCRYPTION_KEY');
        Configuration::deleteByName('PX_SSN_TESTMODE');

        return parent::uninstall();
    }

    /**
     * Get Content
     * @return string
     */
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
        }

        $this->_displayForm();

        return $this->_html;
    }

    /**
     * Configuration Form
     */
    private function _displayForm()
    {
        $this->_html .=
            '<form action="' . Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']) . '" method="post">
			<fieldset>
			<legend><img src="../img/admin/contact.gif" />' . $this->displayName . '</legend>
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
					<tr><td colspan="2" align="center"><input class="button" name="btnSubmit" value="' . $this->l('Update settings') . '" type="submit" /></td></tr>
				</table>
			</fieldset>
		</form>';
    }

    /**
     * Form Validation
     */
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

    /**
     * Form Process
     */
    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('PX_SSN_ACCOUNT_NUMBER', Tools::getValue('accountnumber'));
            Configuration::updateValue('PX_SSN_ENCRYPTION_KEY', Tools::getValue('encryptionkey'));
            Configuration::updateValue('PX_SSN_TESTMODE', Tools::getValue('mode'));
        }

        $this->_html .= '<div class="conf confirm"> ' . $this->l('Settings updated') . '</div>';
    }

    /**
     * Header Hook
     * @param $params
     */
    public function hookHeader($params)
    {
        $this->context->controller->addJS(($this->_path) . 'js/ssn.js');
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
}
<?php
/*
* 2007-2025 PrestaShop
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class giladx_hyp extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public function __construct()
    {
        $this->name = 'giladx_hyp';
        $this->tab = 'payments_gateways';
        $this->version = '4.1.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => '8.2.0'); // Updated for 8.2.x compatibility
        $this->author = 'Gilad Levi';
        $this->controllers = array('validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Yaad Sarig - Hyp Payment Gateway');
        $this->description = $this->l('Yaad Sarig - Hyp Payment Gateway');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        return parent::install() && 
               $this->registerHook('paymentOptions') && 
               $this->registerHook('paymentReturn');
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart'])) {
            return [];
        }

        return [
            $this->getExternalPaymentOption($params),
        ];
    }

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

    public function getExternalPaymentOption($params)
    {
        $billing_address = new Address((int)$this->context->cart->id_address_invoice);
        $billing_address->country = new Country((int)$billing_address->id_country);
        $billing_address->state = new State((int)$billing_address->id_state);

        // Language mapping
        $langForHYP = array('en' => 'ENG', 'he' => 'HEB', 'default' => 'HEB');
        $langCurrent = $this->context->language->iso_code;
        $varLangToSend = $langForHYP[$langCurrent] ?? $langForHYP['default'];

        // Currency mapping
        $currencyForHYP = array('ILS' => 1, 'NIS' => 1, 'USD' => 2, 'EUR' => 3, 'default' => 1);
        $currencyCurrent = $this->context->currency->iso_code;
        $varCurToSend = $currencyForHYP[$currencyCurrent] ?? $currencyForHYP['default'];

        // Cart total
        $cart = $params['cart'];
        $total = (float)($cart->getOrderTotal(true, Cart::BOTH));

        $Tash = Configuration::get('GILADX_HYP_SPLIT');
        $Postpone = Configuration::get('GILADX_HYP_POSTPONE', false) ? 'True' : 'False';
        $Pritim = Configuration::get('GILADX_HYP_PRITIM', false) ? 'True' : 'False';
        $tmp = Configuration::get('GILADX_HYP_TMP');
        $username = Configuration::get('GILADX_HYP_USERNAME');
        $password = Configuration::get('GILADX_HYP_PASSWORD_FIELD');

        $itemArray = '';
        foreach ($cart->getProducts() as $cartProductsVal) {
            $itemArray .= "[" . $cartProductsVal['id_product'] . "~" . $cartProductsVal['name'] . "~" . $cartProductsVal['cart_quantity'] . "~" . $cartProductsVal['price'] . "]";
        }

        $orderTotalShipping = $cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
        if ($orderTotalShipping > 0) {
            $itemArray .= "[0 ~ Shipping ~ 1 ~" . $orderTotalShipping . "]";
        }

        $orderTotalDiscount = $cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
        if ($orderTotalDiscount > 0) {
            $itemArray .= "[0~Discount~-1~" . $orderTotalDiscount . "]";
        }

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($this->l('Credit card payment'))
                       ->setAction('https://icom.yaad.net/p/')
                       ->setInputs([
                           'token' => [
                               'name' => 'action',
                               'type' => 'hidden',
                               'value' => 'pay',
                           ],
                           [
                               'name' => 'Masof',
                               'type' => 'hidden',
                               'value' => Configuration::get('GILADX_HYP_TERMNO'),
                           ],
                           [
                               'name' => 'Info',
                               'type' => 'hidden',
                               'value' => $cart->id,
                           ],
                           [
                               'name' => 'Amount',
                               'type' => 'hidden',
                               'value' => $total,
                           ],
                           [
                               'name' => 'Tash',
                               'type' => 'hidden',
                               'value' => $Tash,
                           ],
                           [
                               'name' => 'sendemail',
                               'type' => 'hidden',
                               'value' => 'true',
                           ],
                           [
                               'name' => 'PageLang',
                               'type' => 'hidden',
                               'value' => $varLangToSend,
                           ],
                           [
                               'name' => 'Coin',
                               'type' => 'hidden',
                               'value' => $varCurToSend,
                           ],
                           [
                               'name' => 'Sign',
                               'type' => 'hidden',
                               'value' => 'True',
                           ],
                           [
                               'name' => 'Postpone',
                               'type' => 'hidden',
                               'value' => $Postpone,
                           ],
                           [
                               'name' => 'Order',
                               'type' => 'hidden',
                               'value' => $cart->id,
                           ],
                           [
                               'name' => 'ClientLName',
                               'type' => 'hidden',
                               'value' => $this->context->customer->lastname,
                           ],
                           [
                               'name' => 'ClientName',
                               'type' => 'hidden',
                               'value' => $this->context->customer->firstname,
                           ],
                           [
                               'name' => 'heshDesc',
                               'type' => 'hidden',
                               'value' => $itemArray,
                           ],
                           [
                               'name' => 'Pritim',
                               'type' => 'hidden',
                               'value' => $Pritim,
                           ],
                           [
                               'name' => 'street',
                               'type' => 'hidden',
                               'value' => $billing_address->address1,
                           ],
                           [
                               'name' => 'city',
                               'type' => 'hidden',
                               'value' => $billing_address->city,
                           ],
                           [
                               'name' => 'cell',
                               'type' => 'hidden',
                               'value' => $billing_address->phone_mobile,
                           ],
                           [
                               'name' => 'zip',
                               'type' => 'hidden',
                               'value' => $billing_address->postcode,
                           ],
                           [
                               'name' => 'phone',
                               'type' => 'hidden',
                               'value' => $billing_address->phone,
                           ],
                           [
                               'name' => 'email',
                               'type' => 'hidden',
                               'value' => $this->context->customer->email,
                           ],
                           [
                               'name' => 'UTF8',
                               'type' => 'hidden',
                               'value' => 'True',
                           ],
                           [
                               'name' => 'UTF8out',
                               'type' => 'hidden',
                               'value' => 'True',
                           ],
                           [
                               'name' =>'tmp', 
                               'type' =>'hidden',
                               'value' => $tmp,
                           ],
                       ])
                       ->setAdditionalInformation($this->context->smarty->fetch('module:giladx_hyp/views/templates/front/payment_infos.tpl'))
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/payment.png'));

        return $externalOption;
    }

    public function getContent()
    {
       $this->_postProcess();
       $currentDomain = Tools::getShopDomain(true); // Get the current domain
       // Force HTTPS if the current domain is HTTP
       if (strpos($currentDomain, 'http://') === 0) {
           $currentDomain = 'https://' . substr($currentDomain, 7); // Replace http with https
       }
       $redirectLink = $currentDomain . '/?fc=module&module=' . $this->name . '&controller=validation';

    // Prepare the HTML for the copy link with modern design

    $this->_html .= '<div style="margin-bottom: 20px; background-color: #333; padding: 15px; border-radius: 5px; position: relative;">';
    $this->_html .= '<label style="color: #fff;">' . $this->l('Redirect Link:') . '</label>';
    $this->_html .= '<div id="redirectLink" title="' . htmlspecialchars($redirectLink) . '" style="width: calc(100% - 40px); padding: 10px; border: none; border-radius: 5px; margin-top: 10px; background-color: #444; color: #fff; cursor: pointer;">';
    $this->_html .= htmlspecialchars($redirectLink);
    $this->_html .= '</div>';
    $this->_html .= '<button id="copyButton" style="position: absolute; right: 10px; top: 10px; background: none; border: none; cursor: pointer; color: #fff; font-size: 18px;" title="' . $this->l('Copy Link') . '">';
    $this->_html .= '<i class="material-icons">content_copy</i>'; // Using Material Icons for the copy icon
    $this->_html .= '</button>';
    $this->_html .= '</div>';


    // Add JavaScript for the copy functionality

    $this->_html .= '<script>
    document.getElementById("copyButton").addEventListener("click", function() {
        var copyText = document.getElementById("redirectLink").innerText; // Get the text from the div
        navigator.clipboard.writeText(copyText).then(function() {
            alert("' . $this->l('Success Redirect Link copied to clipboard!') . '");
        }, function(err) {
            console.error("Could not copy text: ", err);
        });
    });
    </script>';

    // Get the values for the buttons

    $masof = Configuration::get('GILADX_HYP_TERMNO');
    $username = Configuration::get('GILADX_HYP_USERNAME');
    $password = Configuration::get('GILADX_HYP_PASSWORD_FIELD');


    // Add the buttons below the form

    $this->_html .= '<div class="tab-pane active" id="tab-login" style="margin-top: 15px;">';
    $this->_html .= '<a href="#" onclick="window.open(\'https://pay.hyp.co.il/p3/?action=login&Masof=' . $masof . '&User=' . $username . '&Pass=' . $password . '\', \'yaadpay\', \'resizable,top=500,left=500,width=800,height=600\'); return false;" class="btn btn-default" style="color: #fff;background-color: #007a0e;border-color: #ddd;">Stay in Store</a>';
    $this->_html .= '<a href="https://pay.hyp.co.il/p3/?action=login&Masof=' . $masof . '&User=' . $username . '&Pass=' . $password . '" target="_blank" class="btn btn-default" style="color: #fff;background-color: #007a0e;border-color: #ddd;">Open in New Tab</a>';
    $this->_html .= '</div>';

    // Add JavaScript for the copy functionality

    $this->_html .= '<script>
        document.getElementById("copyButton").addEventListener("click", function() {
            var copyText = document.getElementById("redirectLink");
            copyText.select();
            document.execCommand("copy");
            alert("' . $this->l('Link copied to clipboard!') . '");
        });
    </script>';

    $this->_html .= '<script>
        document.getElementById("copyButton").addEventListener("click", function() {
            var copyText = document.getElementById("redirectLink");
            copyText.select();
            document.execCommand("copy");
            alert("' . $this->l('Link copied to clipboard!') . '");
        });
    </script>';
    return $this->_html . $this->renderForm(); return $this->renderForm();
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('submitGiladx_hypModule')) {
            $form_values = $this->getConfigFormValues();
            foreach (array_keys($form_values) as $key) {
                Configuration::updateValue($key, Tools::getValue($key));
            }
        }
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitGiladx_hypModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigFormValues()
    {
        return array(
            'GILADX_HYP_PASSWORD' => Configuration::get('GILADX_HYP_PASSWORD', null),
            'GILADX_HYP_TERMNO' => Configuration::get('GILADX_HYP_TERMNO', null),
            'GILADX_HYP_SPLIT' => Configuration::get('GILADX_HYP_SPLIT', null),
            'GILADX_HYP_POSTPONE' => Configuration::get('GILADX_HYP_POSTPONE', null),
            'GILADX_HYP_PRITIM' => Configuration::get('GILADX_HYP_PRITIM', null),
            'GILADX_HYP_TMP' => Configuration::get('GILADX_HYP_TMP', null),
            'GILADX_HYP_USERNAME' => Configuration::get('GILADX_HYP_USERNAME', null),
            'GILADX_HYP_PASSWORD_FIELD' => Configuration::get('GILADX_HYP_PASSWORD_FIELD', null),
        );
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('HYP Terminal Details'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'GILADX_HYP_PASSWORD',
                        'label' => $this->l('Password Signature'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'GILADX_HYP_TERMNO',
                        'label' => $this->l('Terminal Number'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'select',
                        'name' => 'GILADX_HYP_SPLIT',
                        'label' => $this->l('Hyp Number of Tash allowed'),
                        'options' => array(
                            'query' => array(
                                array('id_option' => 1, 'name' => $this->l('Hyp Tash') . ' 1'),
                                array('id_option' => 2, 'name' => $this->l('Hyp Tash') . ' 2'),
                                array('id_option' => 3, 'name' => $this->l('Hyp Tash') . ' 3'),
                                array('id_option' => 4, 'name' => $this->l('Hyp Tash') . ' 4'),
                                array('id_option' => 5, 'name' => $this->l('Hyp Tash') . ' 5'),
                                array('id_option' => 6, 'name' => $this->l('Hyp Tash') . ' 6'),
                                array('id_option' => 7, 'name' => $this->l('Hyp Tash') . ' 7'),
                                array('id_option' => 8, 'name' => $this->l('Hyp Tash') . ' 8'),
                                array('id_option' => 9, 'name' => $this->l('Hyp Tash') . ' 9'),
                                array('id_option' => 10, 'name' => $this->l('Hyp Tash') . ' 10'),
                                array('id_option' => 11, 'name' => $this->l('Hyp Tash') . ' 11'),
                                array('id_option' => 12, 'name' => $this->l('Hyp Tash') . ' 12'),
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'col' => 3,
                        'type' => 'radio',
                        'name' => 'GILADX_HYP_POSTPONE',
                        'label' => $this->l('Postpone Payment'),
                        'values' => array(
                            array('id' => 'active_on', 'value' => true, 'label' => $this->l('True')),
                            array('id' => 'active_off', 'value' => false, 'label' => $this->l('False')),
                        ),
                    ),
                    array(
                        'col' => 4,
                        'type' => 'radio',
                        'name' => 'GILADX_HYP_PRITIM',
                        'label' => $this->l('Pritim'),
                        'values' => array(
                            array('id' => 'active_on', 'value' => true, 'label' => $this->l('True')),
                            array('id' => 'active_off', 'value' => false, 'label' => $this->l('False')),
                        ),
                    ),
                    array(
                        'col' => 5,
                        'type' => 'text',
                        'name' => 'GILADX_HYP_TMP',
                        'label' => $this->l('Template Number'),
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'name' => 'GILADX_HYP_USERNAME',
                        'label' => $this->l('Hyp User'),
                        'attributes' => array('autocomplete' => 'off'),
                    ),
                    array(
                        'col' => 7,
                        'type' => 'password',
                        'name' => 'GILADX_HYP_PASSWORD_FIELD',
                        'label' => $this->l('Hyp Pass'),
                        'attributes' => array('autocomplete' => 'off'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    public function uninstall()
    {
        Configuration::deleteByName('GILADX_HYP_SPLIT');
        Configuration::deleteByName('GILADX_HYP_PASSWORD');
        Configuration::deleteByName('GILADX_HYP_TERMNO');
        Configuration::deleteByName('GILADX_HYP_POSTPONE');
        Configuration::deleteByName('GILADX_HYP_PRITIM');
        Configuration::deleteByName('GILADX_HYP_TMP');
        Configuration::deleteByName('GILADX_HYP_USERNAME');
        Configuration::deleteByName('GILADX_HYP_PASSWORD_FIELD');
        return parent::uninstall();
    }
}
<?php

class Giladx_hypValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;

        // Retrieve parameters from the GET request
        $deal = Tools::getValue('Id'); // עסקה 'מס
        $CCode = Tools::getValue('CCode'); // משבא תשובה 'מס
        $Amount = Tools::getValue('Amount'); // סכום
        $ACode = Tools::getValue('ACode'); // 
        $token = Tools::getValue('Order'); // token
        $fullname = Tools::getValue('Fild1'); // משפחה ושם פרטי שם
        $email = Tools::getValue('Fild2'); // מייל כתובת
        $phone = Tools::getValue('Fild3'); // טלפון
        $Sign = Tools::getValue('Sign'); // דיגיטלית חתימה

        $sign_array = array(
            'Id' => $deal, 
            'CCode' => $CCode, 
            'Amount' => $Amount, 
            'ACode' => $ACode, 
            'Order' => $token, 
            'Fild1' => rawurlencode($fullname), 
            'Fild2' => rawurlencode($email), 
            'Fild3' => rawurlencode($phone)
        );

        $verify = $this->checkHeshForPayment($sign_array);

        if (isset($token) && $token > 0 && isset($CCode) && ($CCode == 0 || $CCode == 800)) {
            if ($Sign == $verify) {
                if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
                    Tools::redirect('index.php?controller=order&step=1');
                }

                // Check that this payment option is still available
                $authorized = false;
                foreach (Module::getPaymentModules() as $module) {
                    if ($module['name'] == 'giladx_hyp') {
                        $authorized = true;
                        break;
                    }
                }

                if (!$authorized) {
                    die($this->module->l('This payment method is not available.', 'validation'));
                }

                $customer = new Customer($cart->id_customer);
                if (!Validate::isLoadedObject($customer)) {
                    Tools::redirect('index.php?controller=order&step=1');
                }

                $currency = $this->context->currency;
                $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
                $mailVars = array('transaction_id' => $deal);

                $this->module->validateOrder((int)$cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);

                Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart->id . '&id_module=' . (int)$this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
            } else {
                $params = array("errorY" => "yes");
                $back_url = $this->context->link->getPageLink('order-opc', true, (int)$this->context->language->id, $params);
                Tools::redirect($back_url . "#showError");
            }
        } else {
            $params = array("errorY" => "yes");
            $back_url = $this->context->link->getPageLink('order-opc', true, (int)$this->context->language->id, $params);
            Tools::redirect($back_url . "#showError");
        }
    }

    public function checkHeshForPayment($data)
    {
        $signaturePass = Configuration::get('GILADX_HYP_PASSWORD');
        $string = '';

        foreach ($data as $key => $val) {
            $string .= $key . '=' . $val . '&';
        }
        $string = rtrim($string, '&'); // Remove the last '&'
        return hash_hmac('SHA256', $string, $signaturePass);
    }
}

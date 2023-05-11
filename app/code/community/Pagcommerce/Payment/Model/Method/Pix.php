<?php

class Pagcommerce_Payment_Model_Method_Pix extends Mage_Payment_Model_Method_Abstract {

    protected $_code          = 'pagcommerce_payment_pix';
    protected $_formBlockType = 'pagcommerce_payment/form_pix';
    protected $_infoBlockType = 'pagcommerce_payment/info_pix';

    protected $_isGateway = true;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = false;
    protected $_isInitializeNeeded = true;

    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;

    protected $_canAuthorize = true;
    protected $_canCancelInvoice = false;


    /**
     * Initialize
     *
     * @param string $payment
     * @param Varien_Object $state
     * @return $this
     */
    public function initialize($paymentAction,$objectState) {
        parent::initialize($paymentAction, $objectState);
        $quote = $this->getQuote();
        $orderId = $quote->getReservedOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);

        $helper = Mage::helper('pagcommerce_payment');
        if (!$order->getCustomerTaxvat()){
            $postData = Mage::app()->getRequest()->getPost();
            if (isset($postData['cpfcnpjpix']) && $postData['cpfcnpjpix']){
                $cpfcnpj = $postData['cpfcnpjpix'];
                $cpfcnpj = str_replace(array('-', '.', '/'), array('', '', ''), $cpfcnpj);
                /** @var Mage_Customer_Model_Customer $customer */
                $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
                $attributeTaxvat = Mage::getStoreConfig('payment/pagcommerce_payment/customer_taxvat_attribute');

                if (strlen($cpfcnpj) == 11){
                    if (!$helper->validarCpf($cpfcnpj)){
                        throw new Mage_Payment_Model_Info_Exception('Porfavor, digite um CPF válido!');
                    } else{
                        $order->setData('customer_taxvat', $cpfcnpj);
                        $order->save();
                        $customer->setData($attributeTaxvat, $cpfcnpj);
                        $customer->save();
                    }
                } else if (strlen($cpfcnpj) == 14){
                    if (!$helper->validarCnpj($cpfcnpj)){
                        throw new Mage_Payment_Model_Info_Exception('Por favor, digite um CNPJ válido!');
                    } else{
                        $order->setData('customer_taxvat', $cpfcnpj);
                        $order->save();
                        $customer->setData($attributeTaxvat, $cpfcnpj);
                        $customer->save();
                    }
                } else{
                    throw new Mage_Payment_Model_Info_Exception('O CPF/CNPJ informado é inválido!');
                }

            } else{
                throw new Mage_Payment_Model_Info_Exception('É necessário inserir um CPF/CNPJ válido para finalizar a compra!');
            }
        }
        /** @var Mage_Sales_Model_Order $order */
        if($order && $order->getId()){
            $api = $this->_getApi();
            $pixResponse = $api->getPixResponse($order);
            if($pixResponse && !isset($pixResponse['detail'])){
                $info = $this->getInfoInstance();
                $info->setAdditionalInformation('pix', $pixResponse['payment_data']);
                $info->save();
            }else{
                if(isset($pixResponse['detail']) && $pixResponse['detail']){
                    $message = $pixResponse['detail'];
                }else{
                    $message = 'Ocorreu um erro ao gerar o QR Code PIX. '.$api->getErrors();
                }
                throw new Mage_Payment_Model_Info_Exception($message);
            }
        }
        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function assignData($data){
        $info = $this->getInfoInstance();
        $method = $this->getQuote()->getPayment()->getMethod();
        $info->setAdditionalInformation('payment_method', $method);
        return $this;
    }


    public function validate(){
        return parent::validate();
    }


    /**
     * @return Pagcommerce_Payment_Model_Api_Pix
     */
    protected function _getApi(){
        /** @var $api Pagcommerce_Payment_Model_Api_Pix */
        return Mage::getSingleton('pagcommerce_payment/api_pix');
    }

    /**
     * @param integer $quoteId
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote($quoteId = null) {
        if (!empty($quoteId)) {
            return Mage::getModel('sales/quote')->load($quoteId);
        }
        else {
            if(Mage::app()->getStore()->isAdmin()) {
                return Mage::getSingleton('adminhtml/session_quote')->getQuote();
            } else {
                return $this->getCheckoutSession()->getQuote();
            }
        }
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession(){
        return Mage::getSingleton('checkout/session');
    }


//    public function getOrderPlaceRedirectUrl(){
//        return Mage::getUrl($this->getCode() . '/standard/success');
//    }


}
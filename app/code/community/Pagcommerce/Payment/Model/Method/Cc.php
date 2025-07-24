<?php

class Pagcommerce_Payment_Model_Method_Cc extends Mage_Payment_Model_Method_Abstract {

    protected $_code          = 'pagcommerce_payment_cc';

    protected $_formBlockType = 'pagcommerce_payment/form_cc';
    protected $_infoBlockType = 'pagcommerce_payment/info_cc';

    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canVoid = true;
    protected $_isInitializeNeeded = true;

    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;

    protected $_canAuthorize = true;
    protected $_canCancelInvoice = true;

    private $_last_pagcommerce_response = array();

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Initialize
     *
     * @param string $payment
     * @param Varien_Object $state
     * @return $this
     */
    public function initialize($paymentAction,$objectState) {
        parent::initialize($paymentAction, $objectState );
        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $this->getInfoInstance();

        /** @var Pagcommerce_Payment_Helper_Data $helper */
        $helper = Mage::helper('pagcommerce_payment');

        $additional = $payment->getAdditionalInformation();
        if (isset($additional['document_number']) && $additional['document_number']){
            $doc = str_replace(array('-','.','/'), array('', '', ''), trim($payment->getAdditionalInformation()['document_number']));
            if (strlen($doc) > 11){
                $validate_cpfcnpj = $helper->validarCnpj($doc);
                $document_type = 'CNPJ';
            } else{
                $validate_cpfcnpj = $helper->validarCpf($doc);
                $document_type = 'CPF';
            }

            if (!$validate_cpfcnpj){
                throw new Mage_Payment_Model_Info_Exception($helper->__('O CPF/CNPJ informado é inválido'));
            } else{
                $payment->setAdditionalInformation('document_type', $document_type);
            }
        }
        $parcelQty = $payment->getAdditionalInformation('installments');
        $parcels = $helper->getInterestsByTotal($payment->getOrder()->getGrandTotal());
        $parcel = $parcels[$parcelQty];
        $payment->getOrder()->addStatusHistoryComment($parcel['label']);
        $payment->getOrder()->save();

        $onlyAuthorize = $paymentAction == 'authorize' ? true : false;

        $countRequest = 0;
        do{
            $api = $this->getApi();
            try{
                $response = $api->processPayment($payment->getOrder(), $parcelQty, $parcel);
                $this->_last_pagcommerce_response = $response;
                if($response && isset($response['id'])){
                    $countRequest = 100000;
                    /** @var Mage_Sales_Model_Order_Payment $payment */
                    $payment->setAdditionalInformation('transaction_id', $response['id']);
                    $payment->save();

                    switch ($response['status']){
                        case 'denied':
                            throw new Mage_Payment_Model_Info_Exception($helper->__('Pagamento não aprovado. Por favor tente novamente com outro cartão'));
                        case 'denied_risk':
                            throw new Mage_Payment_Model_Info_Exception($helper->__('Pagamento não aprovado. Por favor tente novamente com outro cartão ou utilize outro dispositivo'));
                            break;
                        case 'in_analysis':
                            $configStatus = Mage::getStoreConfig('payment/pagcommerce_payment_cc/order_status_fraud_analisys');
                            if($configStatus){
                                $objectState->setState('new');
                                $objectState->setStatus($configStatus);
                                $objectState->setIsNotified(false);
                            }
                            break;
                        case 'approved':
                            $this->confirmPayment($payment->getOrder(), 'Pagamento confirmado');
                            break;
                    }
                }else{
                    if($api->getErrors()) {
                        throw new Mage_Payment_Model_Info_Exception($api->getErrors());
                    }
                    throw new Mage_Payment_Model_Info_Exception($helper->__('Ocorreu um erro ao processar seu pagamento. Por favor tente novamente'));
                }
            }catch (Exception $e){
                if($e instanceof Mage_Payment_Model_Info_Exception){
                    $countRequest = 100000;
                    throw new Mage_Payment_Model_Info_Exception($e->getMessage());
                }else{
                    Mage::log($e->getMessage(), null, 'pagcommerce_cc_error.log');
                    $countRequest++;
                    if($countRequest >= 3){
                        $countRequest = 100000;
                        throw new Mage_Payment_Model_Info_Exception($e->getMessage());
                    }else{
                        sleep(1);
                    }
                }
            }
        }while($countRequest < 3);

        return $this;
    }

    public function getLastPagcommerceResponse()
    {
        return $this->_last_pagcommerce_response;
    }



    /** @return $this */
    public function authorize(Varien_Object $payment, $amount)
    {
        parent::authorize($payment,$amount);
        return $this;
    }


    public function capture(Varien_Object $payment, $amount)
    {
        return parent::capture($payment, $amount);
    }


    public function confirmPayment(Mage_Sales_Model_Order $order, $comment){
        return $this->_createInvoice($order, $comment, true);
    }


    /** @return Pagcommerce_Payment_Model_Api_Cc */
    private function getApi(){
        return Mage::getModel('pagcommerce_payment/api_cc');
    }

    /**
     * @param array $data
     * @return $this
     */
    public function assignData($data){

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        /** @var Pagcommerce_Payment_Helper_Data $helper */
        $helper = Mage::helper('pagcommerce_payment');

        /** @var Mage_Sales_Model_Quote_Payment $info */
        $info = $this->getInfoInstance();

        /** @var Pagcommerce_Payment_Model_Source_CreditCard_Brand $sourceCards */
        $sourceCards = Mage::getModel('Pagcommerce_Payment_Model_Source_CreditCard_Brand');
        $allCards = $sourceCards->toArray();


        if($data->getData('cc_savedcard_id')){
            /** @var Mage_Customer_Model_Session $session */
            $session = Mage::getSingleton('customer/session');
            if($session->isLoggedIn()){
                $email  = $session->getCustomer()->getEmail();

                /** @var Pagcommerce_Payment_Model_Api_CreditCardToken $api */
                $api = Mage::getModel('Pagcommerce_Payment_Model_Api_CreditCardToken');
                $cards = $api->getCardByCustomerEmail($email);
                if($cards){
                    $currentCard = false;
                    foreach($cards as $card){
                        if($card['id'] == $data->getData('cc_savedcard_id')){
                            $currentCard = $card;
                            break;
                        }
                    }

                    if($currentCard){
                        $cvv = trim($data->getData('cc_card_cvv_'.$currentCard['id']));

                        $brandName =  isset($allCards[$currentCard['card_brand']]) ?  $allCards[$currentCard['card_brand']] : '';
                        $last4 = $currentCard['last4_digits'];
                        $data->setCcCid($cvv);
                        $info->setAdditionalInformation('card_id', $currentCard['id']);

                        $info->setCcCid($cvv);
                        $info->setCcLast4($last4);

                    }else{
                        throw new Mage_Payment_Model_Info_Exception($helper->__('Cartão de crédito não existe'));
                    }

                }else{
                    throw new Mage_Payment_Model_Info_Exception($helper->__('Cartão de crédito inexistente'));
                }
            }else{
                throw new Mage_Payment_Model_Info_Exception($helper->__('Cartão de crédito salvo inválido'));
            }
        }else{
            if(!$data->getCcNumber()){
                throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o número do cartão de crédito ou escolher um cartão salvo'));
            }
            if(!$data->getCcName()){
                throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o nome do titular do cartão'));
            }

            if(!$data->getCcExpMonth()){
                throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o mês de vencimento do cartão'));
            }


            if(!$data->getCcExpYear()){
                throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o ano de vencimento do cartão'));

            }

            if(!$data->getCcCid()){
                throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o código de segurança do cartão'));
            }

//            $allowedBrands = $helper->getConfigCc('brands');
//            if(!$allowedBrands){
//                throw new Mage_Payment_Model_Info_Exception($helper->__('Nenhuma bandeira de cartão foi permitida para receber pagamentos'));
//            }
//            $allowedBrands = explode(',', $allowedBrands);

            /** @var Pagcommerce_Payment_Model_CreditCard_Issuer $issuer */
            $issuer = Mage::getModel('Pagcommerce_Payment_Model_CreditCard_Issuer');
            $brand = $issuer->getCardIssuer($data->getCcNumber());



//            if(!in_array($brand, $allowedBrands)){
//                throw new Mage_Payment_Model_Info_Exception($helper->__('Bandeira '.$allCards[$brand].' não permitida para essa compra. Por favor utilize outro cartão'));
//            }

            $ccNumber = $data->getCcNumber();
            $ccNumber = str_replace(array(' ', '-', '.'), '', $ccNumber);
            $info->setCcType($data->getCcType())
                ->setCcNumber($ccNumber)
                ->setCcName($data->getCcName())
                ->setCcExpMonth($data->getCcExpMonth())
                ->setCcExpYear($data->getCcExpYear())
                ->setCcCid(trim($data->getCcCid()))
                ->setCcLast4(substr($ccNumber, -4))
            ;

            if($data->getCcCpf()){
                $documentNumber = trim($data->getCcCpf());
                $documentNumber = str_replace(array(' ', '.', '-'), '', $documentNumber);
                $info->setAdditionalInformation('document_number', $documentNumber);
            }

            $info->setAdditionalInformation('cc_save_card', $data->getData('cc_save_card'));
            $info->setCcOwner($data->getCcName());


            $brandName = isset($allCards[$brand]) ?  $allCards[$brand] : '';
            $last4 = substr($ccNumber, -4);
        }



        $parcelAvailable = $helper->getInterestsByTotal($this->getInfoInstance()->getQuote()->getGrandTotal());
        $currentParcel = $parcelAvailable[$data->getCcInterest()];

        $paymentDescription =  $brandName.' de final '.$last4.' <br>'.$currentParcel['label'];



        $info->setAdditionalInformation('installments', $data->getCcInterest());

        $info->setAdditionalInformation('last_digits', $last4);
        $info->setAdditionalInformation('brand', $brandName);
        $info->setAdditionalInformation('payment_description', $paymentDescription);
        $info->setAdditionalInformation('payment_method', 'pagcommerce_payment_cc');
        return $this;
    }

    public function validate(){
        return parent::validate();
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


    /**
     * Create invoice
     *
     * @param Mage_Sales_Model_Order @order
     * @param string $comment
     * @param bool $notify
     * @return int|bool
     */
    protected function _createInvoice(Mage_Sales_Model_Order $order, $comment = null, $notify = false) {
        if (!$order->canInvoice()) {
            return false;
        }
        $invoice = $order->prepareInvoice(array());
        if($invoice) {

            $response = $this->getLastPagcommerceResponse();
            if (isset($response['id'])){
                $invoice->setTransactionId($response['id']);
            }

            $invoice->register()->pay();
            $invoice->addComment($comment, $notify && $comment);
            $invoice->getOrder()->setIsInProcess(true);
            $invoice->setEmailSent($notify);

            Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
            $invoice->sendEmail($notify, $comment);
            $invoice->save();

            // Validação para produto virtual/baixável
            $items = $order->getAllItems();
            $virtualCount = 0;
            foreach ($items as $item) {
                if ($item["is_virtual"] == "1" || $item["is_downloadable"] == "1") $virtualCount++;
            }
            $order->save();
            return $invoice->getIncrementId();
        }
        return false;
    }

    public function canVoid(Varien_Object $payment)
    {
        return $this->_canVoid;
    }

    public function canRefund()
    {
        return $this->_canRefund;
    }

    public function void(Varien_Object $payment)
    {
        Mage::throwException(Mage::helper('payment')->__('Void NOT IMPLEMENTD CIELO.'));
        if (!$this->canVoid($payment)) {
            Mage::throwException(Mage::helper('payment')->__('Void action is not available.'));
        }
        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();
        /** @var Pagcommerce_Payment_Model_Api_Cc $apiCc */
        $apiCc = Mage::getModel('pagcommerce_payment/api_cc');
        try{
            $response = $apiCc->refundOrder($order);
            if($response){
                /** @var Mage_Admin_Model_Session $session */
                $session = Mage::getModel('admin/session');
                /** @var Mage_Admin_Model_User $user */
                $user = $session->getUser();
                $order->addStatusHistoryComment('Transação estornada pelo usuário '.$user->getName().' - '.$user->getEmail());
            }
            return $this;
        }catch (Exception $e){
            /** @var Mage_Admin_Model_Session $session */
            $session = Mage::getSingleton('adminhtml/session');
            $session->addError($e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

}
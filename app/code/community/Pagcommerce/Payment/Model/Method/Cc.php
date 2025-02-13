<?php

class Pagcommerce_Payment_Model_Method_Cc extends Mage_Payment_Model_Method_Cc {

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

        /** @var Codecia_Cielo_Helper_Data $helper */
        $helper = Mage::helper('codecia_cielo');

        if ($payment->getAdditionalInformation()['document_number']){
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
        $response = $this->getApi()->processPayment($payment, $onlyAuthorize);
        if(is_array($response) && isset($response['Payment']['Tid'])){
            $paymentStatus = (string)$response['Payment']['Status'];
            if($paymentStatus == Codecia_Cielo_Model_Source_Payment_Status::STATUS_PAYMENTCONFIRMED && !$onlyAuthorize){
                $this->confirmPayment($payment->getOrder(), 'Pagamento confirmado');
            }
        }else{
            throw new Mage_Payment_Model_Info_Exception($helper->__('Ocorreu um erro no pagamento. Por favor tente novamente ou utilize outro cartão de crédito'));
        }

        return $this;
    }


    /** @return $this */
    public function authorize(Varien_Object $payment, $amount)
    {
        parent::authorize($payment,$amount);
        return $this;
    }


    public function capture(Varien_Object $payment, $amount)
    {
        parent::capture($payment, $amount);

        /** @var Codecia_Cielo_Helper_Data $helper */
        $helper = Mage::helper('codecia_cielo');

        $info = $payment->getAdditionalInformation();

        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        if(isset($info['installments']) && isset($info['response_cielo']['Payment']['PaymentId'])){
            $paymentId = $info['response_cielo']['Payment']['PaymentId'];
            $response = $this->getApi()->capture($paymentId, $order);
            if(isset($response[0]['Message'])){
                throw new Exception($response[0]['Message']);
            }else{
                $historyComment = 'Transação capturada';
                $historyComment .= '<br>ReturnCode: '.$response['ReturnCode'];
                $historyComment .= '<br>ReturnMessage: '.$response['ReturnMessage'];
                $historyComment .= '<br>Tid: '.$response['Tid'];
                $historyComment .= '<br>ProofOfSale (NSU): '.$response['ProofOfSale'];
                $historyComment .= '<br>Código de Autorização: '.$response['AuthorizationCode'];

                $order->addStatusHistoryComment($historyComment);
                $order->save();
            }
        }
    }


    public function confirmPayment(Mage_Sales_Model_Order $order, $comment){
        return $this->_createInvoice($order, $comment, true);
    }


    /** @return Codecia_Cielo_Model_Api_Cc */
    private function getApi(){
        return Mage::getModel('codecia_cielo/api_cc');
    }

    /**
     * @param array $data
     * @return $this
     */
    public function assignData($data){

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        /** @var Codecia_Cielo_Helper_Data $helper */
        $helper = Mage::helper('codecia_cielo');

        if(!$data->getCcType())
            throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o tipo do cartão'));


        if(!$data->getCcNumber()){
            throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o número do cartão de crédito'));
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

        $allowedBrands = $this->getConfigData('cc_brands');
        if(!$allowedBrands){
            throw new Mage_Payment_Model_Info_Exception($helper->__('Nenhuma bandeira de cartão foi permitida para receber pagamentos'));
        }
        $allowedBrands = explode(',', $allowedBrands);

        if(!in_array($data->getData('cc_type'), $allowedBrands)){
            throw new Mage_Payment_Model_Info_Exception($helper->__('Bandeira '.$data->getData('cc_type').' não permitida para essa compra'));
        }
        /** @var Mage_Sales_Model_Quote_Payment $info */
        $info = $this->getInfoInstance();

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

        $info->setAdditionalInformation('installments', $data->getCcInterest());
        $info->setAdditionalInformation('last_digits', substr($ccNumber, -4) );
        $info->setAdditionalInformation('payment_method', 'codecia_cielo_cc');
        $info->setCcOwner($data->getCcName());


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
            $invoice->register()->pay();
            $invoice->addComment($comment, $notify && $comment);
            $data = $order->getPayment()->getAdditionalInformation();
            if (isset($data['response_cielo']['Payment']['Tid'])){
                $invoice->setTransactionId($data['response_cielo']['Payment']['Tid']);
            }
            $invoice->setEmailSent($notify);
            $invoice->getOrder()->setIsInProcess(true);
            Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
            $invoice->sendEmail($notify, $comment);

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
        Mage::throwException(Mage::helper('payment')->__('Refund NOT CIELO.'));
        if (!$this->canRefund()) {
            Mage::throwException(Mage::helper('payment')->__('Refund action is not available.'));
        }
        return $this;
    }

}
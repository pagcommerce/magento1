<?php

class Pagcommerce_Payment_Model_Method_Cc extends Mage_Payment_Model_Method_Cc {

    protected $_code          = 'pagcommerce_payment_cc';
    protected $_formBlockType = 'pagcommerce_payment_cc/form_cc';
    protected $_infoBlockType = 'pagcommerce_payment_cc/info_cc';

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

        /** @var Pagcommerce_Payment_Helper_Data $helper */
        $helper = Mage::helper('pagcommerce_payment');

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

        if ($antifraude = $_POST['payment']['antifraude']){
            $payment->setAdditionalInformation('antifraude', $antifraude);
            $payment->save();
        }

        if(!$payment->getCcType())
            throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o tipo do cartão'));


        if(!$payment->getCcNumber()){
            throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o número do cartão de crédito'));
        }

        if(!$payment->getCcOwner()){
            throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o nome do titular do cartão'));
        }


        if(!$payment->getCcExpMonth()){
            throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o nome mês de vencimento do cartão'));
        }

        if(!$payment->getCcExpYear()){
            throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o nome ano de vencimento do cartão'));

        }

        if(!$payment->getCcCid()){
            throw new Mage_Payment_Model_Info_Exception($helper->__('É necessário informar o código de segurança do cartão'));
        }


        $parcelQty = $payment->getAdditionalInformation('installments');
        $parcels = $helper->getInterestsByTotal($payment->getOrder()->getGrandTotal());
        $parcel = $parcels[$parcelQty - 1];
        $payment->getOrder()->addStatusHistoryComment($parcel['label']);
        $payment->getOrder()->save();

        $onlyAuthorize = $paymentAction == 'authorize' ? true : false;
        $returnData = $this->getApi()->processPayment($payment, $onlyAuthorize);
        if(isset($returnData['status'])){
            if($onlyAuthorize){
                if($returnData['status'] == Codecia_Getnet_Model_Api_Cc::STATUS_AUTHORIZED)
                    return $this;
            }else{
                //houve captura
                if($returnData['status'] == Codecia_Getnet_Model_Api_Cc::STATUS_APPROVED){
                    $comment = $helper->__("Confirmação automática de pagamento");
                    return $this->confirmPayment($payment->getOrder(), $comment);
                }
            }
        }



        if(isset($returnData['message'])){
            $message = $returnData['message'];
            if(isset($returnData['details']) && is_array($returnData['details']) && $returnData['details']){
                $message.=' - ';
                foreach($returnData['details'] as $detail){
                    $message.= $detail['description'].' ('.$detail['description_detail'].')';
                }
            }
            throw new Mage_Payment_Model_Info_Exception($helper->__($message));
        }

        throw new Mage_Payment_Model_Info_Exception($helper->__('Houve um erro ao processar seu pagamento. Por favor tente novamente'));
        return $this;
    }



    /** @return $this */
    public function authorize(Varien_Object $payment, $amount)
    {
        parent::authorize($payment,$amount);
        if (!$this->canAuthorize()) {
            Mage::throwException(Mage::helper('payment')->__('Authorize action is not available.'));
        }
        return $this;
    }


    public function capture(Varien_Object $payment, $amount)
    {

        parent::capture($payment, $amount);

        /** @var Codecia_Getnet_Helper_Data $helper */
        $helper = Mage::helper('codecia_getnet');

        $info = $payment->getAdditionalInformation();

        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        if(isset($info['installments']) && isset($info['response_getnet']['payment_id'])){
            $paymentId = $info['response_getnet']['payment_id'];
            if($info['response_getnet']['status'] == Codecia_Getnet_Model_Api_Cc::STATUS_AUTHORIZED){
                 $response = $this->getApi()->capture($paymentId, $order);
                 if(isset($response['status'])){
                     switch ($response['status']){
                         case 'CONFIRMED':
                              $order->addStatusHistoryComment($helper->__('Transação capturada'));
                              return $this;
                              break;
                         case 'PENDING':

                             break;
                         case 'EXPIRED':
                             break;
                     }

                     if(isset($response['message'])){
                         $order->addStatusHistoryComment($helper->__('Erro na captura do pagamento: ').$response['message']);
                     }
                 }else{
                     if(isset($response['message'])){
                         $order->addStatusHistoryComment($helper->__('Erro na captura do pagamento: ').$response['message']);
                     }
                 }
                 $order->save();
            }else{
                throw new Exception($helper->__("Não é possível capturar o pagamento porque a transação não foi autorizada"));
            }
        }
        throw new Exception($helper->__('Erro na captura do pagamento'));

    }


    public function confirmPayment(Mage_Sales_Model_Order $order, $comment){
        return $this->_createInvoice($order, $comment, true);
    }


    /** @return Codecia_Getnet_Model_Api_Cc */
    private function getApi(){
        return Mage::getModel('codecia_getnet/api_cc');
    }

    /**
     * @param array $data
     * @return $this
     */
    public function assignData($data){
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        /** @var Mage_Sales_Model_Quote_Payment $info */
        $info = $this->getInfoInstance();
        $info->setCcType($data->getCcType())
            ->setCcNumber($data->getCcNumber())
            ->setCcName($data->getCcName())
            ->setCcExpMonth($data->getCcExpMonth())
            ->setCcExpYear($data->getCcExpYear())
            ->setCcCid($data->getCcCid())
            ->setCcLast4(substr($data->getCcNumber(), -4))
        ;

        if($data->getCcCpf()){
            $info->setAdditionalInformation('document_number', $data->getCcCpf());
        }

        $info->setAdditionalInformation('installments', $data->getCcInterest());
        $info->setAdditionalInformation('last_digits', substr($data->getCcNumber(), -4) );
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
        $methodInstance = Mage::helper('payment')->getMethodInstance('codecia_getnet');

        if (!$order->canInvoice()) {
            return false;
        }

        $invoice = $order->prepareInvoice(array());
        if($invoice) {
            $invoice->register()->pay();
            $invoice->addComment($comment, $notify && $comment);
            if ($order->getPayment()->getAdditionalInformation()['response_getnet']['credit']['transaction_id']){
                $transaction_id = $order->getPayment()->getAdditionalInformation()['response_getnet']['credit']['transaction_id'];
                if ($transaction_id){
                    $invoice->setTransactionId($transaction_id);
                }
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

//            $statusConfigured = $methodInstance->getConfigData('order_status_invoiced') ?
//                $methodInstance->getConfigData('order_status_invoiced') : false;
//
//            $state = ($virtualCount != count($items)) ? $statusConfigured : Mage_Sales_Model_Order::STATE_COMPLETE;
//            $order->addStatusHistoryComment($comment, $state);
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
        if (!$this->canVoid($payment)) {
            Mage::throwException(Mage::helper('payment')->__('Void action is not available.'));
        }
        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        if (!$this->canRefund()) {
            Mage::throwException(Mage::helper('payment')->__('Refund action is not available.'));
        }

        $payment_id = $payment->getAdditionalInformation()['response_getnet']['payment_id'];
        if ($payment_id){
            $today_day = Mage::getSingleton('core/date')->gmtDate('Y-m-d');
            $order_created = explode(' ', $payment->getOrder()->getCreatedAt())[0];

            // comparando as datas para chamar a devida função
            if ($today_day == $order_created){
                $returnData = $this->getApi()->cancelPayment($payment_id);
            } else{
                $collection = Mage::getModel('codecia_getnet/cancels')->getCollection();
                $collection->getSelect()->where("main_table.order_id = {$payment->getOrder()->getId()}");

                if ($collection->count()){
                    Mage::throwException('O cancelamento para esse pagamento já está sendo processado!');
                } else{
                    $returnData = $this->getApi()->cancelOldPayment($payment_id, $amount);

                    if ($returnData['status']){
                        $payment->setAdditionalInformation('cancel', $returnData);

                        $cancels = Mage::getModel('codecia_getnet/cancels');
                        $cancels->setData('order_id', $payment->getOrder()->getId());
                        $cancels->setData('payment_id', $payment->getId());
                        $cancels->setData('cancel_request_id', $returnData['cancel_request_id']);
                        $cancels->setData('processed', 0);
                        $cancels->setData('created_at', Mage::getSingleton('core/date')->gmtDate('Y-m-d'));
                        $cancels->save();

                        Mage::throwException('Sua solicitação de cancelamento foi enviada para a Getnet!');
                    } else{
                        Mage::log('ORDER ID: ' . $payment->getOrder()->getIncrementId(), null, 'codecia_getnet_cancel.log');
                        Mage::log('Cancel Error: ' . json_encode($returnData), null, 'codecia_getnet_cancel.log');
                        Mage::log('-------------------------------------------', null, 'codecia_getnet_cancel.log');
                        Mage::throwException(Mage::helper('payment')->__('Refund action is not available.'));
                    }
                }
            }
            if ($returnData['status']){
                $payment->setAdditionalInformation('cancel', $returnData);
            } else{
                Mage::log('ORDER ID: ' . $payment->getOrder()->getIncrementId(), null, 'codecia_getnet_cancel.log');
                Mage::log('Cancel Error: ' . json_encode($returnData), null, 'codecia_getnet_cancel.log');
                Mage::log('-------------------------------------------', null, 'codecia_getnet_cancel.log');
                Mage::throwException(Mage::helper('payment')->__('Refund action is not available.'));
            }
        }
        return $this;
    }


}
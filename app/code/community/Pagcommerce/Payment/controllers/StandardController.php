<?php
class Pagcommerce_Payment_StandardController extends Mage_Core_Controller_Front_Action
{

    public function successAction(){
        $this->loadLayout();
        $this->renderLayout();
        return $this;
    }

    public function validateCpfCnpjAction(){
        $response = array(
            'status' => false
        );

        if ($this->getRequest()->isPost()){
            $helper = Mage::helper('pagcommerce_payment');

            $postData = $this->getRequest()->getPost();
            if (isset($postData['documento'])){
                $cpfcnpj = str_replace(array('-','.','/'), array('', '', ''), trim($postData['documento']));
                if (strlen($cpfcnpj) == 11){
                    if ($helper->validarCpf($cpfcnpj)){
                        $response['status'] = true;
                    } else{
                        $response['status'] = false;
                    }
                } else{
                    if ($helper->validarCnpj($cpfcnpj)){
                        $response['status'] = true;
                    } else{
                        $response['status'] = false;
                    }
                }
            }
        }

        echo json_encode($response);
    }

    public function notificationAction(){
        if($this->getRequest()->isPost()){
            $data = $this->getRequest()->getPost();
            if(!$data){
                $data = json_decode(file_get_contents('php://input'), true);
            }
            if(isset($data['id']) && isset($data['transaction_type']) && isset($data['reference_id'])){
                /** @var Mage_Sales_Model_Order $order */
                $order = Mage::getModel('sales/order')->loadByIncrementId($data['reference_id']);
                if($order && $order->getId()){
                    if($data['status'] == 'approved'){
                        //valida via API
                        /** @var Pagcommerce_Payment_Model_Api_Payment_Transaction $api */
                        $api = Mage::getModel('pagcommerce_payment/api_payment_transaction');
                        $transaction = $api->getTransaction($data['id']);
                        if(is_array($transaction)){
                            if(isset($transaction['id']) && $transaction['id'] == $data['id']){
                                if($transaction['status'] == 'approved'){
                                    $this->confirmPayment($order, 'Pagamento confirmado', true);
                                }
                            }
                        }
                    }else{
                        if($data['status'] == 'denied_risk'){
                            //antifraude negou...
                            if($order->getState() == Mage_Sales_Model_Order::STATE_NEW){
                                if($order->canCancel()){
                                    $order->cancel();
                                    $statusConfig = Mage::getStoreConfig('payment/pagcommerce_payment_cc/order_status_fraud_denied');
                                    if($statusConfig){
                                        $order->setStatus($statusConfig);
                                        $order->save();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function confirmPayment(Mage_Sales_Model_Order $order, $comment = null, $notify = false) {
        $methodInstance = $order->getPayment()->getMethodInstance();

        if (!$order->canInvoice()) {
            return false;
        }

        $invoice = $order->prepareInvoice(array());
        if($invoice) {
            $invoice->register()->pay();
            $invoice->addComment($comment, $notify && $comment);
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

            $statusConfigured = $methodInstance->getConfigData('order_status_invoiced') ?
                $methodInstance->getConfigData('order_status_invoiced') : false;

            $state = ($virtualCount != count($items)) ? $statusConfigured : Mage_Sales_Model_Order::STATE_COMPLETE;
            $order->addStatusHistoryComment($comment, $state);
            $order->save();
            return $invoice->getIncrementId();
        }
        return false;
    }


    public function checkorderpaymentAction(){
        $data = array(
            'status' => false,
            'paid' => false,
            'message' => 'Pedido não encontrado'
        );

        /** @var Mage_Customer_Model_Session $session */
        $session = Mage::getModel('customer/session');
        if($session->isLoggedIn()){
             $orderId = $this->getRequest()->getParam('order');
             if($orderId){
                 $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
                 if($order && $order instanceof Mage_Sales_Model_Order && $order->getId()){
                     if($order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING || $order->getState() == Mage_Sales_Model_Order::STATE_COMPLETE){
                         $data = array(
                             'status' => true,
                             'paid' => true,
                             'message' => 'Pedido pago'
                         );
                     }else{
                         $data = array(
                             'status' => true,
                             'paid' => false,
                             'message' => 'Pedido não pago'
                         );
                     }
                 }else{
                     $data['message'] = 'Pedido não encontrado';
                 }
             }else{
                 $data['message'] = 'Order Increment Id não informado';
             }
        }else{
            $data['message'] = 'Cliente não logado';
        }

        // Make sure the content type for this response is JSON
        $this->getResponse()->clearHeaders()->setHeader(
            'Content-type',
            'application/json'
        );

        // Set the response body / contents to be the JSON data
        $this->getResponse()->setBody(
            Mage::helper('core')->jsonEncode($data)
        );
    }
}
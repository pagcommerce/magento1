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
            if(isset($data['id']) && isset($data['transaction_type']) && isset($data['reference_id'])){
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

}
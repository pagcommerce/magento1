<?php
/**
 * Created by PhpStorm.
 * User: eric
 * Date: 05/10/17
 * Time: 16:54
 */
class Pagcommerce_Payment_Model_Cron extends Varien_Object {

    public function updateBoletoStatus(){
        /** @var $helper Pagcommerce_Payment_Helper_Data */
        $helper = Mage::helper('pagcommerce_payment');
        try{
            $stores = Mage::app()->getStores();
            foreach ($stores as $store){
                $storeId = Mage::app()->getStore()->getId();
                $helper->log('StoreId: '.$storeId);
                Mage::app()->setCurrentStore($store);


//                $collection = Mage::getResourceModel('sales/order_collection')
//                    ->addFieldToSelect('*')
//                    ->addFieldToFilter('state', Mage_Sales_Model_Order::STATE_NEW)
//                    //->addFieldToFilter('payment_method', 'pagcommerce_payment_boleto')
//                ;

                $collection = Mage::getModel('sales/order')->getCollection()
                    ->join(
                        array('payment' => 'sales/order_payment'),
                        'main_table.entity_id=payment.parent_id',
                        array('payment_method' => 'payment.method')
                    );

                $collection->addFieldToFilter('payment.method', 'pagcommerce_payment_boleto')
                    ->addFieldToFilter('main_table.state', Mage_Sales_Model_Order::STATE_NEW)
                ;

                if($collection->count()){
                    /** @var Mage_Sales_Model_Order $order */
                    foreach($collection as $order){

                    }
                }

                exit;

                /** @var Pagcommerce_Payment_Model_Api $api */
                $api = Mage::getModel('pagcommerce_payment/api');
                $orders = $api->getApprovedOrders();

                if($orders){
//                    echo '<pre>';
//                    print_r($orders);
                    $helper->log('Pedido encontrado..');

                    foreach($orders as $orderData){
                        $helper->log('Número do Pedido: '.$orderData['numero']);
                        /** @var Mage_Sales_Model_Order $order */
                        $order = Mage::getModel('sales/order')->loadByIncrementId($orderData['numero']);
                        if($order && $order->getId()){
                            $helper->log('State do Pedido: '.$order->getState());
                            if($order->getState() == Mage_Sales_Model_Order::STATE_NEW && $order->canInvoice()){

                                $pricePaid = $this->_convertPriceToFloat($orderData['valorPago']);
                                $paymentDate = $orderData['dataPagamento'];
                                $orderTotal = Mage::helper('core')->currency($order->getGrandTotal(),true,false);

                                $helper->log('Cód. Status do Boleto '.$orderData['status']);

                                switch($orderData['status']){
                                    case '21':
                                        //boleto pago igual
                                        $comment = $this->_getHelper()->__('Boleto pago em (%s), no valor de (%s).',$paymentDate,$pricePaid);
                                        $this->_createInvoice($order,$comment,true);
                                        break;
                                    case '22':
                                        //boleto pago a menor
                                        $comment = $this->_getHelper()->__('Boleto com valor pago (%s) menor do valor emitido (%s).',$pricePaid,$orderTotal);
                                        $order->addStatusHistoryComment($comment,Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);
                                        $order->save();
                                        break;
                                    case '23':
                                        //boleto pago a maior valor
                                        $comment = $this->_getHelper()->__('Boleto com valor pago (%s) maior do valor emitido (%s).',$pricePaid,$orderTotal);
                                        $this->_createInvoice($order,$comment,true);
                                        break;
                                }
                            }
                        }
                    }
                }
            }
        }catch (Exception $e) {
            $helper->log('Error: '.$e->getMessage());
            $helper->log('Trace: '.$e->getTraceAsString());
        }
    }


    /**
     * Create invoice
     *
     * @param Mage_Sales_Model_Order @order
     * @param string $comment
     * @param bool $notify
     * @return int|bool
     */
    protected function _createInvoice(Mage_Sales_Model_Order $order, $comment = null, $notify = false) {
        $methodInstance = $this->_getMethod();

        if (!$order->canInvoice() || !$methodInstance->getConfigData('create_invoice')) {
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


    /**
     * @return Pagcommerce_Payment_Model_Method_Boleto
     */
    private function _getMethod() {
        return Mage::getModel('pagcommerce_payment/method_boleto');
    }

    protected function _convertPriceToFloat($price){
        $integers = substr($price, 0, strlen($price)-2);
        $cents = substr($price, strlen($price)-2);
        return $integers.'.'.$cents;
    }

    /** @return Pagcommerce_Payment_Helper_Data */
    protected function _getHelper(){
        return Mage::helper('pagcommerce_payment');
    }
}
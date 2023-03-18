<?php
/**
 * Created by PhpStorm.
 * User: eric
 * Date: 05/10/17
 * Time: 16:54
 */
class Pagcommerce_Payment_Model_Cron extends Varien_Object {

    public function callOrders(){
        /** @var $helper Pagcommerce_Payment_Helper_Data */
        $helper = Mage::helper('pagcommerce_payment');
        try{
            $stores = Mage::app()->getStores();
            foreach ($stores as $store){
                $storeId = Mage::app()->getStore()->getId();
                Mage::app()->setCurrentStore($store);


                $collection = $this->getCollectionOrderToCancel('pagcommerce_payment_pix');
                if($collection->count()){
                    /** @var Mage_Sales_Model_Order $order */
                    foreach($collection as $order){
                        if($order->canCancel()){
                            $order->cancel();
                            $order->save();
                        }
                    }
                }

                $collection = $this->getCollectionOrderToCancel('pagcommerce_payment_boleto');
                if($collection->count()){
                    /** @var Mage_Sales_Model_Order $order */
                    foreach($collection as $order){
                        if($order->canCancel()){
                            $order->cancel();
                            $order->save();
                        }
                    }
                }

            }
        }catch (Exception $e) {
            $helper->log('Error: '.$e->getMessage());
            $helper->log('Trace: '.$e->getTraceAsString());
        }
    }

    private function getCollectionOrderToCancel($paymentMethod){
        $collection = Mage::getModel('sales/order')->getCollection()
            ->join(
                array('payment' => 'sales/order_payment'),
                'main_table.entity_id=payment.parent_id',
                array('payment_method' => 'payment.method')
            );

        $collection->addFieldToFilter('payment.method', $paymentMethod)
            ->addFieldToFilter('main_table.state', Mage_Sales_Model_Order::STATE_NEW);

        $validDate = date('Y-m-d', strtotime('now - 1 days'));
        $collection->addFieldToFilter('created_at',array('gteq'=>$validDate.' 00:00:00'))
            ->addFieldToFilter('created_at',array("lteq" => $validDate.' 23:59:59'));
        return $collection;

    }
}
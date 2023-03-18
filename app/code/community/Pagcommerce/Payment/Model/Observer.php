<?php

class Pagcommerce_Payment_Model_Observer{

    public function orderPlaceBefore($observer){
        $order = $observer->getEvent()->getOrder();
        if ($order && $order->getId()){
            if ($_POST['cpfcnpj']){

                $order->setData('customer_taxvat', $_POST['cpfcnpj']);
                $order->save();

                $customer = $order->getCustomer();

                if ($customer && $customer->getId()){
                    if (!$customer->getTaxvat()){
                        $customer->setData('taxvat', $_POST['cpfcnpj']);
                        $customer->save();
                    }
                }
            }
        }

    }

}
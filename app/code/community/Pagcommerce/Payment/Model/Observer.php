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


    public function prepareCreditMemo($event){
        /** @var Mage_Sales_Model_Order_Creditmemo $creditMemo */
        $creditMemo = $event->getCreditmemo();

        $order = $creditMemo->getOrder();
        $payment = $order->getPayment();
        $transactionId = $payment->getAdditionalInformation('transaction_id');

        $invoices = $order->getInvoiceCollection();

       $invoice = false;
        if ($invoices->getSize() > 0) {
            foreach ($invoices as $invoice) {
                $invoice->setTransactionId($transactionId);
            }
        }

        if($invoice){
            $creditMemo->setData('invoice', $invoice);
        }
    }
}
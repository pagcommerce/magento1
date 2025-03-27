<?php
class Pagcommerce_Payment_Model_Api_Cc extends Pagcommerce_Payment_Model_Api_AbstractApi
{
    public function processPayment(Mage_Sales_Model_Order $order, $installment, $orderTotal){
        $data = $this->getBaseData($order);
        unset($data['due_date']);
        $data['installments'] = $installment;
        $data['capture'] = '1';

        $customerIp = $_SERVER['REMOTE_ADDR'];
        if(isset($_SERVER["HTTP_CF_CONNECTING_IP"])){
            $customerIp = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }

        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $order->getPayment()->getMethodInstance()->getInfoInstance();
        if($payment->getAdditionalInformation('card_id')){
            $data['card_id'] = $payment->getAdditionalInformation('card_id');
        }else{
            $data['card_holder_name'] =  $payment->getCcOwner();
            $data['card_expiration_month'] =  $payment->getCcExpMonth();
            $data['card_expiration_year'] =  $payment->getCcExpYear();
            $data['card_number'] =  $payment->getCcNumber();
            $data['save_credit_card'] =  $payment->getAdditionalInformation('cc_save_card');
        }

        $data['card_security_code'] =  $payment->getCcCid();
        $data['customer_ip'] =  $customerIp;
        $data['card_taxvat'] =  $payment->getAdditionalInformation('document_number') ? $payment->getAdditionalInformation('document_number') : $payment->getOrder()->getCustomerTaxvat();

        $data['card_number'] = preg_replace('/[^0-9]/', '', $data['card_number']);

        if((int)$data['installments'] > 1){
            /** @var Pagcommerce_Payment_Helper_Data $helper */
            $helper = Mage::helper('pagcommerce_payment');
            $installments = $helper->getInterestsByTotal($data['order_total']);
            $currentInstallment = $installments[$installment];
            $total = $currentInstallment['total_with_interest'];
            $total = preg_replace("/[^0-9]/", "", $total);
            $data['order_total'] = $total/100;
        }

        $response = $this->sendRequest('payment-credit-card', $data);
        if(isset($response['validation_messages'])){
            foreach($response['validation_messages'] as $key => $value){
                $message = $key.': ';
                foreach($value as $error){
                    $message.= $error;
                }
                $this->addErros($message);
            }
        }else{
            return $response;

        }
        return false;
    }
}
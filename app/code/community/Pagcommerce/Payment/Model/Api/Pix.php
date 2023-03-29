<?php
class Pagcommerce_Payment_Model_Api_Pix extends Pagcommerce_Payment_Model_Api_AbstractApi
{
    public function getPixResponse(Mage_Sales_Model_Order $order){
        $data = $this->getBaseData($order);


        $helper = Mage::helper('pagcommerce_payment');
        $qtyDays = $helper->getConfig('pix', 'days');
        $dueDate = strtotime(date('Y-m-d').' + '.$qtyDays.' days');
        $dueDate = date('Y-m-d', $dueDate);

        $data['due_date'] = $dueDate;
        $data['additional_information'] = '';

        $response = $this->sendRequest('payment-pix', $data);

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
<?php
class Pagcommerce_Payment_Model_Api_Pix extends Pagcommerce_Payment_Model_Api_AbstractApi
{
    public function getPixResponse(Mage_Sales_Model_Order $order){
        $data = $this->getBaseData($order);
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
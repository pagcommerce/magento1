<?php
class Pagcommerce_Payment_Model_Api_CreditCardToken extends Pagcommerce_Payment_Model_Api_AbstractApi
{
    public function getCardByCustomerEmail($email){
        $response = $this->sendRequest('credit-card-token-by-customer/'.$email, array(), 'GET');
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
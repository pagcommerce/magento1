<?php
class Pagcommerce_Payment_Model_Api_Payment_Transaction extends Pagcommerce_Payment_Model_Api_AbstractApi
{
    public function getTransaction($transactionId){
        $uri = 'payment-transaction/'.$transactionId;
        $response = $this->sendRequest($uri, array(), 'GET');
        return $response;
    }
}
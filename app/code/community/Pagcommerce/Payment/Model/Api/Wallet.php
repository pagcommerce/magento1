<?php
class Pagcommerce_Payment_Model_Api_Wallet extends Pagcommerce_Payment_Model_Api_AbstractApi
{
    public function getWallet(){
        $uri = 'wallet/1';
        $response = $this->sendRequest($uri, array(), 'GET');
        return $response;
    }
}
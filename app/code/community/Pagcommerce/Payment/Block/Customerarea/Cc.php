<?php
class Pagcommerce_Payment_Block_Customerarea_Cc extends Mage_Core_Block_Template
{
    protected $_template = 'pagcommerce_payment/customerarea/cc.phtml';

    public function __construct(array $args = array())
    {
        parent::__construct($args);
    }


    public function getCreditCardSaved(){
        /** @var Mage_Customer_Model_Session $session */
        $session = Mage::getSingleton('customer/session');
        if($session->isLoggedIn()){
            $email  = $session->getCustomer()->getEmail();

            /** @var Pagcommerce_Payment_Model_Api_CreditCardToken $api */
            $api = Mage::getModel('Pagcommerce_Payment_Model_Api_CreditCardToken');
            $response = $api->getCardByCustomerEmail($email);
            if(!$response){
                return array();
            }
            return $response;
        }
        return array();
    }


}
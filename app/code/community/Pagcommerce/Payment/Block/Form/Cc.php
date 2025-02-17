<?php

class Pagcommerce_Payment_Block_Form_Cc extends Mage_Payment_Block_Form_Cc
{
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('pagcommerce_payment/form/cc.phtml');
        $this->setModuleName('Pagcommerce_Payment');
    }

    public function getConfigData($field, $storeId = null)
    {
        return Mage::getStoreConfig('payment/pagcommerce_payment_cc/' . $field, $storeId);
    }



    public function getCreditCardSaved(){
        /** @var Mage_Customer_Model_Session $session */
        $session = Mage::getSingleton('customer/session');
        if($session->isLoggedIn()){
            $email  = $session->getCustomer()->getEmail();

            /** @var Pagcommerce_Payment_Model_Api_CreditCardToken $api */
            $api = Mage::getModel('Pagcommerce_Payment_Model_Api_CreditCardToken');
            return $api->getCardByCustomerEmail($email);
        }
        return array();
    }
    public function getCcAvailableTypes()
    {
        return array('mastercard' => 'Mastercard');

//        $types = Mage::getModel('pagcommerce_payment/source_cctype')->toArray();
//
//        if ($method = $this->getMethod()) {
//            $availableTypes = $method->getConfigData('cc_brands');
//            if ($availableTypes) {
//                $availableTypes = explode(',', $availableTypes);
//                foreach ($types as $code=>$name) {
//                    if (!in_array($code, $availableTypes)) {
//                        unset($types[$code]);
//                    }
//                }
//            }
//        }
//        return $types;
    }

    public function getInterests()
    {
        /** @var Pagcommerce_Payment_Helper_Data $helper */
        $helper = Mage::helper('pagcommerce_payment');
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/cart')->getQuote();
        if(!$quote->getId()) //is ADMIN
           $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();

        return $helper->getInterestsByTotal($quote->getGrandTotal());
    }



}

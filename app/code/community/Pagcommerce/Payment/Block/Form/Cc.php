<?php

class Pagcommerce_Payment_Block_Form_Cc extends Codecia_Apps_Block_Abstract_Payment_Form_Cc
{
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('pagcommerce_payment/form/cc.phtml');
        $this->setModuleName('Mage_Payment');
    }

    public function getConfigData($key)
    {
        return Mage::getStoreConfig('payment/pagcommerce_payment_cc/' . $key);
    }


    public function getCcAvailableTypes()
    {
        return ['mastercard' => 'Mastercard'];

        $types = Mage::getModel('codecia_cielo/source_cctype')->toArray();

        if ($method = $this->getMethod()) {
            $availableTypes = $method->getConfigData('cc_brands');
            if ($availableTypes) {
                $availableTypes = explode(',', $availableTypes);
                foreach ($types as $code=>$name) {
                    if (!in_array($code, $availableTypes)) {
                        unset($types[$code]);
                    }
                }
            }
        }
        return $types;
    }

    public function getInterests()
    {
        /** @var Codecia_Cielo_Helper_Data $helper */
        $helper = Mage::helper('codecia_cielo');
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getSingleton('checkout/cart')->getQuote();
        if(!$quote->getId()) //is ADMIN
           $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();

        return $helper->getInterestsByTotal($quote->getGrandTotal());
    }



}

<?php

class Pagcommerce_Payment_Model_Source_Paymentaction
{

    public function toOptionArray()
    {
        return array(
            array(
                'value' => Pagcommerce_Payment_Model_Method_Cc::ACTION_AUTHORIZE,
                'label' => Mage::helper('pagcommerce_payment')->__('Somente Autorizar')
            ),
            array(
                'value' => Pagcommerce_Payment_Model_Method_Cc::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('pagcommerce_payment')->__('Autorizar e Capturar')
            ),
        );
    }
}


?>
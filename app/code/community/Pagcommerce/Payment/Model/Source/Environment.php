<?php

class Pagcommerce_Payment_Model_Source_Environment
{
    const PRODUCTION = 'production';
    const TEST = 'development';


    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $helper = Mage::helper('pagcommerce_payment');
        return array(
            array('value' => self::TEST, 'label' => $helper->__('Teste (Homologação)')),
            array('value' => self::PRODUCTION, 'label' => $helper->__('Produção')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $helper = Mage::helper('pagcommerce_payment');
        return array(
            self::TEST => $helper->__('Teste (Homologação)'),
            self::PRODUCTION => $helper->__('Produção'),
        );
    }

}

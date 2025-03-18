<?php

class Pagcommerce_Payment_Model_Source_Fee_Type
{
    const TYPE_PARCEL = 'parcel';
    const TYPE_TOTAL = 'total';


    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $helper = Mage::helper('pagcommerce_payment');
        return array(
            array('value' => self::TYPE_PARCEL, 'label' => $helper->__('Por Parcela')),
            array('value' => self::TYPE_TOTAL, 'label' => $helper->__('Pelo Total do Pedido')),
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
            self::TYPE_PARCEL => $helper->__('Por Parcela'),
            self::TYPE_TOTAL => $helper->__('Pelo Total do Pedido'),
        );
    }

}

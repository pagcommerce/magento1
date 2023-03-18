<?php
/**
 * Created by PhpStorm.
 * User: bruno
 * Date: 28/06/19
 * Time: 09:05
 */

class Pagcommerce_Payment_Model_Source_Customerattributes
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $attributes = Mage::getModel('customer/customer')->getAttributes();
        $result = array();
        foreach ($attributes as $attribute) {
            if ($attribute->getId() && $attribute->getStoreLabel())
            {
                $result[] = array('value' => $attribute->getAttributeCode(), 'label' => $attribute->getStoreLabel());
            }
        }

        return $result;
    }

}
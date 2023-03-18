<?php
/**
 * Created by PhpStorm.
 * User: bruno
 * Date: 28/06/19
 * Time: 09:05
 */

class Pagcommerce_Payment_Model_Source_Addresslines
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $result = array();
        $result[] = array('value' => 1, 'label' => 'Linha 1');
        $result[] = array('value' => 2, 'label' => 'Linha 2');
        $result[] = array('value' => 3, 'label' => 'Linha 3');
        $result[] = array('value' => 4, 'label' => 'Linha 4');

        return $result;
    }

}
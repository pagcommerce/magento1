<?php

class Pagcommerce_Payment_Block_Info_Cc extends Mage_Payment_Block_Info_Cc
{

    protected function _construct() {
        parent::_construct();
        $this->setTemplate('pagcommerce_payment/info/cc.phtml');
        $this->setModuleName('Pagcommerce_Payment');
        $this->setData('showtitle', true);
    }
}

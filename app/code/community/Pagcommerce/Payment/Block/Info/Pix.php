<?php

class Pagcommerce_Payment_Block_Info_Pix extends Mage_Payment_Block_Info_Cc
{
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('pagcommerce_payment/info/pix.phtml');
        $this->setModuleName('Mage_Payment');
        $this->setData('showtitle', true);
    }
}
?>
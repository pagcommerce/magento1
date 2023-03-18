<?php

class Pagcommerce_Payment_Block_Form_Pix extends Mage_Payment_Block_Form
{
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('pagcommerce_payment/form/pix.phtml');
    }
}

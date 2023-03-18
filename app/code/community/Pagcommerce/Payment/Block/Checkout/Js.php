<?php
/**
 * Created by PhpStorm.
 * User: eric
 * Date: 05/10/17
 * Time: 14:37
 */
class Pagcommerce_Payment_Block_Checkout_Js extends Mage_Core_Block_Template {

    public function __construct(array $args = array())
    {
        parent::__construct($args);
        $this->setTemplate('pagcommerce_payment/checkout/js.phtml');
    }
}
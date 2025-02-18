<?php
require_once(Mage::getModuleDir('controllers','Mage_Customer').DS.'AccountController.php');
class Pagcommerce_Payment_CcController extends Mage_Customer_AccountController
{
    public function indexAction(){
        $this->loadLayout();
        $this->renderLayout();
    }
}
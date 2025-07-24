<?php

class Pagcommerce_Payment_Model_Source_Order_Status_Canceled extends Mage_Adminhtml_Model_System_Config_Source_Order_Status
{
    protected $_stateStatuses = Mage_Sales_Model_Order::STATE_CANCELED;
}

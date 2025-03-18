<?php
class Pagcommerce_Payment_CheckController extends Mage_Core_Controller_Front_Action
{
    public function validateCpfCnpjAction(){
        $helper = Mage::helper('pagcommerce_payment');
        $param = $this->getRequest()->getParams();

        $response = array(
            'status' => false
        );

        if ($param['documento']){
            $cpfcnpj = str_replace(array('-','.','/'), array('', '', ''), trim($param['documento']));
            if (strlen($cpfcnpj) == 11){
                if ($helper->validarCpf($cpfcnpj)){
                    $response['status'] = true;
                } else{
                    $response['status'] = false;
                }
            } else{
                if ($helper->validarCnpj($cpfcnpj)){
                    $response['status'] = true;
                } else{
                    $response['status'] = false;
                }
            }
        }
        echo json_encode($response);
    }


    public function validatebinAction(){
        /** @var Pagcommerce_Payment_Helper_Data $helper */
        $helper = Mage::helper('pagcommerce_payment');
        $param = $this->getRequest()->getParams();

        $response = array(
            'status' => true,
            'message' => 'Todas bandeiras aceitas'
        );

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));

//        if ($param['number']){
//            $cardNumber = str_replace(array('-','.','/'), array('', '', ''), trim($param['number']));
//
//            /** @var Pagcommerce_Payment_Model_CreditCard_Issuer $issuer */
//            $issuer = Mage::getModel('Pagcommerce_Payment_Model_CreditCard_Issuer');
//            $brand = $issuer->getCardIssuer($cardNumber);
//
//            if($brand){
//                $allowedBrands = $helper->getConfigCc('brands');
//                $allowedBrands = explode(',', $allowedBrands);
//                if(in_array($brand, $allowedBrands)){
//                    $response = array(
//                        'status' => true,
//                        'message' => 'Bandeira Permitida'
//                    );
//                }else{
//                    /** @var Pagcommerce_Payment_Model_Source_CreditCard_Brand $sourceCards */
//                    $sourceCards = Mage::getModel('Pagcommerce_Payment_Model_Source_CreditCard_Brand');
//                    $allCards = $sourceCards->toArray();
//
//                    $message = 'Não é permitido utilizar cartões da bandeira '.$allCards[$brand].'. Por favor utilize outro cartão.';
//                    $response = array(
//                        'status' => false,
//                        'message' => $message
//                    );
//                }
//            }
//
//        }
//        echo json_encode($response);
    }
}
?>
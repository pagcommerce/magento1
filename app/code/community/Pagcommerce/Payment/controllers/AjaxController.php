<?php


class Pagcommerce_Payment_AjaxController extends Mage_Core_Controller_Front_Action
{
    public function removeccAction(){
        $response = array(
            'status' => false,
            'message' => 'Acesso não autorizado'
        );
        $cardId = $this->getRequest()->getParam('id');
        if($cardId){
            /** @var Mage_Customer_Model_Session $session */
            $session = Mage::getModel('customer/session');
            if($session->isLoggedIn()){
                /** @var Pagcommerce_Payment_Model_Api_Cc $api */
                $api = Mage::getModel('pagcommerce_payment/api_cc');

                $tokens = $api->sendRequest('credit-card-token-by-customer/'.$session->getCustomer()->getEmail(), array(), 'GET');
                if($tokens){
                    $isValid = false;
                    foreach($tokens as $token){
                        if($token['id'] == $cardId){
                            $isValid = true;
                        }
                    }
                    if($isValid){
                        $response['status'] = true;
                        $response['message'] = 'Cartão removido com sucesso';
                    }else{
                        $response['message'] = 'Cartão de crédito não encontrado. Não é possível remover';
                    }

                }else{
                    $response['message'] = 'Cartão de crédito inválido. Não é possível remover';
                }
            }
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }
}
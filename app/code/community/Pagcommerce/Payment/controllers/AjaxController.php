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
                        $responseDelete = $api->sendRequest('credit-card-token/'.$cardId, array(), 'DELETE');
                        if($api->getErrors()){
                            $response['message'] = $api->getErrors();
                        }else{
                            $response['status'] = true;
                            $response['message'] = 'Cartão removido com sucesso';
                        }

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


    public function getinstallmentsquoteAction(){

        $response = array(
            'status' => false,
            'installments' => false
        );

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('checkout/session')->getQuote();

        /** @var Pagcommerce_Payment_Helper_Data $helper */
        $helper = Mage::helper('pagcommerce_payment');
        $installments = $helper->getInterestsByTotal($quote->getGrandTotal());
        if($installments){
            foreach($installments as $key => $value){
                $installments[$key]['label'] = strip_tags($value['label']);
            }
            $response['installments'] = $installments;
            $response['status'] = true;
        }

        $this->getResponse()->clearHeaders()->setHeader('Content-type', 'application/json', true);
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));

    }
}
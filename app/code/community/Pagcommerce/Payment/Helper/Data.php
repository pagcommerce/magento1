<?php

class Pagcommerce_Payment_Helper_Data extends Mage_Core_Helper_Abstract
{

    /** @return string */
    public function getConfig($method, $key){
        return Mage::getStoreConfig('payment/pagcommerce_payment_'.$method.'/'.$key);
    }

    /** @return string */
    public function getDefaultConfig($key){
        return Mage::getStoreConfig('payment/pagcommerce_payment/'.$key);
    }


    public function log($comment){
        Mage::log($comment, null, 'pagcommerce_payment.log');
    }


    public function getConfigCc($config){
        return Mage::getStoreConfig('payment/pagcommerce_payment_cc/'.$config);
    }

    public function unserializeConfigData($data){
        if(is_string($data))
            $data = unserialize($data);

        if(!is_array($data))
            return false;

        $keys = array_keys($data);
        if(!$keys) return false;

        $unserializedData = array();

        foreach($keys as $key){
            for($i=0; $i<sizeof($data[$key]); $i++){
                $unserializedData[$i][$key] = $data[$key][$i];
            }
        }

        return $unserializedData;
    }

    private function formatPrice($price){
        return number_format($price, 2, ',', '');
    }


    public function getInterestsByTotal($orderTotal){
        $configInterest = $this->getConfigCc('installments');
        /** @var Mage_Core_Helper_Data $helperCore */
        $helperCore = Mage::helper('core');


        /** @var Mage_Core_Helper_Data $helperCore */
        $helperCore = Mage::helper('core');
        if($helperCore->isModuleEnabled('Codecia_Productinstallment')){
            /** @var Codecia_Productinstallment_Helper_Data $helperInstallment */
            $helperInstallment = Mage::helper('codecia_productinstallment');
            $installmentsCodecia = $helperInstallment->getInterestsByTotal($orderTotal);
            return $installmentsCodecia;
        }

        $installments = array();
        if($configInterest){
            $arraConfig = $this->unserializeConfigData($configInterest);
            if($arraConfig){
                $parcelsAllowed = array();
//                $configUsed = false;
                for($i=0; $i<sizeof($arraConfig); $i++){
                    if($i ==0) continue;
                    if($orderTotal >=  $arraConfig[$i]['de'] && $orderTotal <= $arraConfig[$i]['ate']){
//                        $configUsed = $arraConfig[$i];
                        $parcelsAllowed[$arraConfig[$i]['parcela']] = $arraConfig[$i];
                    }
                }

                if($parcelsAllowed){

                    $labelParcelWithFee = $this->__(Mage::helper('pagcommerce_payment')->getConfigCc('label_install'));
                    $labelParcelNoFee =  $this->__(Mage::helper('pagcommerce_payment')->getConfigCc('label_nofee'));
                    $feeType = Mage::helper('pagcommerce_payment')->getConfigCc('fee_type');

                    foreach($parcelsAllowed as $key => $value){
                        $juros = (float)$value['juros'];
                        if($juros > 0){
                            //tem juros

                            if($feeType == Pagcommerce_Payment_Model_Source_Fee_Type::TYPE_PARCEL){
                                $jurosByParcel = ($orderTotal * ($juros/100)) * $value['parcela'];
                            }else{
                                $jurosByParcel = ($orderTotal * ($juros/100));

                            }

                            $totalWithJuros = $orderTotal + $jurosByParcel;
                            $priceByParcel =  $totalWithJuros/$value['parcela'];


                            $label = $key.$this->__('x de ').$helperCore->currency($priceByParcel, true, false);
                            if($labelParcelWithFee){
                                $labelParcel = sprintf($labelParcelWithFee, $helperCore->currency($totalWithJuros, true, false));
                                $label.= $labelParcel;
                            }

                            $installments[$value['parcela']] = array(
                                'parcel' => $value['parcela'],
                                'price_byparcel' => $this->formatPrice($priceByParcel),
                                'label' => $label,
                                'total_with_interest' => $this->formatPrice($totalWithJuros)
                            );
                        }else{
                            //não tem juros
                            $priceByParcel =  $orderTotal/$key;
                            $label = $key.$this->__('x de ').$helperCore->currency($priceByParcel, true, false);

                            if($labelParcelNoFee && $key > 1){
                                $labelParcel = sprintf($labelParcelNoFee, $helperCore->currency($orderTotal, true, false));
                                $label.= $labelParcel;
                            }

                            $installments[$value['parcela']] = array(
                                'parcel' => $value['parcela'],
                                'price_byparcel' => $this->formatPrice($priceByParcel),
                                'label' => $label,
                                'total_with_interest' => $this->formatPrice($orderTotal)
                            );
                        }
                    }
                }

                return $installments;

//                if($configUsed){
//                    //$helperCore->currency($orderTotal, true, false);
//                    for($i=1; $i<= $configUsed['parcela']; $i++){
//                        if($i == 1){
//                            $installments[$i] = array(
//                                'parcel' => $i,
//                                'price_byparcel' => $this->formatPrice($orderTotal),
//                                'label' => $i.$this->__('x de ').$helperCore->currency($orderTotal, true, false),
//                                'total_with_interest' => $this->formatPrice($orderTotal)
//                            );
//                        }else{
//                            $jurosByParcel = (($orderTotal * $configUsed['juros'])/100 ) * ($i-1);
//                            $totalWithJuros = $orderTotal + $jurosByParcel;
//                            $priceByParcel =  $totalWithJuros/$i;
//
//                            $label = $i.$this->__('x de ').$helperCore->currency($priceByParcel, true, false);
//                            if($jurosByParcel){
//                                $labelParcel = Mage::helper('pagcommerce_payment')->getConfigCc('label_install');
//                                if($labelParcel){
//                                    $labelParcel = $this->__($labelParcel);
//                                    $labelParcel = sprintf($labelParcel, $helperCore->currency($totalWithJuros, true, false));
//                                    $label.= $labelParcel;
//                                }
//                            }else{
//                                $labelParcel = Mage::helper('pagcommerce_payment')->getConfigCc('label_nofee');
//                                if($labelParcel){
//                                    $labelParcel = $this->__($labelParcel);
//                                    $labelParcel = sprintf($labelParcel, $helperCore->currency($totalWithJuros, true, false));
//                                    $label.= $labelParcel;
//                                }
//                            }
//                            $installments[$i] = array(
//                                'parcel' => $i,
//                                'price_byparcel' => $this->formatPrice($priceByParcel),
//                                'label' => $label,
//                                'total_with_interest' => $this->formatPrice($totalWithJuros)
//                            );
//
//
//                        }
//                    }
//
//                }else{
//                    $i = 1;
//                    return array(
//                        $i => array(
//                            'parcel' => $i,
//                            'price_byparcel' => $orderTotal,
//                            'label' => $i.$this->__('x de ').$helperCore->currency($orderTotal, true, false),
//                            'total_with_interest' => $this->formatPrice($orderTotal)
//                        )
//                    );
//                }
            }
            return $installments;
        }

        return array();

    }



    public function validarCpf($cpf){

        // Extrai somente os números
        $cpf = preg_replace( '/[^0-9]/is', '', $cpf );

        // Verifica se foi informado todos os digitos corretamente
        if (strlen($cpf) != 11) {
            return false;
        }
        // Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        // Faz o calculo para validar o CPF
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        return true;
    }

    public function validarCnpj($cnpj)
    {
        $cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);
        // Valida tamanho
        if (strlen($cnpj) != 14){
            return false;
        }

        // Valida primeiro dígito verificador
        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++)
        {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $resto = $soma % 11;
        if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
            return false;
        }

        // Valida segundo dígito verificador
        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++)
        {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $resto = $soma % 11;
        return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
    }

    public function canShowInstallmentsConfig(){
        return false;
    }
}

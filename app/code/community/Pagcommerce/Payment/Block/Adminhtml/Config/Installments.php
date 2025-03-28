<?php


class Pagcommerce_Payment_Block_Adminhtml_Config_Installments extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected $_addRowButtonHtml = array();
    protected $_removeRowButtonHtml = array();

    public function _construct()
    {
        parent::_construct(); // TODO: Change the autogenerated stub

    }

    
    /** @return Pagcommerce_Payment_Helper_Data */
    private function __getHelper(){
        return Mage::helper('pagcommerce_payment');
    }

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        if($this->_isCodeciaProductInstallmentEnabled()){
            $url = Mage::helper('adminhtml')->getUrl('adminhtml/system_config/edit', array(
                'section' => 'codecia_productinstallment',
            ));
            return '<div style="background-color: #dedede;padding: 10px;">Módulo de Parcelamento Codecia Instalado.<br> Configure o parcelamento em:<br> <strong><a href="'.$url.'">Codecia - Parcelamento</a></strong></div>';
        }
        $this->setElement($element);
        
        $html = '<script type="text/javascript">';
            $html .= 'var countCcInstallmentsPag = 1;';
            $html .= 'var templateCcInstallmentsPag = \'\';';
            $html .= 'templateCcInstallmentsPag += \'<tr id="ccinstallmentspag_XXX" style="display:BBB">\';';
            $html .= 'templateCcInstallmentsPag += \'<td><input type="text" name="groups[pagcommerce_payment_cc][fields][installments][value][de][]" value="VALUEAMOUNT" class="input-text" style="width:100px"></td>\';';
            $html .= 'templateCcInstallmentsPag += \'<td><input type="text" name="groups[pagcommerce_payment_cc][fields][installments][value][ate][]" value="VALUEATE" class="input-text" style="width:100px"></td>\';';
            $html .= 'templateCcInstallmentsPag += \'<td><input type="text" name="groups[pagcommerce_payment_cc][fields][installments][value][parcela][]" value="VALUEPARCEL" class="input-text" style="width:100px"></td>\';';
            $html .= 'templateCcInstallmentsPag += \'<td><input type="text" name="groups[pagcommerce_payment_cc][fields][installments][value][juros][]" value="VALUEINTEREST" class="input-text" style="width:100px"></td>\';';
            $html .= 'templateCcInstallmentsPag += \'<td><button onclick="removeCCInstallmentsPag(this);"  data-id="ccinstallmentspag_ZZZ" class="scalable delete" type="button"><span><span><span>'.$this->__getHelper()->__('Excluir').'</span></span></span></button></td>\';';
            $html .= 'templateCcInstallmentsPag += \'</tr>\';';

            $html .= ' ';
            $html .= ' ';

            $html .= 'function addCCInstallmentsLinePag(valorDe, valorAte, parcela, juros, vardisplay){';
                  $html.= 'let currentTemplate = templateCcInstallmentsPag;';
                  $html.= 'currentTemplate = currentTemplate.replace("VALUEAMOUNT", valorDe);';
                  $html.= 'currentTemplate = currentTemplate.replace("VALUEATE", valorAte);';
                  $html.= 'currentTemplate = currentTemplate.replace("VALUEPARCEL", parcela);';
                  $html.= 'currentTemplate = currentTemplate.replace("VALUEINTEREST", juros);';
                  $html.= 'currentTemplate = currentTemplate.replace("XXX", countCcInstallmentsPag);';
                  $html.= 'currentTemplate = currentTemplate.replace("ZZZ", countCcInstallmentsPag);';
                  $html.= 'currentTemplate = currentTemplate.replace("BBB", vardisplay);';
                  $html.= 'countCcInstallmentsPag++;';
                  $html .= 'Element.insert($(\'ccinsttablepag\'),{bottom : currentTemplate});';
                $html .= '';
                $html .= '';
            $html .= '}';


            $html .= 'function removeCCInstallmentsPag(el){';
                $html.= 'let dataid  = el.readAttribute(\'data-id\');';
                $html .= 'Element.remove($(dataid));';
            $html .= '}';
        $html .= '</script>';


        $html.= '<div class="grid" id="grid_5c26227b08e19" style="">';
            $html .='<table id="ccinsttablepag" cellpadding="0" cellspacing="0" class="border">';
                $html.='<tbody>';

                    $html.='<tr class="headings" id="headings_5c26227b08e19">';
                        //$html.='<th>Moeda</th>';
                        $html.='<th>'.$this->__getHelper()->__('Valor pedido de').'</th>';
                        $html.='<th>'.$this->__getHelper()->__('Valor pedido até').'</th>';
                        $html.='<th>'.$this->__getHelper()->__('Quantidade de parcelas').'</th>';
                        $html.='<th>'.$this->__getHelper()->__('Taxa de juro (%)').'</th>';
                        $html.='<th></th>';
                    $html.='</tr>';
                $html.='</tbody>';
            $html.='</table>';
            $html.='<br>';
            $html .= $this->_getAddRowButtonHtml();
        $html.='</div>';


        //adicionan o primeiro vazio (bugFIX - Não salva sem valores).
        $html .= '<script type="text/javascript">';
            $html .= "addCCInstallmentsLinePag('','', '','', 'none');";
        $html .= '</script>';


        //adiciona os elementos salvos
        if ($this->_getValue('de')) {
            $html .= '<script type="text/javascript">';
                foreach ($this->_getValue('de') as $i => $f) {
                        if($i == 0) continue;
                        $valorDe = $this->_getValue('de/' . $i);
                        $valorAte = $this->_getValue('ate/' . $i);
                        $parcelas = $this->_getValue('parcela/' . $i);
                        $juros = $this->_getValue('juros/' . $i);

                        $html .= "addCCInstallmentsLinePag('".$valorDe."','".$valorAte."','".$parcelas."', '".$juros."','contents');";
                }
            $html .= '</script>';
        }



        return $html;
    
    
    }


    protected function _getDisabled()
    {
        if($this->_isCodeciaProductInstallmentEnabled()){
            return '';
        }
        return $this->getElement()->getDisabled() ? ' disabled' : '';
    }

    protected function _getValue($key)
    {
        if($this->_isCodeciaProductInstallmentEnabled()){
            return '';
        }
        return $this->getElement()->getData('value/' . $key);
    }

    protected function _getSelected($key, $value)
    {
        if($this->_isCodeciaProductInstallmentEnabled()){
            return '';
        }
        return $this->getElement()->getData('value/' . $key) == $value ? 'selected="selected"' : '';
    }

    protected function _getAddRowButtonHtml($container = false, $template = false, $title = 'Adicionar')
    {
        if($this->_isCodeciaProductInstallmentEnabled()){
            return '';
        }


        $title = $this->__getHelper()->__($title);
        if (!isset($this->_addRowButtonHtml[$container])) {
            $this->_addRowButtonHtml[$container] = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setType('button')
                ->setClass('add ' . $this->_getDisabled())
                ->setLabel($this->__($title))
                ->setOnClick("addCCInstallmentsLinePag('','', '','', 'contents');")
                //->setOnClick("Element.insert($('" . $container . "'), {bottom: $('" . $template . "').innerHTML})")
                ->setDisabled($this->_getDisabled())
                ->toHtml();
        }
        return $this->_addRowButtonHtml[$container];
    }

    public function _isCodeciaProductInstallmentEnabled(){
        /** @var Mage_Core_Helper_Data $coreHelper */
        $coreHelper = Mage::helper('core');
        if($coreHelper->isModuleEnabled('Codecia_Productinstallment')){
            return true;
        }

        return false;
    }
}
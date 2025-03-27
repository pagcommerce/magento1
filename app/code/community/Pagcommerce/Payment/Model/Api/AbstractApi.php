<?php

abstract class Pagcommerce_Payment_Model_Api_AbstractApi{
    const ENV_SANDBOX = 'sandbox';
    const ENV_PRODUCTION = 'production';

    private $_key = '';
    private $_secret = '';
    private $_erros = array();
    private $_environment = '';


    public function __construct(){
        /** @var Pagcommerce_Payment_Helper_Data $helper */
        $helper = Mage::helper('pagcommerce_payment');
        $this->_environment = $helper->getDefaultConfig('enviroment');
        $this->_key = $helper->getDefaultConfig('api_key');
        $this->_secret = $helper->getDefaultConfig('api_secret');

    }

    public function sendRequest($uri, $data = array(), $method = 'POST'){
        $this->_erros = array();
        if($this->hasCredentials()){
            if($this->getEnvironment()){
                $additionalHeaders = array();

                if($method == 'POST'){
                    if(!$data){
                        $this->addErros('É necessário informar o POSTDATA');
                        return false;
                    }else{
                        if(is_array($data) || is_object($data) && $method == 'POST'){
                            $data = json_encode($data);
                        }
                    }
                }


                $ch = curl_init($this->getApiUrl().$uri);
                curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . base64_encode($this->getKey().':'.$this->getSecret())
                ));
                curl_setopt($ch, CURLOPT_TIMEOUT, 40);
                switch ($method){
                    case 'POST':
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                        break;
                    case 'DELETE':
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                        curl_setopt($ch, CURLOPT_ENCODING, '');
                        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                        break;
                }

                $logEnabled = Mage::getStoreConfigFlag('payment/pagcommerce_payment/enable_log');
                if($logEnabled){
                    if(is_string($data)){
                        Mage::log('REQUEST :'.$data, null, 'pagcommerce_payment.log');
                    }

                }
                $response = curl_exec($ch);
                if($logEnabled){
                    Mage::log('RESPONSE :'.$response, null, 'pagcommerce_payment.log');
                }
                $error = curl_error($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if(!$error && $response){
                    $response = json_decode($response, true);
                    if($httpCode == 404){
                        if(isset($response['detail'])){
                            $this->addErros($response['detail']);
                        }else{
                            $this->addErros('Nenhum registro encontrado');
                        }

                    }else{
                        if($httpCode == 403){
                            $this->addErros('Acesso não autorizado');
                        }else{
                            return $response;
                        }
                    }

                }else{
                    $this->addErros($error);
                }
            }else{
                $this->addErros('É necessário setar o ambiente da API');
            }
        }else{
            $this->addErros('É necessário informar as credenciais de acesso a API');
        }
        return false;
    }


    /** @return boolean */
    private function hasCredentials(){
        return $this->getKey() && $this->getSecret();
    }

    /** @return string */
    private function getApiUrl(){
        $sandboxUrl = 'https://api-sandbox.pagcommerce.com.br/';
        return $this->getEnvironment() == Pagcommerce_Payment_Model_Source_Environment::TEST ? $sandboxUrl : 'https://api.pagcommerce.com.br/';
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->_secret;
    }

    /**
     * @param string $secret
     */
    public function setSecret($secret)
    {
        $this->_secret = $secret;
    }


    /** @return $this */
    protected function addErros($errorMessage){
        $this->_erros[] = $errorMessage;
        return $this;
    }

    /** @return string */
    public function getErrors(){
        if($this->hasErros()){
            return implode('<br>', $this->_erros);
        }
        return '';
    }

    /** @return boolean */
    public function hasErros(){
        return sizeof($this->_erros) > 0;
    }

    /**
     * @return string
     */
    protected function getEnvironment()
    {
        return $this->_environment;
    }

    /**
     * @return string
     */
    protected function getKey()
    {
        return $this->_key;
    }

    /**
     * @return string
     */
    protected function formatCpfCnpj($cpfCnpj){
        $cpfCnpj = trim($cpfCnpj);
        $cpfCnpj = str_replace(array('-', '.', '/'), array('', '', ''), $cpfCnpj);
        return $cpfCnpj;
    }

    protected function getCustomerTaxVat(Mage_Sales_Model_Order $order){
        $taxVat = $order->getCustomerTaxvat();
        if(!$taxVat){
            $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
            /** @var Pagcommerce_Payment_Helper_Data $helper */
            $helper = Mage::helper('pagcommerce_payment');
            $attributeTaxVat = $helper->getDefaultConfig('customer_taxvat_attribute');
            $taxVat = $customer->getData($attributeTaxVat);
        }

        if(!$taxVat && $this->_environment == Pagcommerce_Payment_Model_Source_Environment::TEST){
            $taxVat = '039.647.320-28';
        }
        return $this->formatCpfCnpj($taxVat);
    }

    protected function formatTelephone($phoneNumber){
        return str_replace(array('(', ')', ' ', '-'), '', $phoneNumber);
    }

    protected function formatCEP($cep){
        return str_replace(array('(', ')', ' ', '-'), '', $cep);
    }

    /** @return Pagcommerce_Payment_Helper_Data */
    protected function getHelper(){
        return Mage::helper('pagcommerce_payment');
    }

    public function formatCurrency($amount){
        return number_format($amount, 2, '.', '');
    }

    public function getBaseData(Mage_Sales_Model_Order $order){
        $address = $order->getIsVirtual() ? $order->getBillingAddress() : $order->getShippingAddress();
        $telephone = $this->formatTelephone($address->getTelephone());
        $configStret = $this->getHelper()->getDefaultConfig('customer_address_street');
        $configNumber = $this->getHelper()->getDefaultConfig('customer_address_number');
        $configDistrict = $this->getHelper()->getDefaultConfig('customer_address_district');
        $configStretComplement = $this->getHelper()->getDefaultConfig('customer_address_complement');

        if(!$telephone){
            $telephone = '1130902373';
        }

        $taxvat = $order->getCustomerTaxvat();
        $taxvat = $this->formatCpfCnpj($taxvat);
        $data = array(
            'customer_name' => $order->getCustomerName(),
            'customer_email' => $order->getCustomerEmail(),
            'customer_type' => strlen($taxvat) > 12 ? 'PJ' : 'PF',
            'customer_taxvat' => $taxvat,
            'customer_phone'  => $telephone,
            'customer_address' => array(
                'postalcode' => $address->getPostcode(),
                'street' => $address->getStreet($configStret),
                'number' =>  $address->getStreet($configNumber),
                'complement' => $address->getStreet($configStretComplement),
                'district' =>$address->getStreet($configDistrict),
                'city' => $address->getCity(),
                'uf' => $address->getRegionCode(),
                'country' => $address->getCountry(),
            ),
            'reference_id' => $order->getIncrementId(),
            'order_total' => $this->formatCurrency($order->getGrandTotal()),
            'due_date' => date('Y-m-d'),
        );

        $items = array();

        /** @var Mage_Sales_Model_Order_Item $orderItem */
        foreach($order->getAllVisibleItems() as $orderItem){
            $items[] = array(
                'id' => $orderItem->getProduct()->getSku(),
                'name' => $orderItem->getName(),
                'qty' => (int)$orderItem->getQtyOrdered(),
                'unit_price' =>  $this->formatCurrency($orderItem->getPrice()),
                'total' =>  $this->formatCurrency($orderItem->getRowTotal())
            );
        }

        $data['order_items'] = $items;
        $data['shipment'] = array(
            'shipment_price' => $order->getIsVirtual() ? '0' : $this->formatCurrency($order->getShippingAmount()),
            'shipment_address' => array(
                'postalcode' => $address->getPostcode(),
                'street' => $address->getStreet($configStret),
                'number' =>  $address->getStreet($configNumber),
                'complement' => $address->getStreet($configStretComplement),
                'district' =>$address->getStreet($configDistrict),
                'city' => $address->getCity(),
                'uf' => $address->getRegionCode(),
                'country' => $address->getCountry(),
            )
        );
        $data['notification_url'] = Mage::app()->getStore()->getUrl('pagcommerce_payment/standard/notification');
        return $data;
    }


    /** @return boolean */
    public function refundOrder(Mage_Sales_Model_Order $order){

        $payment = $order->getPayment();
        $transactionID = $payment->getAdditionalInformation('transaction_id');

        if($transactionID){
            $transaction = $this->sendRequest('payment-transaction/'.$transactionID, array(), 'GET');
            if($transaction && isset($transaction['id'])){
                if($transactionID == $transaction['id']){
                    if($transaction['status'] == 'approved'){
                        if($transaction['transaction_type'] == 'cc' || $transaction['transaction_type'] == 'pix'){
                            $response = $this->sendRequest('payment-refund', array('transaction_id' =>$transaction['id']));
                            if($response && isset($response['refunded'])){
                                if($response['refunded']){
                                    return true;
                                }
                            }else{
                                throw new Exception("Pagcommerce: Não é possível estornar essa transação. Por favor tente novamente. Se o problema persistir, utilize o modo offline e estorne a transação dentro do Painel Pagcommerce");
                            }

                        }else{
                            throw new Exception("Pagcommerce: Não é possível estornar esse tipo de transação. Só é permitido estornar vendas por Pix e por Cartão de Crédito");
                        }

                    }else{
                        throw new Exception('Pagcommerce: Não é possível estornar essa transação porque ela não foi paga');
                    }
                }else{
                    throw new Exception("Pagcommerce: Transação inválida. Não é possível estornar");
                }
            }else{
                throw new Exception("Pagcommerce: Transação não encontrada. Não é possível estornar");
            }
        }

        return false;
    }
}   
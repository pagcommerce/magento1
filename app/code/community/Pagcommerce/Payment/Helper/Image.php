<?php

class Pagcommerce_Payment_Helper_Image extends Mage_Core_Helper_Abstract
{

    public function convertBase64ToImage($b64, Mage_Sales_Model_Order $order){
        $b64 = str_replace('data:image/png;base64,', '', $b64);
        $bin = base64_decode($b64);
        $im = imageCreateFromString($bin);
        if (!$im) {
            return false;
        }

        $imgName = $order->getId().'-'.$order->getIncrementId().'-'.$order->getCustomerId().'.png';

        $imageSavePath = 'media'.DIRECTORY_SEPARATOR.'pagcommerce_payment';
        if(!is_dir($imageSavePath)){
            mkdir($imageSavePath);
        }

        $imageSavePath = $imageSavePath.DIRECTORY_SEPARATOR.$imgName;
        if(!is_file($imageSavePath)){
            imagepng($im, $imageSavePath, 0);
        }
        return Mage::getBaseUrl('media').'pagcommerce_payment/'.$imgName;
    }
}
<?php
/**
 * This file is released under a custom license by Avenla Oy.
 * All rights reserved
 * 
 * License and more information can be found at http://productdownloads.avenla.com/magento-modules/klarna-checkout/ 
 * For questions and support - klarna-support@avenla.com
 * 
 * @category   Avenla
 * @package    Avenla_KlarnaCheckout
 * @copyright  Copyright (c) Avenla Oy
 * @link       http://www.avenla.fi 
 */

/**
 * Avenla KlarnaCheckout
 *
 * @category   Avenla
 * @package    Avenla_KlarnaCheckout
 */

class Avenla_KlarnaCheckout_Model_Validator extends Mage_Core_Model_Abstract
{   

    /**
     *  Parse Klarna order for validation
     *
     *  @return object
     */
	public function parseValidationPost()
    {
        $rawrequestBody = file_get_contents('php://input');

        if (mb_detect_encoding($rawrequestBody, 'UTF-8', true)){
            $order = json_decode($rawrequestBody);
        }
        else {
            $rawrequestBody = iconv("ISO-8859-1", "UTF-8", $rawrequestBody);
            $order = json_decode($rawrequestBody);
        }

        return $order;
    }

    /**
     *  Validate quote
     *
     *  @param  Mage_Sales_Model_Quote
     *  @return bool
     */
    public function validateQuote($quote, $ko)
    {
        if(!isset($ko->shipping_address->phone) || !isset($ko->billing_address->phone)){
            $msg = Mage::helper('klarnaCheckout')->__('Please fill in your phone number.');
            Mage::getSingleton('core/session')->addError($msg);
            return false;
        }
        
        if (!$quote->isVirtual()){
            $address = $quote->getShippingAddress();
            $method= $address->getShippingMethod();
            $rate  = $address->getShippingRateByCode($method);
            
            if (!$quote->isVirtual() && (!$method || !$rate)){
                $msg = Mage::helper('sales')->__('Please specify a shipping method.');
                Mage::getSingleton('core/session')->addError($msg);
                return false;
            }

            if($quote->getShippingAddress()->getPostcode() != $ko->shipping_address->postal_code){
                $msg = Mage::helper('klarnaCheckout')->__('Please use the same post code for your quote and Klarna.');
                Mage::getSingleton('core/session')->addError($msg);
                return false;
            }
        }
        
        return true;
    }
}
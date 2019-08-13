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
class Avenla_KlarnaCheckout_Helper_Data extends Mage_Core_Helper_Data
{

    /**
     * Get confirmation url
     * 
     * @return  string
     */
    public function getConfirmationUri()
    {
        return rtrim(Mage::getUrl('klarnaCheckout/KCO/confirmation?klarna_order={checkout.order.uri}'), "/");
    }
    
    /**
     * Get push url
     * 
     * @return  string
     */
    public function getPushUri()
    {
		$storeId = Mage::app()->getStore()->getStoreId();
        return rtrim(Mage::getUrl('klarnaCheckout/KCO/push?storeid='.$storeId.'&klarna_order={checkout.order.uri}'), "/");
    }
    
    /**
     * Get url of checkout page
     * 
     * @return  string
     */
    public function getCheckoutUri()
    {
        return rtrim(Mage::helper('checkout/url')->getCheckoutUrl(), "/");
    }
    
    /**
     * Get url of cart page
     * 
     * @return  string
     */
    public function getCartUri()
    {
        return Mage::getUrl('checkout/cart');
    }
    
    /**
     * Get Klarna logo url
     * 
     * @param	int $width
     * @return	string
     */
    public function getLogoSrc($width = 88)
	{
        $eid = Mage::getSingleton('klarnaCheckout/KCO')->getConfig()->getKlarnaEid();
		return "https://cdn.klarna.com/public/images/FI/logos/v1/basic/FI_basic_logo_std_blue-black.png?width=" . $width . "&eid=" . $eid;
	}
    
    /**
     * Get link text
     * 
     * @return	string
     */
    public function getLinkText()
	{
		return Mage::getSingleton('klarnaCheckout/KCO')->getConfig()->getLinkText();
	}
    
    /**
     * Get url to Klarna online GUI
     * 
     * @return  string
     */
    public function getKlarnaMerchantsUrl()
    {
        return Avenla_KlarnaCheckout_Model_Config::ONLINE_GUI_URL;
    }
    
    /**
     * Send test query to Klarna to verify given merchant credentials
     * 
     * @return  bool
     */
    public function getConnectionStatus($quote = null)
    {
        try{
            $ko = Mage::getModel("klarnaCheckout/order")->dummyOrder($quote);

            if($ko == null)
                return false;
            
            $ko->fetch();
			return true;    
		}
        catch (Exception $e) {
            return false;
        }	
    }
	
	/**
     *  Check if Klarna order was made in current store
     *
     *  @param  Klarna_Checkout_Order $ko
     *  @return bool
     */
    public function isOrderFromCurrentStore($ko)
    {
        $uri = $ko['merchant']['push_uri'];
        preg_match('/storeid=(.*?)&klarna_order/', $uri, $res);
        
        if($res[1] == Mage::app()->getStore()->getStoreId())
            return true;
        
        return false;
    }

	/**
     * Get order shipping tax rate
     * @return float $taxRate
     */
    public function getShippingVatRate()
    {
        $taxRate = 0;
        $taxHelper = Mage::helper('tax/data');
        $taxClass = $taxHelper->getShippingTaxClass(Mage::app()->getStore());
        $taxClasses  = Mage::helper("core")->jsonDecode(Mage::helper("tax")->getAllRatesByProductClass());
        if(isset($taxClasses["value_".$taxClass]))
			$taxRate = $taxClasses["value_".$taxClass];

        return $taxRate;
    }

    /**
     * Get locale code for purchase country
     * 
     * @param string $country
     * @return string
     */
    public function getLocale($country)
    {
         switch($country){
            case 'SE':
                return 'sv-se';
            case 'NO':
                return 'nb-no';
            case 'DE':
                return 'de-de';
            case 'FI':
            default:
                return 'fi-fi';
        }
    }
}
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
class Avenla_KlarnaCheckout_Model_Config extends Varien_Object
{
    const KCO_LIVE_URL      = 'https://checkout.klarna.com';
    const KCO_DEMO_URL      = 'https://checkout.testdrive.klarna.com';
    const KCO_LIVE_S_URL    = 'https://online.klarna.com';
    const KCO_DEMO_S_URL    = 'https://testdrive.klarna.com';
	const KLARNA_DOC_URL	= 'http://developers.klarna.com/';
	const ONLINE_GUI_URL	= 'https://merchants.klarna.com';
    const LICENSE_URL       = 'http://productdownloads.avenla.com/magento-modules/klarna-checkout/license';
	
    /**
     *  Return config var
     *
     *  @param    string $key
     *  @param    string $default value for non-existing key
     *  @return	  mixed
     */
    public function getConfigData($key, $default=false)
    {
        $store = Mage::app()->getStore();
        
        if(Mage::app()->getStore()->getId() == 0)
            $store = Mage::app()->getRequest()->getParam('store', 0);
        
        if (!$this->hasData($key)) {
            $value = Mage::getStoreConfig('payment/klarnaCheckout_payment/'.$key, $store);
            if (is_null($value) || false===$value) {
                $value = $default;
            }
            $this->setData($key, $value);
        }
        return $this->getData($key);
    }
    
    /**
     * Get Klarna merchant eid
     * 
     * @return  string
     */
	public function getKlarnaEid()
	{
        return $this->getConfigData('merchantid');
	}
    
    /**
     * Get Klarna merchant shared secret
     * 
     * @return  string
     */
	public function getKlarnaSharedSecret()
	{
        return Mage::helper('core')->decrypt($this->getConfigData('sharedsecret'));
	}
	
	/**
     * Get terms url
     * 
     * @return  string
     */
	public function getTermsUri()
	{
        return Mage::getUrl($this->getConfigData('terms_url'));        
	}
    
    /**
     * Get Klarna Checkout mode (LIVE OR BETA)
     * 
     * @return  bool
     */
    public function isLive()
    {
        if($this->getConfigData('server') == "LIVE")
            return true;
        
        return false;
    }
    
    /**
     * Get selected locale for Klarna Checkout
     * 
     * @return  string  locale
     */
    public function getLocale()
    {
        return $this->getConfigData('locale');
    }
    
    /**
     * Get module status
     * 
     * @return  bool
     */
    public function isActive()
    {
        return $this->getConfigData('active');
    }

    /**
     * Get partial shipment activation mode
     * 
     * @return  bool
     */
    public function activatePartial()
    {
        return $this->getConfigData('activate_partial');
    }

    /**
     * Get Google Analytics number or false if not found
     * 
     * @return  mixed
     */
    public function getGoogleAnalyticsNo()
    {
        $ga = $this->getConfigData('google_analytics');
        if(strlen($ga) < 1)
            return false;

        return $this->getConfigData('google_analytics');
    }

	/**
     * Get method title
     * 
     * @return  string
     */	
    public function getTitle()
    {
        if(strlen($this->getConfigData('title')) > 0)
            return $this->getConfigData('title');
        
        return "Klarna Checkout";
    }
    
    /**
     * Get link text
     * 
     * @return  string
     */	
    public function getLinkText()
    {
        if(strlen($this->getConfigData('linktext')) > 0)
            return $this->getConfigData('linktext');
        
        return "Go to Klarna Checkout";
    }
	/**
     * Get tax rate for credit memo adjustment
     * 
     * @return  float
     */
    public function getReturnTaxRate()
    {
        $taxClass =  $this->getConfigData('return_tax');

        $taxClasses  = Mage::helper("core")->jsonDecode(Mage::helper("tax")->getAllRatesByProductClass());
        if(isset($taxClasses["value_".$taxClass]))
            return $taxClasses["value_".$taxClass];
        
        return 0;
    }

    /**
     * Get license agreement status 
     * 
     * @return bool
     */
    public function getLicenseAgreement()
    {
        return $this->getConfigData('license');
    }
}
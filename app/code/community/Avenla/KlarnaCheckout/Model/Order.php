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

require_once(Mage::getBaseDir('lib') . '/KlarnaCheckout/Checkout.php');

class Avenla_KlarnaCheckout_Model_Order extends Klarna_Checkout_Order
{   
	private $helper;
	private $quote;
	private $config;
	private $cart = array();
	private $dummy = false;
	public  $connector;    
	public  $order;
	private $mobile;
    
	public function __construct()
	{ 
		$this->helper = Mage::helper("klarnaCheckout");
		$this->config = Mage::getSingleton('klarnaCheckout/KCO')->getConfig();
		
		$url = $this->config->isLive() 
			?  Avenla_KlarnaCheckout_Model_Config::KCO_LIVE_URL 
			:  Avenla_KlarnaCheckout_Model_Config::KCO_DEMO_URL;

		parent::$baseUri  		= $url . '/checkout/orders';
		parent::$contentType 	= "application/vnd.klarna.checkout.aggregated-order-v2+json";
		Mage::log("SS:". $this->config->getKlarnaSharedSecret());
		
		$this->connector = Klarna_Checkout_Connector::create($this->config->getKlarnaSharedSecret());
	}
    
	/**
	 * Get Klarna Checkout order
	 * 
	 * @param	Mage_Sales_Model_Quote $quote
     * @param	string $checkoutId
     * @return	Klarna_Checkout_Order
     */
    public function getOrder($quote = null, $checkoutId = null, $mobile = false)
    {
		
        $this->order = new Klarna_Checkout_Order($this->connector, $checkoutId);
        $this->mobile = $mobile;
        if(!$quote)
            return $this->order;
        
        $this->quote = $quote; 
        $this->addProductsToCart();
        $this->processDiscount();
        $this->getShippingCosts();
        
        $checkoutId ? $this->updateOrder() : $this->createOrder();
        
        return $this->order;
    }
    
    /**
     * Create new Klarna Checkout order
     *
     */
    private function createOrder($country = null)
    {   
        try{
            $create['purchase_country'] = $country != null 
				? $country 
				: Mage::getStoreConfig('general/country/default', Mage::app()->getStore());
				
            $create['purchase_currency']                = $this->dummy ? 'EUR' : $this->quote->getBaseCurrencyCode();
            $create['locale']                           = $this->config->getLocale();
            $create['merchant']['id']                   = $this->config->getKlarnaEid();
            $create['merchant']['terms_uri']            = $this->config->getTermsUri();
            $create['merchant']['checkout_uri']         = $this->helper->getCheckoutUri();
            $create['merchant']['confirmation_uri']     = $this->helper->getConfirmationUri(); 
            $create['merchant']['push_uri']             = $this->helper->getPushUri();
            $create['merchant_reference']['orderid1']   = $this->quote ? $this->quote->getId() : '12345';
            $create['gui']['options']                   = array('disable_autofocus');
			$create['gui']['layout']					= $this->mobile ? 'mobile' : 'desktop';
            
            $info = $this->getCustomerInfo();
            if(!empty($info))
                $create['shipping_address'] = $info;
            
            foreach ($this->cart as $item){
                $create['cart']['items'][] = $item;
            }
            
            $this->order->create($create);
            if(!$this->dummy)
                $this->order->fetch();
        }
        catch (Exception $e) {
            Mage::logException($e);
			$this->order = null;
		}
    }
    
    /**
     * Update existing Klarna Checkout order
     *
     */
    public function updateOrder()
    {
        try {
			$this->order->fetch();
			
			if(!$this->helper->isOrderFromCurrentStore($this->order)){
                $this->createOrder();
                return;
            }
			
			$update['cart']['items'] = array();
            $update['merchant_reference']['orderid1'] = $this->quote->getId();
            
            $info = $this->getCustomerInfo();
            if(!empty($info))
                $update['shipping_address'] = $info;
                 
            foreach ($this->cart as $item){
				$update['cart']['items'][] = $item;
			}
			
			$this->order->update($update);
		}
        catch (Exception $e) {
            Mage::logException($e);
			$this->order = null;
			unset($_SESSION['klarna_checkout']);
		}
    }
    
    /**
     * Get customer info for Klarna Checkout
     * 
     * @return	array
     */
    private function getCustomerInfo()
    {
        $info = array();
        if($this->quote){
            $sa = $this->quote->getShippingAddress();
            $sa->getPostcode() != null ? $info['postal_code'] = $sa->getPostcode() : '';
            $sa->getEmail() != null ? $info['email'] = $sa->getEmail() : '';
        }
        return $info;
    }
    
    /**
     *  Process items from quote to Klarna Checkout order cart
     *  
     */
	private function addProductsToCart()
	{
        $mCart = $this->quote->getAllVisibleItems();
		if(count($mCart) > 0){
			foreach ($mCart as $i)
			{
				$this->cart[] = array(
					'type' 			=> 'physical',
					'reference' 	=> $i->getSku(),
					'name' 			=> $i->getName(),
					'uri' 			=> $i->getUrlPath(),
					'quantity' 		=> (int)$i->getQty(),
					'unit_price' 	=> round($i->getPriceInclTax(), 2) * 100,
					'discount_rate' => round($i->getDiscountPercent(), 2) * 100,
					'tax_rate' 		=> round($i->getTaxPercent(), 2) * 100
				);
			}
		}
	}
    
    /**
     *  Process discount from quote to Klarna Checkout order
     *  
     */
    private function processDiscount()
    {
        $totals = $this->quote->getTotals();
		
		// TODO : Calculate discount tax rate ! Cannot be 0 always.
		// Check discount tax configuration too.
		
        if(isset($totals['discount'])){
            $discount = $totals['discount'];
            $this->cart[] = array(
				'type' 			=> 'discount',
				'reference' 	=> $discount->getcode(),
				'name' 			=> $discount->getTitle(),
				'quantity' 		=> 1,
				'unit_price' 	=> round($discount->getValue(), 2) * 100,
				'tax_rate' 		=> 0
			);
        }
    }
    
    /**
     *  Process shipping costs from quote to Klarna Checkout order
     *  
     */
	private function getShippingCosts()
	{
        if($this->quote->getShippingAddress()->getShippingMethod() != null){
            
			$taxRate = 0;
            $taxHelper = Mage::helper('tax/data');
            $taxClass = $taxHelper->getShippingTaxClass(Mage::app()->getStore());
            $taxClasses  = Mage::helper("core")->jsonDecode(Mage::helper("tax")->getAllRatesByProductClass());
            if(isset($taxClasses["value_".$taxClass]))
				$taxRate = $taxClasses["value_".$taxClass];
            
			$shippingCosts = array(
				'type' 			=> 'shipping_fee',
				'reference' 	=> 'shipping_fee',
				'name' 			=> $this->quote->getShippingAddress()->getShippingDescription(),
				'quantity' 		=> 1,
				'unit_price' 	=> round($this->quote->getShippingAddress()->getShippingInclTax(), 2) * 100,
				'tax_rate'      => (int)($taxRate * 100)
			);
            
			$this->cart[] = $shippingCosts;
		}
	}
    
    /**
     * Create dummy order with test product
     * 
     * @return  Klarna_Checkout_Order
     */
    public function dummyOrder($country = null)
    {
        $this->dummy = true;
        $this->order = new Klarna_Checkout_Order($this->connector, null);
        
        $this->cart = array(
            array(
                'reference' => '123456789',
                'name' => 'Test product',
                'quantity' => 1,
                'unit_price' => 4490,
                'tax_rate' => 2400
            ));
        $this->createOrder($country);
        
        return $this->order;
    }
}
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
	private $cart;
	private $dummy = false;
	public  $connector;    
	public  $order;
	private $mobile;
    private $discounted = 0;
    
	public function __construct()
	{ 
        $this->helper = Mage::helper("klarnaCheckout");		
        $this->config = Mage::getSingleton('klarnaCheckout/KCO')->getConfig();
		$url = $this->config->isLive()
			?  Avenla_KlarnaCheckout_Model_Config::KCO_LIVE_URL 
			:  Avenla_KlarnaCheckout_Model_Config::KCO_DEMO_URL;

		parent::$baseUri  		= $url . '/checkout/orders';
		parent::$contentType 	= "application/vnd.klarna.checkout.aggregated-order-v2+json";
		
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
    private function createOrder()
    {   
        try{
            $create['purchase_country']                 = $this->getPurchaseCountry();	
            $create['purchase_currency']                = Mage::app()->getStore()->getBaseCurrencyCode();
            $create['locale']                           = $this->helper->getLocale($this->getPurchaseCountry());
            $create['merchant']['id']                   = $this->config->getKlarnaEid();
            $create['merchant']['terms_uri']            = $this->config->getTermsUri();
            $create['merchant']['checkout_uri']         = $this->helper->getCheckoutUri();
            $create['merchant']['confirmation_uri']     = $this->helper->getConfirmationUri();
            $create['merchant']['push_uri']             = $this->helper->getPushUri();

            if($this->helper->getValidationUri())
                $create['merchant']['validation_uri']   = $this->helper->getValidationUri();

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
			if(!$this->helper->isOrderFromCurrentStore($this->order) ||
                strtoupper($this->order['purchase_country']) != $this->getPurchaseCountry()){
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
     * Get purchase country
     * 
     * @return  string
     */
    private function getPurchaseCountry()
    {
        if($this->quote && $this->quote->getShippingAddress()->getCountry()){
            return $this->quote->getShippingAddress()->getCountry();               
        }
        else{
            return Mage::getStoreConfig('general/country/default', Mage::app()->getStore());
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
        $this->cart = array();
        $mCart = $this->quote->getAllVisibleItems();
        
        if(count($mCart) > 0){
            foreach ($mCart as $i){
                if($i->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE && $i->isChildrenCalculated()){
                    foreach($i->getChildren() as $c){
                        $this->addProduct($c);
                    }
                }
                else{
                    $this->addProduct($i);
                }
            }
        }
	}

    /**
     *  Add quote item to cart array
     *  
     *  @param Mage_Sales_Model_Quote_Item
     */
    private function addProduct($item)
    {
        $discount_rate = 0;
        if($item->getBaseDiscountAmount()){
            $discount_rate = $item->getBaseDiscountAmount() / ($item->getBaseRowTotalInclTax() / 100);
            $this->discounted += $item->getBaseDiscountAmount();
        }

        $this->cart[] = array(
            'type'          => 'physical',
            'reference'     => $item->getSku(),
            'name'          => $item->getName(),
            'uri'           => $item->getUrlPath(),
            'quantity'      => (int)$item->getTotalQty(),
            'unit_price'    => round($item->getBasePriceInclTax(), 2) * 100,
            'discount_rate' => round($discount_rate, 2) * 100,
            'tax_rate'      => round($item->getTaxPercent(), 2) * 100
        );
    }

    /**
     *  Process discount from quote to Klarna Checkout order
     *  
     */
    private function processDiscount()
    {
        $totals = $this->quote->getTotals();
        $baseDiscount = $this->quote->getShippingAddress()->getBaseDiscountAmount();

        if(abs($baseDiscount) - $this->discounted > 0.001){
            $discount = $totals['discount'];
            
            $this->cart[] = array(
                'type'          => 'discount',
                'reference'     => $discount->getcode(),
                'name'          => $discount->getTitle(),
                'quantity'      => 1,
                'unit_price'    => round($baseDiscount, 2) * 100,
                'tax_rate'      => 0
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
				'unit_price' 	=> round($this->quote->getShippingAddress()->getBaseShippingInclTax(), 2) * 100,
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
    public function dummyOrder($quote = null)
    {
        $this->dummy = true;
        if($quote)
            $this->quote = $quote;

        $this->order = new Klarna_Checkout_Order($this->connector, null);

        $this->cart = array(
            array(
                'reference'     => '123456789',
                'name'          => 'Test product',
                'quantity'      => 1,
                'unit_price'    => 4490,
                'tax_rate'      => 0
            ));
        $this->createOrder();
        
        return $this->order;
    }
}
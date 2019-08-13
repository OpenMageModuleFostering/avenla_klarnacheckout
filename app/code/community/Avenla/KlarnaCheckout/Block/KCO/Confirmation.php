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
class Avenla_KlarnaCheckout_Block_KCO_Confirmation extends Mage_Core_Block_Template
{
	
	protected function _toHtml()
    {	
	    return $this->getKcoFrame();
    }
	
	/**
     *  Return Klarna Checkout confirmation page
     *
     *  @return	  string
     */
	private function getKcoFrame()
	{
		$order = Mage::getModel("klarnaCheckout/order")->getOrder(null, $this->getCheckoutID());
        $order->fetch();
        $result = "";
        
        if(Mage::getModel('klarnaCheckout/config')->getGoogleAnalyticsNo() !== false && isset($_SESSION['klarna_checkout'])){
            $result .= $this->getAnalyticsCode($order);
        }

		$result .= $order['gui']['snippet'];
		
        $link_to_store = '<div class="buttons-set"><button type="button" class="button"
            title="'.  $this->__('Continue Shopping') .'" onclick="window.location=\''. $this->getUrl() .'\'">
            <span><span>'. $this->__('Continue Shopping') .'</span></span></button></div>';
        
        $result .= $link_to_store;
		
        unset($_SESSION['klarna_checkout']);
        
        return $result;
	}
    
    /**
     *  Get Google Analytics Ecommerce tracking code
     *
     *  @param      Klarna_Checkout_Order $ko
     *  @return     string
     */
    private function getAnalyticsCode($ko)
    {
        $type = Mage::getModel('klarnaCheckout/config')->getGoogleAnalyticsType();
        $orderId = false;

        if(strlen($ko['merchant_reference']['orderid2']) > 0){
            $mo = Mage::getModel('sales/order')->load($ko['merchant_reference']['orderid1'], 'increment_id');
            if($mo->getId()){
                $orderId = $ko['merchant_reference']['orderid1'];
            }
        }

        if(!$orderId){
            $quote = Mage::getModel('sales/quote')->load($ko['merchant_reference']['orderid1']);
            $quote->reserveOrderId();
            $quote->save();
            $orderId = $quote->getReservedOrderId();
        }

        if(count($ko['cart']['items']) < 1)
            return;

        foreach($ko['cart']['items'] as $p){
            $shipping_fee = "";
            if($p['type'] == 'shipping_fee')
                $shipping_fee = $p['total_price_including_tax'];
        }

        if($type == Avenla_KlarnaCheckout_Model_Config::ANALYTICS_UNIVERSAL){
            return $this->getUniversalAnalyticsCode($ko, $shipping_fee, $orderId);
        }
        else{
            return $this->getClassicAnalyticsCode($ko, $shipping_fee, $orderId);
        }
    }

	/**
     *  Get classic Google Analytics Ecommerce tracking code
     *
	 *	@param		Klarna_Checkout_Order $ko
     *  @param      string $shipping_fee
     *  @param      string $orderId
     *  @return		string
     */
    private function getClassicAnalyticsCode($ko, $shipping_fee, $orderId)
    {

        $gc = '<script type="text/javascript">';
        $gc .= "//<![CDATA[\n";
        $gc .= 'var _gaq = _gaq || [];';
        $gc .= '_gaq.push(["_setAccount", "' . Mage::getModel('klarnaCheckout/config')->getGoogleAnalyticsNo() . '"]);';

        $gc .= sprintf("_gaq.push(['_addTrans', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);",
            $orderId,
            Mage::app()->getStore()->getName(),
            $ko['cart']['total_price_including_tax'] / 100,
            $ko['cart']['total_tax_amount'] / 100,
            $shipping_fee / 100,
            $ko['billing_address']['city'],
            null,
            $ko['billing_address']['country']
        );

        foreach ($ko['cart']['items'] as $p){

            if($p['type'] == 'shipping_fee')
                continue;


            $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $p['reference']);
            if($product){
                $categoryIds = Mage::getModel('catalog/product')
                        ->loadByAttribute('sku', $p['reference'])
                        ->getCategoryIds();

                if(!empty($categoryIds))
                    $cat = Mage::getModel('catalog/category')->load(end($categoryIds))->getName();
            }

            $gc .= sprintf("_gaq.push(['_addItem', '%s', '%s', '%s', '%s', '%s', '%s']);",
                $orderId,
                $p['reference'],
                $p['name'],
                null,
                $p['unit_price'] / 100,
                $p['quantity']
            );
        }
       
        $gc .= '_gaq.push(["_set", "currencyCode", "'. $ko['purchase_currency'].'"]); ';
        $gc .= '_gaq.push(["_trackTrans"]);';
        $gc .= '(function() { ';
        $gc .= 'var ga = document.createElement("script"); ga.type = "text/javascript"; ga.async = true; ';
        $gc .= 'ga.src = ("https:" == document.location.protocol ? "https://ssl" : "http://www") + ".google-analytics.com/ga.js";';
        $gc .= 'var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ga, s);';
        $gc .= ' })();';
        $gc .= '//]]>' . "\n";
        $gc .= '</script>';
        
        return $gc;  
    }

    /**
     *  Get Universal Google Analytics Ecommerce tracking code
     *
     *  @param      Klarna_Checkout_Order $ko
     *  @param      string $shipping_fee
     *  @param      string $orderId
     *  @return     string
     */
    public function getUniversalAnalyticsCode($ko, $shipping_fee, $orderId)
    {
        $gc = '<script type="text/javascript">';
        $gc .= "//<![CDATA[\n";
        $gc .= "(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){";
        $gc .= "(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),";
        $gc .= "m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)";
        $gc .= "})(window,document,'script','//www.google-analytics.com/analytics.js','ga');";

        $gc .= "ga('create', '" . Mage::getModel('klarnaCheckout/config')->getGoogleAnalyticsNo() . "', 'auto');";

        $gc .= "ga('require', 'ecommerce');";
        $gc .= sprintf("ga('ecommerce:addTransaction', {
                'id': '%s',
                'affiliation': '%s',
                'revenue': '%s',
                'tax': '%s',
                'shipping': '%s',
                'currency': '%s'
            });",
            $orderId,
            Mage::app()->getStore()->getName(),
            $ko['cart']['total_price_including_tax'] / 100,
            $ko['cart']['total_tax_amount'] / 100,
            $shipping_fee / 100,
            $ko['purchase_currency']
        );

        foreach ($ko['cart']['items'] as $p){

            if($p['type'] == 'shipping_fee')
                continue;

            $gc .= sprintf("ga('ecommerce:addItem', {
                    'id': '%s',
                    'sku': '%s',
                    'name': '%s',
                    'category': '%s',
                    'price': '%s',
                    'quantity': '%s'
                });",
                $orderId,
                $p['reference'],
                $p['name'],
                null,
                $p['unit_price'] / 100,
                $p['quantity']
            );        
        }
        $gc .= "ga('ecommerce:send');";
        
        $gc .= '//]]>' . "\n";
        $gc .= '</script>';

        return $gc;
    }
}
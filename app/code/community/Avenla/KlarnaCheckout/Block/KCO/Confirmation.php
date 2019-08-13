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
        
        if(Mage::getModel('klarnaCheckout/config')->getGoogleAnalyticsNo() !== false && isset($_SESSION['klarna_checkout']))
            $result .= $this->getGoogleCode($order);

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
	 *	@param		Klarna_Checkout_Order $ko
     *  @return		string
     */
    private function getGoogleCode($ko)
    {
		if(count($ko['cart']['items']) < 1)
			return;

        foreach($ko['cart']['items'] as $p){
            $shipping_fee = "";
            if($p['type'] == 'shipping_fee')
                $shipping_fee = $p['total_price_including_tax'];
        }

        $gc .= 'var _gaq = _gaq || [];';
        $gc .= '_gaq.push(["_setAccount", "' . Mage::getModel('klarnaCheckout/config')->getGoogleAnalyticsNo() . '"]);';
        $gc .= '_gaq.push(["_trackPageview"]);';
        $gc .= '_gaq.push(["_addTrans",';
        $gc .= '"' . $ko['merchant_reference']['orderid1'] . '",';
        $gc .= '"' . Mage::app()->getStore()->getName() . '",';
        $gc .= '"' . $ko['cart']['total_price_including_tax'] / 100 . '",';
        $gc .= '"' . $ko['cart']['total_tax_amount'] / 100 . '",';
        $gc .= '"' . $shipping_fee / 100 . '",';
        $gc .= '"' . $ko['billing_address']['city'] . '",';
        $gc .= '"",' ;
        $gc .= '"' . $ko['billing_address']['country'] . '"';
        $gc .= ']);' . "\n";
        
        foreach ($ko['cart']['items'] as $p){

			if($p['type'] == 'shipping_fee')
                continue;

            $cat = "";
            $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $p['reference']);
            if($product){
                $categoryIds = Mage::getModel('catalog/product')
                        ->loadByAttribute('sku', $p['reference'])
                        ->getCategoryIds();

                if(!empty($categoryIds))
                    $cat = Mage::getModel('catalog/category')->load(end($categoryIds))->getName();
            }

            $gc .= '_gaq.push(["_addItem",';
            $gc .= '"' . $ko['merchant_reference']['orderid1'] . '",';
            $gc .= '"' . $p['reference'] . '",';
            $gc .= '"' . $p['name'] . '",';
            $gc .= '"' . $cat . '",';
            $gc .= '"' . $p['unit_price'] / 100 . '",';
            $gc .= '"' . $p['quantity'] . '"';
            $gc .= ']);' . "\n";

        }
       
        $gc .= '_gaq.push(["_set", "currencyCode", "EUR"]); ';
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
}
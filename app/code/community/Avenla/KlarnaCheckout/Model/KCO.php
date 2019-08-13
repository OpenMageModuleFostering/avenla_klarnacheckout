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

class Avenla_KlarnaCheckout_Model_KCO extends Mage_Payment_Model_Method_Abstract
{ 
	protected $_code                    = 'klarnaCheckout_payment';
    protected $_formBlockType           = 'klarnaCheckout/KCO_form';
	protected $_infoBlockType           = 'klarnaCheckout/KCO_info';

    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = false;
    protected $_canUseForMultishipping  = false;
    protected $_order                   = null;

    /**
     * Get Config model
     *
     * @return  object Avenla_KlarnaCheckout_Model_Config
     */
    public function getConfig()
    {
        return Mage::getSingleton('klarnaCheckout/config');
    }
    
    /**
     * Check if Klarna Checkout is available
     *
     * @param   Mage_Sales_Model_Quote|null $quote
     * @return  bool
     */
    public function isAvailable($quote = null)
    { 
        if($quote == null)
            $quote = Mage::getSingleton('checkout/session')->getQuote();

        if(in_array($quote->getShippingAddress()->getShippingMethod(), $this->getConfig()->getDisallowedShippingMethods()))
            return false;

        $result = parent::isAvailable($quote) && 
               (Mage::getSingleton('customer/session')->isLoggedIn() || Mage::helper('checkout')->isAllowedGuestCheckout($quote)) && 
               count($quote->getAllVisibleItems()) >= 1 && $this->getConfig()->getLicenseAgreement();

        if($result)
            $result = Mage::helper('klarnaCheckout')->getConnectionStatus($quote);

        return $result;
    }
    
    /**
     * Capture payment
     *
     * @param   Varien_Object $payment
     * @param   float $amount
     * @return  Avenla_KlarnaCheckout_Model_KCO
     */
    public function capture(Varien_Object $payment, $amount)
    {
		$order = $payment->getOrder();
		$currentId = Mage::app()->getStore()->getStoreId();
        Mage::app()->setCurrentStore($order->getStore()->getStoreId());
        
        if(Mage::registry('kco_transaction') == null){
            foreach ($order->getInvoiceCollection() as $invoice) {
                if($invoice->getId() == null){
                    $inv = $invoice;
                }
            }
			if(isset($inv))
				Mage::getModel('klarnaCheckout/api')->activateFromInvoice($order, $inv);
        }
        
        if($id = Mage::registry('kco_transaction')){
            $payment->setTransactionId($id);
            $payment->setIsTransactionClosed(1);
            Mage::unregister('kco_transaction');
        }
        
		Mage::app()->setCurrentStore($currentId);
        return $this;
    }

    /**
     * Register KCO save before redund
     *
     * @param   $invoice
     * @param   Varien_Object $payment
     * @return  Avenla_KlarnaCheckout_Model_KCO
     */
    public function processBeforeRefund($invoice, $payment)
    {
         Mage::register('kco_save', true);
         return $this;
    }
    
    /**
     * Unregister KCO save after redund
     *
     * @param   $creditmemo
     * @param   Varien_Object $payment
     * @return  Avenla_KlarnaCheckout_Model_KCO
     */
    public function processCreditmemo($creditmemo, $payment)
    {
        Mage::unregister('kco_save');
        return $this;
    }

    /**
     * Refund specified amount from invoice
     *
     * @param   Varien_Object $payment
     * @param   float $amount
     * @return  Avenla_KlarnaCheckout_Model_KCO
     */
    public function refund(Varien_Object $payment, $amount)
    {       
        $order = $payment->getOrder();

		$currentId = Mage::app()->getStore()->getStoreId();
        Mage::app()->setCurrentStore($order->getStore()->getStoreId());
		$api = Mage::getModel('klarnaCheckout/api');
        $rno = Mage::helper('klarnaCheckout/api')->getReservationNumber($order);

        if($rno === false)
            return $this;

        $creditmemo = $payment->getCreditmemo();
        $invoice = $creditmemo->getInvoice();
        $klarna_invoice = $invoice->getTransactionId();
        
        $products = array();
        $result = array();
        $total_refund = false;
        
        if (abs($invoice->getGrandTotal() - $creditmemo->getGrandTotal()) < .0001)
            $total_refund = true;

        foreach ($creditmemo->getAllItems() as $item)
        {
            $invoiceItem = Mage::getResourceModel('sales/order_invoice_item_collection')
                ->addAttributeToSelect('*')
                ->setInvoiceFilter($invoice->getId())
                ->addFieldToFilter('order_item_id', $item->getOrderItemId())
                ->getFirstItem();

            $diff = $item->getQty() - $invoiceItem->getQty();
            
            if($diff > 0)
                $total_refund = false;

            if($item->getQty() > 0 && !$item->getOrderItem()->isDummy())
                $products[$item->getSku()] = $item->getQty();
        }

        if($total_refund){
            $result[] = $api->creditInvoice($klarna_invoice)
                ? "Refunded Klarna invoice " . $klarna_invoice
                : "Failed to refund Klarna invoice " . $klarna_invoice;
        }
        else{
            $fee = null;
            if($creditmemo->getAdjustment() < 0)
                $fee = abs($creditmemo->getAdjustment());
            
            if(!empty($products) || $creditmemo->getShippingAmount() > 0){
                if (abs($invoice->getShippingAmount() - $creditmemo->getShippingAmount()) < .0001)
                    $products['shipping_fee'] = 1;

                if($fee != null){
                    $response = $api->creditPart($klarna_invoice, $products, $fee, $this->getConfig()->getReturnTaxRate());
                }
                else{
                    $response = $api->creditPart($klarna_invoice, $products);
                }
                
                if($response){
                    $t = "Credited products: ";
                    foreach($products as $key => $p){
                        $t .= $key."(".$p.") ";
                    }
                    $result[] = $t;
                }
                else{
                    $result[] = "Failed to do partial refund";
                }

                if($creditmemo->getShippingAmount() > 0 && !array_key_exists('shipping_fee', $products)){
                    $result[] =  $api->returnAmount($klarna_invoice, $creditmemo->getShippingAmount(), Mage::helper('klarnaCheckout')->getShippingVatRate())
                        ? "Refunded amount of " . $creditmemo->getShippingAmount() . " from shipment on Klarna invoice " . $klarna_invoice 
                        : "Failed to refund amount of " . $creditmemo->getShippingAmount() . " from shipment on Klarna invoice " . $klarna_invoice;

                }
            }

			if($creditmemo->getAdjustment() > 0){
                $result[] =  $api->returnAmount($klarna_invoice, $creditmemo->getAdjustment(), $this->getConfig()->getReturnTaxRate())
                    ? "Refunded amount of " . $creditmemo->getAdjustment() . " on Klarna invoice " . $klarna_invoice 
                    : "Failed to refund amount of " . $creditmemo->getAdjustment() . " on Klarna invoice " . $klarna_invoice;
            } 
        }
      
        if(!empty($result)) {
            foreach($result as $msg)
            {
                $order->addStatusHistoryComment($msg);
            }
            $order->save();
        }
	
		Mage::app()->setCurrentStore($currentId);
		
        return $this;
    }
}
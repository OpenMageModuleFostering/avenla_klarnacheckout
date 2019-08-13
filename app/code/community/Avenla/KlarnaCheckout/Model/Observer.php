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

class Avenla_KlarnaCheckout_Model_Observer
{
    private $api;
    private $helper;
    private $apiHelper;
    
    public function __construct()
    {        
        $this->helper 		= Mage::helper('klarnaCheckout');
        $this->apiHelper 	= Mage::helper('klarnaCheckout/api');
    }
    
    /**
     * Process order after status change
     * 
     * @param	Varien_Event_Observer $observer
     */
    public function orderStatusChanged($observer)
	{
		if(Mage::registry('kco_save'))
            return $this;
        
        $order = $observer->getEvent()->getOrder();
        $rno = $this->apiHelper->getReservationNumber($order);
		Mage::app()->setCurrentStore($order->getStore()->getStoreId());
		$this->api = Mage::getModel('klarnaCheckout/api');
        
        switch ($order->getState()) {
            case Mage_Sales_Model_Order::STATE_COMPLETE:
                if($rno !== false && $order->canInvoice())
                    $this->api->activateReservation($order);
                
                break;
            
            case Mage_Sales_Model_Order::STATE_CANCELED:
                if($rno !== false)
                    $this->api->cancelReservation($rno, $order);

                break;
            
            default:
                if($rno !== false){
                    $mixed = false;
                    foreach($order->getAllItems() as $item){
                        if($item->getQtyShipped() > $item->getQtyInvoiced())
                            $mixed = true;
                    }
                    
                    if($mixed)
                        $this->api->activateReservation($order);
                }
        }            
	}

	/**
     * Process invoice after save
     * 
     * @param	Varien_Event_Observer $observer
     */
    public function invoiceSaved($observer)
    {
        if(Mage::registry('kco_save'))
            return $this;

        if($kco_invoicekey = Mage::registry('kco_invoicekey')){
            $invoice = $observer->getEvent()->getInvoice();
            $order = $invoice->getOrder();
            $rno = $this->apiHelper->getReservationNumber($order);
            
            if($rno !== false){
                if(false !== $klarnainvoices = $this->apiHelper->getKlarnaInvoices($order)){
                    if (!array_key_exists($invoice->getId(), $klarnainvoices)){
                        $klarnainvoices[$invoice->getId()] = $klarnainvoices[$kco_invoicekey];
                        unset($klarnainvoices[$kco_invoicekey]);
                        
                        $order = $this->apiHelper->saveKlarnaInvoices($order, $klarnainvoices);
                        Mage::register('kco_save', true);
                        $order->save();
                        Mage::unregister('kco_save');
                    }    
                }
            }
        }       
    }
    
	/**
     * Add Klarna link in default Checkout
     * 
     * @param	Varien_Event_Observer $observer
     */
    public function insertKlarnaLink($observer)
    {
        $block = $observer->getBlock();
        $isLogged = Mage::helper('customer')->isLoggedIn();
        
        if(!Mage::getModel('klarnaCheckout/config')->isActive())
            return $this;

        if (
            $block->getType() == 'checkout/onepage_login' ||
            ($isLogged && $block->getType() == 'checkout/onepage_billing') ||
            ($block->getType() == 'checkout/onepage_payment_methods' && $block->getBlockAlias() != 'methods') &&
            Mage::getSingleton('klarnaCheckout/KCO')->isAvailable()
            )
        {           
            $child = clone $block;
            $child->setType('klarnaCheckout/KCO_Link');
            $block->setChild('original', $child);
            $block->setTemplate('KCO/link.phtml');
        }
    }
    
	/**
     * Add activate reservation button to admin order view
     * 
     * @param Varien_Event_Observer $observer
     */
    public function addActivate($observer)
    {
        $block = $observer->getEvent()->getBlock();
        
        if(get_class($block) =='Mage_Adminhtml_Block_Sales_Order_View'
            && $block->getRequest()->getControllerName() == 'sales_order')
        {
            $order = $block->getOrder();
            
            if($order->getPayment()->getAdditionalInformation("klarna_order_reference")){
                $block->addButton('activate_klarna_reservation', array(
                    'label'     => Mage::helper('klarnaCheckout')->__('Activate Klarna reservation'),
                    'onclick'   => 'setLocation(\'' . $block->getUrl('klarnaCheckout/KCO/activateReservation', array('order_id' => $order->getId())) . '\')',
                    'class'     => 'save'
                ));
            }
        }
    }

    /**
     * Add new layout handle if needed
     * 
     * @param Varien_Event_Observer $observer
     */
    public function layoutLoadBefore($observer)
    {
        if (Mage::getModel('klarnaCheckout/config')->hideDefaultCheckout()){
            $observer->getEvent()->getLayout()->getUpdate()
                ->addHandle('kco_only');
        }

        $kco_layout = Mage::getModel('klarnaCheckout/config')->getKcoLayout();
        
        if($observer->getAction()->getFullActionName() == "checkout_cart_index" && ($kco_layout && $kco_layout != "default")){
            $observer->getEvent()->getLayout()->getUpdate()
                ->addHandle($kco_layout);
        }
    }
}
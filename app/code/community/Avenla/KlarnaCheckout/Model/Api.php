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

require_once(Mage::getBaseDir('lib') . '/Klarna/Klarna.php');
require_once(Mage::getBaseDir('lib') . '/Klarna/transport/xmlrpc-3.0.0.beta/lib/xmlrpc.inc');
require_once(Mage::getBaseDir('lib') . '/Klarna/transport/xmlrpc-3.0.0.beta/lib/xmlrpc_wrappers.inc');

class Avenla_KlarnaCheckout_Model_Api extends Mage_Core_Model_Abstract
{
	protected  $klarna;
    private    $helper;
    
	public function __construct()
	{
        $this->helper = Mage::helper('klarnaCheckout/api');
        $this->klarna = new Klarna();

        $locale = $this->klarna->getLocale($this->helper->getCountry());
        $config = Mage::getSingleton('klarnaCheckout/KCO')->getConfig();
		
        try{
            $this->klarna->config(
                '645', //$config->getKlarnaEid(),
                'W7LcGpIMfs28cTJ', //$config->getKlarnaSharedSecret(),
                $locale['country'],
                $locale['language'],
                $locale['currency'],
                $config->isLive() ?  Klarna::LIVE : Klarna::BETA,
                'json',
                Mage::getBaseDir('lib').'/Klarna/pclasses/'.$config->getKlarnaEid().'_pclass_'.$locale['country'].'.json',
                true,
                true
            );
        }
        catch (Exception $e) {
			Mage::logException($e);
        }
	}
    
	/**
     * Activate Klarna reservation
     * 
     * @param   Magento_Sales_Order
     * @param   string $invoiceId|null
     * @return  bool
     */
    public function activateReservation($mo, $invoiceId = null)
    {  
        Mage::register('kco_save', true);
        
        if($invoiceId != null){
            $result = $this->activateFromInvoice($mo, Mage::getModel('sales/order_invoice')->load($invoiceId));
        }
        else if(false !== $qtys = $this->checkIfPartial($mo)){
            $result = $this->activatePartialReservation($mo, $qtys);
        }
        else{
            $result = $this->activateFullReservation($mo);
        }
        Mage::unregister('kco_save');
		
        return $result;
    }

    /**
     * Check if activation is partial or full
     * 
     * @param   Magento_Sales_Order $mo
     * @return  array | false
     */
    private function checkIfPartial($mo)
    {
        $qtys = array();
        $partial = false;

        foreach($mo->getAllVisibleItems() as $item){

            if($item->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE){
                if($item->isChildrenCalculated()){
                    foreach($item->getChildrenItems() as $child){
                        $qtys[$child->getId()] = $child->getQtyShipped() - $child->getQtyInvoiced();

                        if($child->getQtyOrdered() != $child->getQtyShipped())
                            $partial = true;
                    }
                }
                else{
                    if($item->isDummy()){
                        $bundleQtys = array();
                        foreach($item->getChildrenItems() as $child){
                            $parentCount = 0;
                            $bundleCount = $child->getQtyOrdered() / $item->getQtyOrdered();
                            $qtyInvoiced = $bundleCount * $item->getQtyInvoiced();
                            $diff = $child->getQtyShipped() - $qtyInvoiced;
                            
                            if($diff >= $bundleCount)
                                $parentCount = floor($bundleCount / $diff);
                            
                            $bundleQtys[] = $parentCount;

                            if($child->getQtyOrdered() != $child->getQtyShipped())
                                $partial = true;
                        }

                        $qtys[$item->getId()] = min($bundleQtys);
                    }
                    else{
                        $qtys[$item->getId()] = $item->getQtyShipped() - $item->getQtyInvoiced();
                        
                        if($item->getQtyShipped() != $item->getQtyOrdered())
                            $partial = true;
                    }
                }
            }   
            else{
                $qtys[$item->getId()] = $item->getQtyShipped() - $item->getQtyInvoiced();

                if($item->getQtyShipped() != $item->getQtyOrdered())
                    $partial = true;
            }
        }

        if($partial)
            return $qtys;

        return false;
    }

    /**
     * Do partial activation
     * 
     * @param   Magento_Sales_Order $mo
     * @param   array $qtys
     * @return  bool
     */
    public function activatePartialReservation($mo, $qtys)
    {
        if(!Mage::getSingleton('klarnaCheckout/KCO')->getConfig()->activatePartial())
            return false;

        foreach($qtys as $key => $qty){
            $sku = Mage::getModel('sales/order_item')->load($key)->getSku();
            $this->klarna->addArtNo($qty, $sku);
        }

        $klarnainvoices = $this->helper->getKlarnaInvoices($mo);
        if(empty($klarnainvoices)){
            $this->klarna->addArtNo(1, 'shipping_fee');
        }
        
        try{
            $rno = $this->helper->getReservationNumber($mo);
            $result = $this->klarna->activate($rno);
            
            $mo = $this->createMageInvoice($mo, $result, $qtys);
            $mo = $this->checkExpiration($mo);
            $mo->save();

            return true;
        }
        catch(Exception $e) {
            $this->helper->failedActivation($mo, $rno, $e);
            return false;
        }
    }

    /**
     * Do full activation
     * 
     * @param   Magento_Sales_Order $mo
     * @return  bool
     */
    public function activateFullReservation($mo)
    {
        if($rno = $this->helper->getReservationNumber($mo)){
            try{
                $result = $this->klarna->activate($rno);
                $mo = $this->createMageInvoice($mo, $result);
                $mo = $this->checkExpiration($mo);
                $mo->save();

                return true;
            }
            catch(Exception $e) {
                $this->helper->failedActivation($mo, $rno, $e);
                return false;
            }
        }
        return false;
    }

    /**
     * Create invoice for Magento order
     * 
     * @param   Magento_Sales_Order $mo
     * @param   array $result
     * @param   array $qtys
     * @return  Magento_Sales_Order
     */
    private function createMageInvoice($mo, $result, $qtys = null)
    {
        $invoice = Mage::getModel('sales/service_order', $mo)->prepareInvoice($qtys);
        
        if (!$invoice->getTotalQty())
            Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products'));

        if(Mage::registry('kco_transaction') != null)
            Mage::unregister('kco_transaction');

        Mage::register('kco_transaction', $result[1]);
        $amount = $invoice->getGrandTotal();
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
        $invoice->register();

        $mo->getPayment()->setTransactionId($result[1]);

        Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();
            
        $invoice->save();
        $klarnainvoices = $this->helper->getKlarnaInvoices($mo);
        $klarnainvoices[$invoice->getId()] = array(
            'invoice'   => $result[1],
            'risk'      => $result[0]
        );

        $mo->addStatusHistoryComment($this->helper->__('Created Klarna invoice %s', $result[1]));
        $mo = $this->helper->saveKlarnaInvoices($mo, $klarnainvoices);
        
        return $mo;
    }

    /**
     * Check reservation expiration
     * 
     * @param   Magento_Sales_Order $mo
     * @return  Magento_Sales_Order
     */
    private function checkExpiration($mo)
    {
        $expr = $mo->getPayment()->getAdditionalInformation("klarna_order_reservation_expiration");
        $expiration = new Zend_Date($expr);
        
        if($expiration < new Zend_Date()){
            $formattedExpiration = Mage::helper('core')->formatDate(
                $expr,'medium', false);
            
            $mo->getPayment()->setAdditionalInformation(
				'klarna_message',
				'Reservation was activated after expiration (expired '.$formattedExpiration.')'
			);
        }
        return $mo;
    }
   
    /**
     * Activate reservation from Magento invoice
     * 
     * @param   Magento_Sales_Order $mo
     * @param   Magento_Sales_Order_Invoice $invoice
     * @return  bool
     */
    public function activateFromInvoice($mo, $invoice)
    {
        if($rno = $this->helper->getReservationNumber($mo)){
            if (abs($mo->getTotalDue() - $invoice->getGrandTotal()) > .0001){
                foreach($invoice->getAllItems() as $item){
                    if(!$item->getOrderItem()->isDummy()){
                        $this->klarna->addArtNo($item->getQty(), $item->getSku());
                    }
                }
            }

            if($invoice->getShippingAmount() > 0){
                $this->klarna->addArtNo(1, 'shipping_fee');
            }

            try{
                $result = $this->klarna->activate($rno);

                if(Mage::registry('kco_invoicekey') != null)
                    Mage::unregister('kco_invoicekey');

                Mage::register('kco_invoicekey', $result[1]);
                
                $klarnainvoices = $this->helper->getKlarnaInvoices($mo);
                $klarnainvoices[$result[1]] = array(
                    'invoice'   => $result[1],
                    'risk'      => $result[0]
                );
                
                if(Mage::registry('kco_transaction') != null)
                    Mage::unregister('kco_transaction');

                Mage::register('kco_transaction', $result[1]);

                $mo = $this->helper->saveKlarnaInvoices($mo, $klarnainvoices);
                $mo = $this->checkExpiration($mo);
                
                $mo->save();

                return true;
            }
            catch(Exception $e) {
                $this->helper->failedActivation($mo, $rno, $e);
                return false;
            }         
        }
    }
    
    
    /**
     * Credit Klarna invoice
     * 
     * @param   string $invoiceNo
     * @return  bool
     */
    public function creditInvoice($invoiceNo)
    {
        try {  
            $result = $this->klarna->creditInvoice($invoiceNo);
            return $this->emailInvoice($result);
        }
        catch(Exception $e) {
            Mage::logException($e);
            return false;
        }   
    }
    
	/**
     * Credit Klarna invoice partially
     * 
     * @param   string  $invoiceNo
	 * @param   array   $products
     * @param   float   $adjustment |null
     * @param   float   $adjustmentTaxRate | null
     * @return  bool
     */
    public function creditPart($invoiceNo, $products, $adjustment = null, $adjustmentTaxRate = null)
    {
        if($adjustment){
            $this->klarna->addArticle(
                1,
                'Adjustment',
                $this->helper->__('Adjustment fee'),
                $adjustment,
                $adjustmentTaxRate,
                0,
                KlarnaFlags::INC_VAT | KlarnaFlags::IS_HANDLING
            );   
        }
        
        foreach($products as $key => $p){
            $this->klarna->addArtNo($p, $key);
        }

        try {  
            $result = $this->klarna->creditPart($invoiceNo);
            return $this->emailInvoice($result);
        }
        catch(Exception $e) {
            Mage::logException($e);
            return false;
        } 
    }

    /**
     * Return amount from Klarna invoice
     * 
     * @param   string $invoiceNo
     * @param   float $amount
	 * @param  	float $vat|0
     * @return  bool
     */
    public function returnAmount($invoiceNo, $amount, $vat = 0)
    {
        try {
            $result = $this->klarna->returnAmount(  
                $invoiceNo,                
                $amount,
                $vat,
                KlarnaFlags::INC_VAT  
            );
            return $this->emailInvoice($result);
        }
        catch(Exception $e) {  
            Mage::logException($e);
            return false;
        }
    }
    
    /**
     * Cancel Klarna reservation
     * 
     * @param   string $rno
	 * @param   Mage_Sales_Model_Order $mo|null
     * @return  bool
     */
    public function cancelReservation($rno, $mo = null)
    {
        try {
            $result = $this->klarna->cancelReservation($rno);

            if($mo){
                $mo->addStatusHistoryComment(
                    $this->helper->__('Klarna reservation <b>%s</b> was canceled.', $rno)
                );
            }
            return true;
        }
        catch(Exception $e) {  
            if($mo){
                $mo->addStatusHistoryComment(
                    $this->helper->__('Failed to cancel Klarna reservation <b>%s</b>.(%s - %s)',
                        $rno,
                        $e->getMessage(),
                        $e->getCode())
                );
            }
            Mage::logException($e);
            return false;
        }
    }

	/**
     * Send invoice e-mail
     * 
     * @param   string $invoiceNo
     * @return  bool
     */
    public function emailInvoice($invoiceNo)
    {
        try {  
            $result = $this->klarna->emailInvoice($invoiceNo);
            return true;
        } catch(Exception $e) {
			Mage::logException($e);
            return false;
        }  
    }
    
    /**
     * Get cheapest monthly price
     * 
     * @param   float $price
     * @return  string | bool
     */
    public function getMonthlyPrice($price)
    {
        if($pclass = $this->klarna->getCheapestPClass($price, KlarnaFlags::PRODUCT_PAGE)){
            $value = KlarnaCalc::calc_monthly_cost(
                $price,
                $pclass,
                KlarnaFlags::PRODUCT_PAGE
            );

            $country = $pclass->getCountry();
            $currency = KlarnaCurrency::getCode(KlarnaCountry::getCurrency($pclass->getCountry()));
            
            try{
                $currency = Mage::app()->getLocale()->currency(strtoupper($currency))->getSymbol();
            }            
            catch(Exception $e){
                Mage::logException($e);
            }

            return $value . $currency;
        }

        return false;
    }

    /**
     * Update Klarna PClasses
     * 
     * @return string
     */
    public function updatePClasses()
    {
        try {
            $this->klarna->fetchPClasses();
            return $this->helper->__('PClasses updated successfully');
        }
        catch(Exception $e) {
            Mage::logException($e);
            return $e->getMessage();
        }
    }
}
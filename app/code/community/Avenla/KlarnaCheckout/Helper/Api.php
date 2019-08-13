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
class Avenla_KlarnaCheckout_Helper_Api extends Mage_Core_Helper_Abstract
{

    /**
     * Get default country from store config
     * 
     * @return  string
     */
    public function getCountry()
	{
        return Mage::getStoreConfig('general/country/default');
	}
	
	/**
     * Get Klarna reservation number from order
     * 
	 * @param	Mage_Sales_Model_Order $mo
     * @return 	mixed
     */
    public function getReservationNumber($mo)
    {
        if($rno = $mo->getPayment()->getAdditionalInformation("klarna_order_reservation"))
            return $rno;

        return false;
    }

	/**
     * Get Klarna invoice numbers from order
     * 
	 * @param	Mage_Sales_Model_Order $mo
     * @return	array
     */
    public function getKlarnaInvoices($mo)
    {
        if($result = $mo->getPayment()->getAdditionalInformation("klarna_order_invoice"))
            return $result;

        return array();
    }

	/**
     * Save Klarna invoice numbers to order
     * 
	 * @param	Mage_Sales_Model_Order $mo
	 * @param	array $klarnainvoices
     * @return	Mage_Sales_Model_Order
     */
    public function saveKlarnaInvoices($mo, $klarnainvoices)
    {
        $mo->getPayment()->setAdditionalInformation("klarna_order_invoice", $klarnainvoices);
        return $mo;
    }

	/**
     * Handle failed activation 
     * 
	 * @param	Mage_Sales_Model_Order $mo
	 * @param	string $rno
	 * @param	Exception $e
     */
    public function failedActivation($mo, $rno, $e)
    {
        $mo->addStatusHistoryComment(
            $this->__('Failed to activate reservation %s', $rno) ."(" . $e->getMessage() . ")"
            );
        $mo->save();
        Mage::unregister('kco_save');
        Mage::logException($e);
    }

	/**
     * Get Klarna API documentation URL
     * 
     */
    public function getApiDocumentationUrl()
    {
        return Avenla_KlarnaCheckout_Model_Config::KLARNA_DOC_URL;
    }
}
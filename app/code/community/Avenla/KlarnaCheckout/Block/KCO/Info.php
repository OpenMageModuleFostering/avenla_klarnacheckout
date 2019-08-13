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
class Avenla_KlarnaCheckout_Block_KCO_Info extends Mage_Payment_Block_Info
{
    protected function _toHtml()
    {
		$this->setTemplate('KCO/info.phtml');  
		$helper = Mage::helper("klarnaCheckout");
		$payment = $this->getMethod();

		$this->assign('info', $this->getInfo());
		$this->assign('imgSrc', $helper->getLogoSrc());
		$this->assign('guiUrl', $helper->getKlarnaMerchantsUrl());
        
		if (count($this->getInfo()->getAdditionalInformation("klarna_order_invoice")) > 0){
			$server = $this->getInfo()->getAdditionalInformation("klarna_server");
			$this->assign('pdfUrl', $server . "/packslips/");
		}
        
		if (strlen($this->getInfo()->getAdditionalInformation("klarna_message")) > 0)
			$this->assign('message', $this->getInfo()->getAdditionalInformation("klarna_message"));
        
		return parent::_toHtml();
    }
}

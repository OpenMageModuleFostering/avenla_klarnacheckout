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
class Avenla_KlarnaCheckout_Adminhtml_KCOController extends Mage_Adminhtml_Controller_Action
{
	/**
	 *	Update Klarna PClasses
	 *
	 */
    public function updatePClassesAction()
    {
        $result = Mage::getModel('klarnaCheckout/api')->updatePClasses();
        Mage::app()->getResponse()->setBody($result);
    }
}
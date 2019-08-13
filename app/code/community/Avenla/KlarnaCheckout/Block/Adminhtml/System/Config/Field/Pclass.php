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
class Avenla_KlarnaCheckout_Block_Adminhtml_System_Config_Field_Pclass extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Set template
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('KCO/system/config/field/pclass.phtml');
    }

    public function getButtonHtml()
    {
        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setLabel(Mage::helper('klarnaCheckout')->__('Update PClasses'))
            ->setOnClick("javascript:updatePClasses(); return false;")
            ->toHtml();

        return $html;
    }

    /**
     * Return element html
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Get url for update action
     *
     * @return string
     */
    public function getAjaxUpdateUrl()
    {
        return Mage::getUrl('klarnaCheckout/adminhtml_KCO/updatePClasses/');
    }
}

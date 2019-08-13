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

class Avenla_KlarnaCheckout_Block_Catalog_Product_Ppwidget extends Mage_Core_Block_Template
{    
    protected   $_product = null;
    private     $config;

    public function __construct()
    {
        $this->getProduct();
        $this->config = Mage::getSingleton('klarnaCheckout/KCO')->getConfig();

        return parent::_construct();
    }

    public function getWidgetParams()
    {
        return array(
            'width'     => 210,
            'height'    => 70,
            'eid'       => $this->config->getKlarnaEid(),
            'locale'    => Mage::app()->getLocale()->getLocaleCode(),
            'price'     => $this->getPrice(),
            'layout'    => $this->config->getPpWidgetLayout()
        );
    }

    public function getProduct()
    {
        if (!$this->_product) {
            $this->_product = Mage::registry('product');
        }
    }

    private function getPrice()
    {
        return $this->_product->getFinalPrice();
    }
}
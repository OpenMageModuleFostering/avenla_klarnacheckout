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

class Avenla_KlarnaCheckout_Block_Catalog_Product_Price extends Mage_Bundle_Block_Catalog_Product_Price
{   
    private $config;

    public function __construct()
    {
        $this->config = Mage::getSingleton('klarnaCheckout/KCO')->getConfig();
        return parent::_construct();
    }  

    protected function _toHtml()
    {
        $html = parent::_toHtml();
        if($this->getLayout()->getBlock('klarnaCheckout_price') && $this->getRequest()->getControllerName() == 'product')
            return $html;
        
        if($type = $this->getWidgetType()){
            if($this->getRequest()->getControllerName()=='category' && $this->config->getPpWidgetSelection() != "product_list")
                return $html;

            $html .= $this->getLayout()->createBlock('core/template', 'klarnaCheckout_price')
                ->setWidgetType($this->getWidgetType())
                ->setWidgetData($this->getWidgetData())
                ->setTemplate('KCO/catalog/product/price.phtml')->toHtml();
        }

        return $html;
    }

    /**
     *  Get widget type
     *
     *  @return string | false
     */
    public function getWidgetType()
    {
        $selection = $this->config->getPpWidgetSelection();
        if($selection == 'product' || $selection == 'product_list'){
            return "product";
        }
        else if($selection == 'klarna'){
            return $selection;
        }
        
        return false;
    }

    /**
     *  Get widget data
     *
     *  @return mixed | false
     */
    public function getWidgetData()
    {
        $price = $this->_getPrice();

        if($price < 0.1)
            return false;

        if($this->getWidgetType() == "product"){
            if($widgetText = Mage::getModel('klarnaCheckout/api')->getMonthlyPrice($price)){
                return $this->__("From %s/mo.", $widgetText);
            }
        }
        else if($this->getWidgetType() == "klarna"){
            return array(
                'width'     => 210,
                'height'    => 70,
                'eid'       => $this->config->getKlarnaEid(),
                'locale'    => Mage::app()->getLocale()->getLocaleCode(),
                'price'     => $price,
                'layout'    => $this->config->getPpWidgetLayout()
            );
        }

        return false;
    }

    /**
     * Get product price
     *
     * @return float
     */
    private function _getPrice()
    {
        if($this->getDisplayMinimalPrice()){
            $price = $this->getProduct()->getMinimalPrice();
        }
        else{
            $price = $this->getProduct()->getFinalPrice();   
        }

        $c = Mage::app()->getStore()->getCurrentCurrencyCode();
        $bc = Mage::app()->getStore()->getBaseCurrencyCode();
        $rate = 1;
        
        if ($bc != $c) {
            $currency = Mage::getModel('directory/currency');
            $currency->load($bc);
            $rate = $currency->getRate($c);
        }

        return $this->helper('tax')->getPrice(
            $this->getProduct(),
            $price,
            true
        ) * $rate;
    }
}
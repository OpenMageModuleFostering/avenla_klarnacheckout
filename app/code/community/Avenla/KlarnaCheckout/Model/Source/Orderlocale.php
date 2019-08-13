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

class Avenla_KlarnaCheckout_Model_Source_Orderlocale
{   
    public function toOptionArray()
    {
        return array(
            array(
                'label' => Mage::app()->getLocale()->getCountryTranslation('FI'),
                'value' => 'fi-fi'
            ),
            array(
                'label' => Mage::app()->getLocale()->getCountryTranslation('SE'),
                'value' => 'sv-se'
            ),
            array(
                'label' => Mage::app()->getLocale()->getCountryTranslation('NO'),
                'value' => 'nb-no'
            ), 
            array(
                'label' => Mage::app()->getLocale()->getCountryTranslation('DE'),
                'value' => 'de-de'
            )
        );        
    }
}

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
class Avenla_KlarnaCheckout_KCOController extends Mage_Core_Controller_Front_Action
{
    /**
     *  Load Klarna Checkout iframe
     *
     */
    public function loadKcoFrameAction()
    {
        $mobile = false;
		
        if($this->getRequest()->getParam('mobile') == true)
            $mobile = true;
	
        $result = array();

        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $kco = Mage::getModel('klarnaCheckout/KCO');
        
        if (!$quote->validateMinimumAmount()){
            $minimumAmount = Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())
                ->toCurrency(Mage::getStoreConfig('sales/minimum_order/amount'));

            $warning = Mage::getStoreConfig('sales/minimum_order/description')
                ? Mage::getStoreConfig('sales/minimum_order/description')
                : Mage::helper('checkout')->__('Minimum order amount is %s', $minimumAmount);
				
            $result['msg'] = $warning;
        }
		
        if(!$kco->isAvailable($quote)){
            $result['msg'] = $this->__("Klarna Checkout is not available");
        }
        else{
            $ko = null;
            $kcoOrder = Mage::getModel("klarnaCheckout/order");
            
            if (array_key_exists('klarna_checkout', $_SESSION))
                $ko = $kcoOrder->getOrder($quote, $_SESSION['klarna_checkout'], $mobile);
            
            if ($ko == null)
               $ko = $kcoOrder->getOrder($quote, null, $mobile);
            
            if($ko != null){
                $_SESSION['klarna_checkout'] = $sessionId = $ko->getLocation();
                
                if($quote->getShippingAddress()->getPostcode() == null)
                        $result['msg'] = $this->__("Please fill in your post code");
					
                if($quote->getShippingAddress()->getCountry() == null)
                        $result['msg'] = $this->__("Please select country");
				
                if (!$quote->isVirtual() && $quote->getShippingAddress()->getShippingMethod() == null)
                    $result['msg'] = $this->__("Please select shipping method to use Klarna Checkout");

                $result['klarnaframe'] = $ko['gui']['snippet'];            
            }
        }
        
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
	
    /**
     *  Confirmation action for Klarna Checkout
     *
     */
    public function confirmationAction()
    {
        $redirect = false;
        @$checkoutId = $_GET['klarna_order'];
        $ko = Mage::getModel("klarnaCheckout/order")->getOrder(null, $checkoutId);

        try{
            $ko->fetch();
            if ($ko['status'] == "checkout_complete" || $ko['status'] == "created"){
                $this->emptyCart();
                $this->loadLayout();
                $this->getLayout()->getBlock('klarnaCheckout.confirmation')->setCheckoutID($checkoutId);
                $this->renderLayout();
            }
            else{
                $redirect = true;
            }
        }
        catch(Exception $e) {
            Mage::logException($e);
            $redirect = true;
        }
        
        if($redirect){
            header('Location: ' . Mage::helper('checkout/url')->getCartUrl()); 
            exit();
        }
    }
    
    /**
     *  Validation action for Klarna Checkout
     *
     */
    public function validationAction()
    {   
        $validator = Mage::getModel('klarnaCheckout/validator');

        $ko = $validator->parseValidationPost();
        $quote = Mage::getModel("sales/quote")->load($ko->merchant_reference->orderid1);
        
        if($validator->validateQuote($quote, $ko)){
            header("HTTP/1.1 200 OK");
        }
        else{
            Mage::getSingleton('core/session')->setSessionId($_GET['sid']);
            header("HTTP/1.0 303 See Other");
            Mage::app()->getFrontController()->getResponse()->setRedirect(
                Mage::helper('checkout/url')->getCartUrl()
            )->sendResponse();
        }
        exit();
    }

    /**
     * Convert Klarna address to Magento address
     *
     * @param  array $address
     * @param  string $region
     * @param  string $region_code
     */
    private function convertAddress($address, $region = '', $region_code = '')
    {
        $country_id = strtoupper($address['country']);

        if($region_code == '')
            $region_code = 1;

        $street = isset($address['street_address']) 
        	? $address['street_address'] 
        	: $address['street_name']  . " " . $address['street_number'];

        $magentoAddress = array(
            'firstname'             => $address['given_name'],
            'lastname'              => $address['family_name'],
            'email'                 => $address['email'],
            'street'                => $street,
            'city'                  => $address['city'],
            'region_id'             => $region_code,
            'region'                => $region,
            'postcode'              => $address['postal_code'],
            'country_id'            => strtoupper($address['country']),
            'telephone'             => $address['phone']
        );
 
        return $magentoAddress;
    }
	
    /**
     * Activate Klarna reservation (manually from order view)
     * 
     */
    public function activateReservationAction()
    {
        try {
            if($orderId = $this->getRequest()->getParam('order_id')){
                $order = Mage::getModel('sales/order')->load($orderId);

                if(Mage::helper('klarnaCheckout/api')->getReservationNumber($order) !== false){
                    Mage::register('kco_save', true);
                    $currentId = Mage::app()->getStore()->getStoreId();
                    Mage::app()->setCurrentStore($order->getStore()->getStoreId());
                    Mage::getModel("klarnaCheckout/api")->activateFullReservation($order);
					Mage::app()->setCurrentStore($currentId);
                    Mage::unregister('kco_save');
                }
                else{
                    $this->_getSession()->addError($this->__('No Klarna reservation number found in order'));
                        $this->_redirectReferer();
                    
                    return;
                }
            }
        }
        catch(Exception $e) {
            $this->_getSession()->addError($this->__($e->getMessage()));
            $this->_redirectReferer();
        }
        $this->_redirectReferer();
    }
    
    /**
     *  Push action for Klarna Checkout
     *
     */
    public function pushAction()
    {
        @$checkoutId = $_GET['klarna_order'];
        Mage::app()->setCurrentStore($_GET['storeid']);
        $ko = Mage::getModel("klarnaCheckout/order")->getOrder(null, $checkoutId);
        $ko->fetch();
        $quoteID = $ko['merchant_reference']['orderid1'];
		
        if ($ko['status'] == "checkout_complete" && $quoteID){
            $quote = Mage::getModel("sales/quote")->load($quoteID);

            if(count($quote->getAllItems()) < 1){
                Mage::log("No valid quote found for Klarna order, reservation canceled.");
                Mage::getModel('klarnaCheckout/api')->cancelReservation($ko['reservation']);
                return;
            }
			
            $mo = $this->quoteToOrder($quote, $ko);
			
            $url = Mage::getSingleton('klarnaCheckout/KCO')->getConfig()->isLive() 
                ?  Avenla_KlarnaCheckout_Model_Config::KCO_LIVE_S_URL 
                :  Avenla_KlarnaCheckout_Model_Config::KCO_DEMO_S_URL;
            
            $mo->getPayment()->setAdditionalInformation("klarna_server", $url);
            $mo->getPayment()->setAdditionalInformation("klarna_order_id", $ko['id']);
            $mo->getPayment()->setAdditionalInformation("klarna_order_reference", $ko['reference']);
            $mo->getPayment()->setAdditionalInformation("klarna_order_reservation", $ko['reservation']);
            $mo->getPayment()->setAdditionalInformation("klarna_order_reservation_expiration", $ko['expires_at']);
            $mo->getPayment()->save();
            
            $update['merchant_reference']['orderid1'] = $mo->getIncrementId();
            $update['status'] = 'created';
            $ko->update($update);

            if($ko['status'] != "created"){
                $this->cancelOrder($mo, $this->__('Order canceled: Failed to create order in Klarna.'));
            }
            else{
                $mo->getSendConfirmation(null);
                $mo->sendNewOrderEmail();

                if($mo->getPayment()->getAdditionalInformation("klarna_newsletter"))
                    Mage::getModel('klarnaCheckout/newsletter')->signForNewsletter($mo, Mage::app()->getStore()->getWebsiteId());
            }
        }
        else{
            if($ko['status'] != "checkout_complete")
                Mage::log("Klarna reservation " . $ko['reservation'] ." got to pushAction with status: " . $ko['status']);
            
            if(!$quoteID){
                Mage::getModel('klarnaCheckout/api')->cancelReservation($ko['reservation']);
                Mage::log("Couldn't find quote id for Klarna reservation " . $ko['reservation']);
            }
        }
    }
    
    /**
     * Convert Magento quote to order
     * 
     * @param   Mage_Sales_Model_Quote
     * @param   Klarna_Checkout_Order
     * @return  Mage_Sales_Model_Order
     */
    private function quoteToOrder($quote, $ko)
	{
        $quote->setCustomerEmail($ko['billing_address']['email'])->save();
        $quote->getBillingAddress()->addData($this->convertAddress($ko['billing_address']));
        $quote->getShippingAddress()->addData($this->convertAddress($ko['shipping_address']));
        $quote->getPayment()->setMethod(Mage::getModel("klarnaCheckout/KCO")->getCode());
        $quote->collectTotals()->save();
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();
		
        return $service->getOrder();
    }
    
    /**
     * Cancel Magento order
     * 
     * @param   Mage_Sales_Model_Order
     * @param   string  msg
     */
    private function cancelOrder($mo, $msg)
    {
        $mo->cancel();
        $mo->setStatus($msg);
        $mo->save();
    }
	
    /**
     * Clear the checkout session after successful checkout
     *
     */
    private function emptyCart()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $quote->setIsActive(false)->save();
        Mage::getSingleton('checkout/session')->clear();
    }

    /**
     * Subscribe to newsletter
     * 
     */
    public function newsletterAction()
    {
        $result = array();
        $status = false;
        $customerSession = Mage::getSingleton('customer/session');

        if($this->getRequest()->getParam('status') == true)
            $status = true;

        if ($status && Mage::getStoreConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG) != 1 && 
            !$customerSession->isLoggedIn()) {
            $result['letter_msg'] = $this->__(
                'Sorry, but administrator denied subscription for guests. Please <a href="%s">register</a>.',
                Mage::helper('customer')->getRegisterUrl()
            );
            $status = false;
        }

        $result['msg'] = ' ';

        Mage::getModel('klarnaCheckout/newsletter')->addNewsletterStatus($status);
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
}

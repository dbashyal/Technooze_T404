<?php
/**
 * @category    Technooze
 * @package     Technooze_T404
 * @author      Damodar Bashyal (github.com/dbashyal/Technooze_T404)
 * @url         http://dltr.org (visit for more magento tips and tricks)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Technooze_T404_Model_Observer {

    public function frontInitBefore(Varien_Event_Observer $observer){
        return; // this can be enabled for quick debug.

        $request = $observer->getEvent()->getFront()->getRequest();


        $info = array(
            'base_url' => Mage::getBaseUrl(),
            'request_path' => $request->getOriginalPathInfo(),
            'path' => $request->getPathInfo(),
            'IP1' => $request->getClientIp(),
            'IP2' => $request->getClientIp(false),
            'params' => $request->getParams(),
            'store' => Mage::app()->getStore()->getData(),
        );
        Mage::log($info);
    }

    /**
     * 404 logger
     *
     * @param Varien_Event_Observer $observer
     * @return Technooze_T404_Model_Observer
     */
    public function noRoute(Varien_Event_Observer $observer) {
        $event = $observer->getEvent();
        $request = $event->getControllerAction()->getRequest();
        $actionName = $request->getActionName();

        if ($actionName == 'noRoute') {
            $requestUrl = rtrim($request->getScheme() . '://' . $request->getHttpHost() . $request->getRequestUri(), '/');
            $mageUrl = Mage::helper('core/url')->getCurrentUrl();

            $info = array(
                'base_url' => Mage::getBaseUrl(),
                'request_url' => $requestUrl,
                'mage_url' => $mageUrl,
                'path' => $request->getPathInfo(),
                'controller_name' => $request->getControllerName(),
                'route_name' => $request->getRouteName(),
                'module_name' => $request->getModuleName(),
                'IP1' => $request->getClientIp(),
                'IP2' => $request->getClientIp(false),
                'params' => $request->getParams(),
                'store' => Mage::app()->getStore()->getData(),
                'cookie' => Mage::getModel('core/cookie')->get('store'),
            );

            Mage::log($info, 7, '404-'.date("Ymd", time()).'.log', true);

            /*
             * reload site without param ___store
             */
            $this->redirect($mageUrl, $event->getControllerAction()->getResponse());
        }
        return $this;
    }

    /**
     * @param string $url
     * @param Mage_Core_Controller_Response_Http $response
     * @return $this
     */
    public function redirect($url='', Mage_Core_Controller_Response_Http $response){
        $storeParam     = Mage::app()->getRequest()->getParam('___store', false);
        $storeCookie    = Mage::getModel('core/cookie')->get('store');
        if($url || $storeParam || $storeCookie){
            Mage::getModel('core/cookie')->delete('store');
            $url = trim(str_replace("___store={$storeParam}", '', $url), '?');
            $response->setRedirect($url)->sendResponse();
            exit;
        }
        return $this;
    }
}
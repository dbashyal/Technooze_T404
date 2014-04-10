<?php
/**
 * @category   Technooze
 * @package    Technooze_T404
 * @author     Damodar Bashyal (github.com/dbashyal/Technooze_T404)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Technooze_T404_Model_Observer {

    /**
     * 404 logger
     *
     * @param Varien_Event_Observer $observer
     */
    public function noRoute(Varien_Event_Observer $observer) {
        $request = $observer->getEvent()->getControllerAction()->getRequest();
        $actionName = $request->getActionName();

        if ($actionName == 'noRoute') {
            $requestUrl = rtrim($request->getScheme() . '://' . $request->getHttpHost() . $request->getRequestUri(), '/');
            $info = array(
                'request_url' => $requestUrl,
                'controller_name' => $request->getControllerName(),
                'route_name' => $request->getRouteName(),
                'module_name' => $request->getModuleName(),
                'requested_route_name' => $request->getRequestedRouteName(),
                'requested_controller_name' => $request->getRequestedControllerName(),
                'requested_action_name' => $request->getRequestedActionName(),
                'IP1' => $request->getClientIp(),
                'IP2' => $request->getClientIp(false),
                'params' => $request->getParams(),
            );

            Mage::log($info, 7, '404-'.date("Ymd", time()).'.log', true);
        }
        return;
    }
}
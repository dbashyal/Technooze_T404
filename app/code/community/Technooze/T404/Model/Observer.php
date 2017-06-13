<?php

/**
 * @category    Technooze
 * @package     Technooze_T404
 * @author      Damodar Bashyal (github.com/dbashyal/Technooze_T404)
 * @url         http://dltr.org (visit for more magento tips and tricks)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Technooze_T404_Model_Observer
{
    private $_observer;
    private $_event;
    private $_request;
    private $_response;
    private $_checked_product_store = false;
    private $_current_website_id = 0;
    private $_current_store_id = 0;

    public function frontInitBefore(Varien_Event_Observer $observer)
    {
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
    public function noRoute(Varien_Event_Observer $observer)
    {
        $this->_observer = $observer;
        $this->_event = $observer->getEvent();
        $this->_request = $this->_event->getControllerAction()->getRequest();
        $actionName = $this->_request->getActionName();
        $this->_response = $this->_event->getControllerAction()->getResponse();

        if ($actionName == 'noRoute') {
            $requestUrl = rtrim($this->_request->getScheme() . '://' . $this->_request->getHttpHost() . $this->_request->getRequestUri(), '/');

            $info = array(
                'base_url' => Mage::getBaseUrl(),
                'request_url' => $requestUrl,
                'mage_url' => Mage::helper('core/url')->getCurrentUrl(),
                'path' => $this->_request->getPathInfo(),
                'controller_name' => $this->_request->getControllerName(),
                'route_name' => $this->_request->getRouteName(),
                'module_name' => $this->_request->getModuleName(),
                'IP1' => $this->_request->getClientIp(),
                'IP2' => $this->_request->getClientIp(false),
                'params' => $this->_request->getParams(),
                'store' => Mage::app()->getStore()->getData(),
                'cookie' => Mage::getModel('core/cookie')->get('store'),
            );

            Mage::log($info, 7, '404-' . date("Ymd", time()) . '.log', true);

            // condition: 1
            // try reloading site without param ___store
            $this->reloadWithoutStoreCode();

            // condition: 2
            // if it reaches here, that means, this is not store code related issue.
            // let's check if requested path is sku of the product
            // i.e. http://store-url/sku
            $this->loadProductPageUsingSku();

            // condition: 3
            // it could be a product associated with configurable product,
            // so reload using parent product url
            // currently it's skipped, so redirectToProductCategory() is used to handle this 404
            //@todo:: not required at the moment

            // condition: 4
            // if this is a disabled product
            // redirect to it's category page
            $this->redirectToProductCategory();

            // condition: 5
            // coming soon: check entries from 404 manager in admin > suggest if you think of any
        } else {
            $moduleName = $this->_request->getModuleName(); // catalog
            $controllerName = $this->_request->getControllerName(); // product
            $actionName = $this->_request->getActionName(); // view
            $id = $this->_request->getParam('id'); // product ID

            //Mage::log("{$id}-{$moduleName}-{$controllerName}-{$actionName}");

            if ($id && $moduleName == 'catalog' && $controllerName == 'product' && $actionName == 'view') {
                // redirect 'out of stock' products to
                /* @var $_product Mage_Catalog_Model_Product */
                $_product = Mage::getModel('catalog/product')->load($id);
                if ($_product && $_product->getId()) {
                    $isSaleable = $_product->getIsSalable();
                    if (!$isSaleable) {
                        $this->redirectToProductCategory();
                    }
                }
            }
        }
        return $this;
    }

    public function reloadWithoutStoreCode()
    {
        $storeParam = Mage::app()->getRequest()->getParam('___store', false);
        $storeCookie = Mage::getModel('core/cookie')->get('store');
        // lets delete store cookie, so it's loading correct store page.
        if ($storeParam || $storeCookie) {
            Mage::getModel('core/cookie')->delete('store');
            $url = trim(str_replace("___store={$storeParam}", '', Mage::helper('core/url')->getCurrentUrl()), '?');
            $this->_response->setRedirect($url, 301)->sendResponse();
            exit;
        }
        return $this;
    }

    public function loadProductPageUsingSku()
    {
        $path = trim($this->_request->getPathInfo(), '/');

        // check to see if path contains path separators
        if (strpos($path, '/') === false) {
            // let's check if this is sku of the product
            $productid = Mage::getModel('catalog/product')->getIdBySku($path);
            if ($productid) {
                $product = Mage::getModel('catalog/product')->load($productid);
                $this->_response->setRedirect($product->getProductUrl(), 301)->sendResponse();
                exit;
            }
        }
        return $this;
    }

    public function redirectToProductCategory()
    {
        $moduleName = $this->_request->getModuleName(); // catalog
        $controllerName = $this->_request->getControllerName(); // product
        $actionName = $this->_request->getActionName(); // noRoute
        $id = $this->_request->getParam('id'); // product ID

        if ($id && $moduleName == 'catalog' && $controllerName == 'product') {
            // load product to find assigned categories
            $_product = Mage::getModel('catalog/product')->load($id);
            $_categoryIds = $_product->getCategoryIds();
            $_productStoreId = Mage::app()->getStore()->getData('store_id');

            // find the correct store view id
            foreach (Mage::app()->getStores() as $stores) {
                $data = $stores->getData();
                if ($data['website_id'] == $this->getCurrentWebsiteId()) {
                    if (in_array(Mage::app()->getStore($data['store_id'])->getRootCategoryId(), $_categoryIds)) {
                        if (Mage::app()->getStore()->getData('store_id') === $data['store_id']) {
                            // do nothing
                        } else {
                            $_productStoreId = $data['store_id'];
                        }
                        break;
                    }
                }
            }

            $appEmulation = Mage::getSingleton('core/app_emulation');
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($_productStoreId);
            /* @var $categories Mage_Catalog_Model_Resource_Category_Collection */
            $categories = $_product->getCategoryCollection();
            $categories->addFieldToFilter('path', array('like' => "1/" . Mage::app()->getStore($_productStoreId)->getRootCategoryId() . "/%"));
            $categories->addAttributeToFilter('is_active', array('eq'=>'1'));
            $categories->addAttributeToSort('level', 'desc'); // get the top most category

            /* @var $category Mage_Catalog_Model_Category */
            $category = $categories->getLastItem();
            $_categoryUrl = $category->getUrl();
            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);

            $_productName = $_product->getName();
            $_productSku = $_product->getSKU();
            $msg = Mage::helper('core')->__('Product "%s" (SKU: %s) is no longer in stock. Please check other items from same category.', $_productName, $_productSku);
            Mage::getSingleton('core/session')->addNotice($msg);

            $this->_response->setRedirect($_categoryUrl, 301)->sendResponse();
            exit;
        }
        return $this;
    }

    private function getCurrentStoreId()
    {
        if (empty($this->_current_store_id)) {
            $this->_current_store_id = Mage::app()->getStore()->getData('store_id');
        }
        return $this->_current_store_id;
    }

    private function getCurrentWebsiteId()
    {
        if (empty($this->_current_website_id)) {
            $this->_current_website_id = Mage::app()->getWebsite()->getData('website_id');
        }
        return $this->_current_website_id;
    }

    private function isCheckedProductStore()
    {
        $this->_checked_product_store = Mage::registry('products_store_checked');
        if (empty($this->_checked_product_store)) {
            Mage::register('products_store_checked', 1);
        }
        return $this->_checked_product_store;
    }
}
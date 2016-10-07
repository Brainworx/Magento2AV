<?php

namespace Mageplaza\LayeredNavigation\Plugins\Controller\Category;

class View
{
	protected $_jsonHelper;
	public function __construct(\Magento\Framework\Json\Helper\Data $jsonHelper){
		$this->_jsonHelper = $jsonHelper;
	}
    public function afterExecute(\Magento\Catalog\Controller\Category\View $action, $page)
	{
		if($action->getRequest()->getParam('isAjax')){
			$navigation = $page->getLayout()->getBlock('catalog.ln.leftnav');
			$products = $page->getLayout()->getBlock('category.products');
			$result = ['products' => $products->toHtml(), 'navigation' => $navigation->toHtml()];
			$action->getResponse()->representJson($this->_jsonHelper->jsonEncode($result));
		} else {
			return $page;
		}
    }
}

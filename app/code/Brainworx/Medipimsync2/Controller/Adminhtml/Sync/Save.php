<?php
namespace Brainworx\Medipimsync2\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;
use Brainworx\Medipimsync2\Helper\SyncHelper;
// use Magento\Framework\App\ResourceConnection;
// use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
// use Zend\Validator\Explode;
// use Magento\Tax\Model\ClassModel;
// use Magento\Catalog\Model\Product\Visibility;
// use Magento\Catalog\Model\Product\Type;
// use Magento\Tax\Api\TaxClassRepositoryInterface;
// use Magento\Framework\Api\FilterBuilder;
// use Magento\Framework\Api\SearchCriteriaBuilder;
// use Magento\Tax\Api\TaxClassManagementInterface;
// use Magento\Variable\Model\Variable;


// use Magento\Catalog\Api\ProductRepositoryInterface;
// use Magento\Catalog\Api\CategoryRepositoryInterface;

//TODO add rest api to execute this sync
class Save extends \Magento\Backend\App\Action
{
// 	protected $_resource;
// 	protected $_storemanager;
	protected $_logger;
	//required to query tax class
// 	protected $_filterBuilder;
// 	protected $_taxClassRepository;
// 	protected $_searchCriteriaBuilder;
	
// 	protected $_productRepo;
// // 	protected $_catsRepo;
	
// 	private $_variableMdl;
// 	private $_id; // your api user id
// 	private $_key; // your secret api key
// 	private $_mediaUrl;
// 	private $_catUrl;
// 	private $_prodUrl;
// 	private $_configdir;
	
	private $_synchelper;
	
	/**
	 * @param Action\Context $context
	 */
	public function __construct(Action\Context $context, 
			SyncHelper $helper,
			LoggerInterface $logger
// 			ResourceConnection $resource, 
// 			StoreManagerInterface $storemanager, 
// 			FilterBuilder $filterBuilder,
// 			TaxClassRepositoryInterface $taxClassRepository,
// 			SearchCriteriaBuilder $searchCriteriaBuilder,
// 			Variable $variableMdl)
			)
	{
		$this->_synchelper = $helper;
// 		$this->_resource = $resource;
// 		$this->_storemanager = $storemanager;
		$this->_logger = $logger;
// 		$this->_filterBuilder = $filterBuilder;
// 		$this->_searchCriteriaBuilder = $searchCriteriaBuilder;
// 		$this->_taxClassRepository = $taxClassRepository;
// 		$this->_variableMdl = $variableMdl;
		
// 		$this->_id = $this->_variableMdl->loadByCode('medipim_api_userid')->getPlainValue();
// 		$this->_key = $this->_variableMdl->loadByCode('medipim_api_key')->getPlainValue();
// 		$this->_mediaUrl = $this->_variableMdl->loadByCode('medipim_api_mediaurl')->getPlainValue();
// 		$this->_catUrl = $this->_variableMdl->loadByCode('medipim_url_cat')->getPlainValue();
// 		$this->_prodUrl = $this->_variableMdl->loadByCode('medipim_url_prod')->getPlainValue();
// 		$this->_configdir = $this->_variableMdl->loadByCode('medipim_sync_config_dir')->getPlainValue();
		
		
		parent::__construct($context);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Brainworx_Medipimsync2::save');
	}

	/**
	 * Save action
	 *
	 * @return \Magento\Framework\Controller\ResultInterface
	 */
	public function execute()
	{
		
		$this->_logger->debug("Starting sync");
		$data = $this->getRequest()->getPostValue();
		/** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
		$resultRedirect = $this->resultRedirectFactory->create();
		
		if ($data) {
			try {
				if($data['entity']=='CAT'){
					$this->_logger->debug("Starting cleanup");
					self::deleteAllCategories();
					$this->_logger->debug("Cleanup completed");
					
					$resultRedirect = $this->resultRedirectFactory->create();
					
					return $resultRedirect->setPath('*/*/');
				}else{
				//temp disabled
// 					/** @var \brainworx\medipimsync\Model\Sync $model */
// 					$model = $this->_objectManager->create('Brainworx\Medipimsync2\Model\Sync');
					
// 					$this->_eventManager->dispatch(
// 							'medipimsync2_sync_prepare_save',
// 							['sync' => $model, 'request' => $this->getRequest()]
// 					);
					
// 					$this->_synchelper->sync($data['entity'],$model);
					
// 					$this->messageManager->addSuccess(__('You performed this sync.'));
// 					$this->_objectManager->get('Magento\Backend\Model\Session')->setFormData(false);
// 					if ($this->getRequest()->getParam('back')) {
// 						return $resultRedirect->setPath('*/*/edit', ['sync_id' => $model->getId(), '_current' => true]);
// 					}		
				}
				
			} catch (\Magento\Framework\Exception\LocalizedException $e) {
				$this->_logger->error("Error medipimsync save ".$e->getLogMessage());
				$this->messageManager->addError($e->getMessage());
			} catch (\RuntimeException $e) {
				$this->_logger->error("Error medipimsync save ".$e->getMessage());
				$this->messageManager->addError($e->getMessage());
			} catch (\Exception $e) {
				$this->_logger->error("Error medipimsync save ".$e->getMessage());
				$this->messageManager->addException($e, __('Something went wrong while executing the sync:'.$e->getMessage()));
			}
		}
		return $resultRedirect->setPath('*/*/');
	}
	function deleteAllCategories() {
	
		$categoryFactory = $this->_objectManager->get('Magento\Catalog\Model\CategoryFactory');
		$newCategory = $categoryFactory->create();
		$collection = $newCategory->getCollection();
		$this->_objectManager->get('Magento\Framework\Registry')->register('isSecureArea', true);
	
		foreach($collection as $category) {
	
			$category_id = $category->getId();
	
			if( $category_id <= 2 ) continue;
	
			try {
				$category->delete();
	
			} catch (Exception $e) {
				echo 'Failed to remove category '.$category_id .PHP_EOL;
				echo $e->getMessage() . "\n" .PHP_EOL;
			}
			echo 'Categories Removed '.PHP_EOL;
		}
	}
}

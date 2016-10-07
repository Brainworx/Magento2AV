<?php
namespace Brainworx\Medipimsync2\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;

class Edit extends \Magento\Backend\App\Action
{	
	/**
	 * Core registry
	 *
	 * @var \Magento\Framework\Registry
	 */
	protected $_coreRegistry = null;
	
	/**
	 * @var \Magento\Framework\View\Result\PageFactory
	 */
	protected $resultPageFactory;

	/**
     * @param Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Registry $registry
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->_coreRegistry = $registry;
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
	 * Init actions
	 *
	 * @return \Magento\Backend\Model\View\Result\Page
	 */
	protected function _initAction()
	{
		// load layout, set active menu and breadcrumbs
		/** @var \Magento\Backend\Model\View\Result\Page $resultPage */
		$resultPage = $this->resultPageFactory->create();
		$resultPage->setActiveMenu('Brainworx_Medipimsync2::sync')
		->addBreadcrumb(__('Sync'), __('Sync'))
		->addBreadcrumb(__('Manage Medipim Syncs'), __('Manage Medipim Syncs'));
		return $resultPage;
	}

	/**
	 * Forward to edit
	 *
	 * @return \Magento\Backend\Model\View\Result\Forward
	 */
	public function execute()
	{
		$id = $this->getRequest()->getParam('sync_id');
        $model = $this->_objectManager->create('Brainworx\Medipimsync2\Model\Sync');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addError(__('This sync no longer exists.'));
                /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            }
        }

        $data = $this->_objectManager->get('Magento\Backend\Model\Session')->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        $this->_coreRegistry->register('medipimsync2_synced', $model);

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->_initAction();
        $resultPage->addBreadcrumb(
            $id ? __('Edit Medipim Sync') : __('New Medipim Sync'),
            $id ? __('Edit Medipim Sync') : __('New Medipim Sync')
        );
        $resultPage->getConfig()->getTitle()->prepend(__('Medipim Syncs'));
        $resultPage->getConfig()->getTitle()
            ->prepend($model->getId() ? $model->getTitle() : __('New Medipim Sync'));

        return $resultPage;
	}
}
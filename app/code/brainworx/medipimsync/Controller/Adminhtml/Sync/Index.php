<?php
namespace brainworx\medipimsync\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends \Magento\Backend\App\Action
{
	/**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Index action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Brainworx_Medipimsync::sync');
        $resultPage->addBreadcrumb(__('Synced Entities'), __('Syned Entities'));
        $resultPage->addBreadcrumb(__('Manage Synced Entities'), __('Manage Synced Entities'));
        $resultPage->getConfig()->getTitle()->prepend(__('Synced Entities'));

        return $resultPage;
    }

    /**
     * Is the user allowed to view the synced entities grid.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Brainworx_Medipimsync::sync');
    }

}
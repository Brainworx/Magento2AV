<?php
namespace Brainworx\Medipimsync2\Controller\Adminhtml\Sync;

use Magento\Backend\App\Action;

class NewAction extends \Magento\Backend\App\Action
{
	/**
	 * @var \Magento\Backend\Model\View\Result\Forward
	 */
	protected $resultForwardFactory;
	
	protected $_helper;

	/**
	 * @param \Magento\Backend\App\Action\Context $context
	 * @param \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory
	 */
	public function __construct(
			\Magento\Backend\App\Action\Context $context,
			\Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory)
	{
		$this->resultForwardFactory = $resultForwardFactory;
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
	 * Forward to edit
	 *
	 * @return \Magento\Backend\Model\View\Result\Forward
	 */
	public function execute()
	{
		/** @var \Magento\Backend\Model\View\Result\Forward $resultForward */
		$resultForward = $this->resultForwardFactory->create();
		return $resultForward->forward('edit');
	}
}
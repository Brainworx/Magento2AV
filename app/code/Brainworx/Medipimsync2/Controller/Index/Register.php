<?php
namespace Brainworx\Medipimsync2\Controller\Index;

use \Magento\Framework\App\Action\Action;

/**
 * This will map to: /medipimsync/ /medipimsync/index and /medipimsync2/trigger/register.
 * @author Stijn
 *
 */
class Register extends Action
{
	/** @var  \Magento\Framework\View\Result\Page */
	protected $resultPageFactory;
	/**
	 * @param \Magento\Framework\App\Action\Context $context
	 */
	public function __construct(\Magento\Framework\App\Action\Context $context,
			\Magento\Framework\View\Result\PageFactory $resultPageFactory)
	{
		$this->resultPageFactory = $resultPageFactory;
		parent::__construct($context);
	}

	/**
	 * Medipimsync Index, shows a list of recent medipim syncs.
	 *
	 * @return \Magento\Framework\View\Result\PageFactory
	 */
	public function execute()
	{
		$data = $this->getRequest()->getPostValue();
		
		$model = $this->_objectManager->create('Brainworx\Medipimsync2\Model\Sync');
		
		$latestsync = $this->_objectManager->create('Brainworx\Medipimsync2\Model\Sync');
		$model->setEntity($data['entity']);
		$model->setQtyInsrt($data['qtyinsert']);
		$model->setQtyUpdt($data['qtyupd']);
		$model->setQtyTot($data['total']);
		$model->setIsEnded(true);
		$model->setUpdateDttm($data['time']);
		$model->setSyncDttm($data['time']);
		$model->save();
		
		//die ('found');
		//return $this->resultPageFactory->create();
	}
}
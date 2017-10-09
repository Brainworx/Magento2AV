<?php
namespace Brainworx\Medipimsync2\Controller\Index;

use \Magento\Framework\App\Action\Action;

/**
 * This will map to: /medipimsync/ /medipimsync/index and /medipimsync2/index/index.
 * @author Stijn
 *
 */
class Index extends Action
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
		die ('found');
		//return $this->resultPageFactory->create();
	}
}
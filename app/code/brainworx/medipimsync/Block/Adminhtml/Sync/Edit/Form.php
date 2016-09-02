<?php
namespace brainworx\medipimsync\Block\Adminhtml\Sync\Edit;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\Data\FormFactory;
use Magento\Store\Model\System\Store;
use Magento\Backend\Model\Auth\Session;

/**
 * Adminhtml sync edit form
 */
class Form extends \Magento\Backend\Block\Widget\Form\Generic
{

	/**
	 * @var \Magento\Store\Model\System\Store
	 */
	protected $_systemStore;
	protected $_loginUser;

	/**
	 * @param \Magento\Backend\Block\Template\Context $context
	 * @param \Magento\Framework\Registry $registry
	 * @param \Magento\Framework\Data\FormFactory $formFactory
	 * @param \Magento\Cms\Model\Wysiwyg\Config $wysiwygConfig
	 * @param \Magento\Store\Model\System\Store $systemStore
	 * @param array $data
	 * @param \Magento\backend\Model\Auth\Session $authSession
	 */
	public function __construct(
			Context $context,Registry $registry,FormFactory $formFactory,Store $systemStore,
			array $data = [],Session $authSession
	) {
		$this->_systemStore = $systemStore;
		$this->_loginUser = $authSession->getUser()->getUsername();
		parent::__construct($context, $registry, $formFactory, $data);
	}

	/**
	 * Init form
	 *
	 * @return void
	 */
	protected function _construct()
	{
		parent::_construct();
		$this->setId('sync_form');
		$this->setTitle(__('Sync Information'));
	}

	/**
	 * Prepare form
	 *
	 * @return $this
	 */
	protected function _prepareForm()
	{		
		/** @var \brainworx\medipimsync\Model\Sync $model */
		$model = $this->_coreRegistry->registry('medipimsync_sync');

		/** @var \Magento\Framework\Data\Form $form */
		$form = $this->_formFactory->create(
				['data' => ['id' => 'edit_form','action' => $this->getData('action'), 'method' => 'post']]
		);

		$form->setHtmlIdPrefix('sync_');

		$fieldset = $form->addFieldset(
				'base_fieldset',
				['legend' => __('General Information'), 'class' => 'fieldset-wide']
		);

		$fieldset->addField(
				'title',
				'text',
				['name' => 'user', 'label' => __('User'), 'title' => __('User'), 'required' => true,
						'value' => $this->_loginUser , 'readonly' => true
				]
		);

		$fieldset->addField(
				'entity',
				'select',
				[
						'label' => __('Entity'),
						'title' => __('Entity'),
						'name' => 'entity',
						'required' => true,
						'options' => ['PROD' => __('Products'), 'CAT' => __('Categories')]
				]
		);

		//$form->setValues($model->getData());
		$form->setUseContainer(true);
		$this->setForm($form);

		return parent::_prepareForm();
	}
}
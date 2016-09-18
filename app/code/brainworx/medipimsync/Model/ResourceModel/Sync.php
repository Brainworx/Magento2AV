<?php
namespace brainworx\medipimsync\Model\ResourceModel;

/**
 * Medipimsync sync mysql resource
 */
class Sync extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $_date;

    /**
     * Construct
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param string|null $resourcePrefix
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        $resourcePrefix = null
    ) {
        parent::__construct($context, $resourcePrefix);
        $this->_date = $date;
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('brainworx_medipimsync_sync', 'sync_id');
    }

    /**
     * Process post data before saving
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {

        if (!$this->isValidSyncEntity($object)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('The entity to sync must be category or product.')
            );
        }

        if ($object->isObjectNew() && !$object->hasSyncDttm()) {
            //current date
        	$object->setSyncDttm($this->_date->gmtDate());
        }
		//set additional info
		//set user
		
        $object->setUpdateDttm($this->_date->gmtDate());

        return parent::_beforeSave($object);
    }
    /**
     *  Check whether entity is valid
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return bool
     */
    protected function isValidSyncEntity(\Magento\Framework\Model\AbstractModel $object)
    {
    	if($object->getData('entity') == "CAT" || $object->getData('entity') == "PROD" )
    		return true;
    	else 
    		return false;
//     	return preg_match('/^[0-9]+$/', $object->getData('entity'));
    }
    /**
     * Retrieve load select with filter by entity and ended (optional)
     *
     * @param string $entity
     * @param int $isEnded
     * @return latest record
     */
    public function loadBySyncedEntity($entity, $isEnded = null)
    {
    	
    	$select = $this->getConnection()->select()
    	->from(
    			['ms' => $this->getMainTable()])
    	->where(
    			'ms.entity like ?',
    			$entity
    	);
    
    	if (!is_null($isEnded)) {
    		$select->where('ms.is_ended = ?', $isEnded);
    	}
    	$select->order('sync_id DESC');
    	//TODO: check select or fetch row
    
    	return $this->getConnection()->fetchRow($select);
    }
}
<?php 
namespace Brainworx\Medipimsync2\Model\ResourceModel\Sync;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'sync_id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Brainworx\Medipimsync2\Model\Sync', 'Brainworx\Medipimsync2\Model\ResourceModel\Sync');
    }

}
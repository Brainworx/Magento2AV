<?php namespace Brainworx\Medipimsync2\Model;

use Brainworx\Medipimsync2\Api\Data\SyncInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;

class Sync  extends \Magento\Framework\Model\AbstractModel implements SyncInterface, IdentityInterface
{
	/**
	 * Constants for keys of data array. Identical to the name of the getter in snake case
	 */
	const SYNC_ID   	= 'sync_id';
	const ENTITY    	= 'entity';
	const SYNC_DTTM 	= 'sync_dttm';
	const UPDATE_DTTM 	= 'update_dttm';
	const USER      	= 'user';
	const QTY_UPDT		= 'qty_updt';
	const QTY_TOT		= 'qty_tot';
	const QTY_INSRT		= 'qty_insrt';
	const IS_ENDED		= 'is_ended';
	
    /**
     * CMS page cache tag
     */
    const CACHE_TAG = 'medipimsync2_sync';

    /**
     * @var string
     */
    protected $_cacheTag = 'medipimsync2_sync';

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'medipimsync2_sync';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Brainworx\Medipimsync2\Model\ResourceModel\Sync');
    }

    /**
     * Return unique ID(s) for each object in system
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId()
    {
        return $this->getData(self::SYNC_ID);
    }

    /**
     * Get ENTITY
     * 
     * @return string
     */
    public function getEntity()
    {
        return $this->getData(self::ENTITY);
    }

    /**
     * Get QTY_TOT
     *
     * @return int
     */
    public function getQtyTot()
    {
        return $this->getData(self::QTY_TOT);
    }

    /**
     * Get QTY_INSRT
     *
     * @return int
     */
    public function getQtyInsrt()
    {
        return $this->getData(self::QTY_INSRT);
    }

    /**
     * Get QTY_UPDT
     *
     * @return int
     */
    public function getQtyUpdt()
    {
    	return $this->getData(self::QTY_UPDT);
    }

    /**
     * Get SYNC_DTTM
     *
     * @return Datetime|null
     */
    public function getSyncDttm()
    {
        return $this->getData(self::SYNC_DTTM);
    }

    /**
     * Get UPDATE_DTTM
     *
     * @return Datetime|null
     */
    public function getUpdateDttm()
    {
        return $this->getData(self::UPDATE_DTTM);
    }

    /**
     * Get USER
     *
     * @return string|null
     */
    public function getUser()
    {
        return (bool) $this->getData(self::USER);
    }
    /**
     * Is ended
     *
     * @return bool|null
     */
    public function isEnded()
    {
    	return (bool) $this->getData(self::IS_ENDED);
    }
    /**
     * Set ID
     *
     * @param int $id
     * @return \brainworx\medipimsync2\Api\Data\SyncInterface
     */
    public function setId($id)
    {
        return $this->setData(self::SYNC_ID, $id);
    }

    /**
     * Set entity
     *
     * @param string $entity
     * @return \brainworx\medipimsync2\Api\Data\SyncInterface
     */
    public function setEntity($entity)
    {
        return $this->setData(self::ENTITY, $entity);
    }

    /**
     * Set total quantity
     *
     * @param int $qty_tot
     * @return \brainworx\medipimsync2\Api\Data\SyncInterface
     */
    public function setQtyTot($qty_tot)
    {
        return $this->setData(self::QTY_TOT, $qty_tot);
    }

    /**
     * Set quantity inserted
     *
     * @param int $qty_updt
     * @return \brainworx\medipimsync2\Api\Data\SyncInterface
     */
    public function setQtyInsrt($qty_insrt)
    {
        return $this->setData(self::QTY_INSRT, $qty_insrt);
    }
    /**
     * Set quantity updated
     *
     * @param int $qty_updt
     * @return \brainworx\medipimsync2\Api\Data\SyncInterface
     */
    public function setQtyUpdt($qty_updt)
    {
    	return $this->setData(self::QTY_UPDT, $qty_updt);
    }

    /**
     * Set sync start time
     *
     * @param Datetime $sync_dttm
     * @return \brainworx\medipimsync2\Api\Data\SyncInterface
     */
    public function setSyncDttm($sync_dttm)
    {
        return $this->setData(self::SYNC_DTTM, $sync_dttm);
    }

    /**
     * Set sync update time
     *
     * @param Datetime $update_time
     * @return \brainworx\medipimsync2\Api\Data\SyncInterface
     */
    public function setUpdateDttm($update_dttm)
    {
        return $this->setData(self::UPDATE_DTTM, $update_dttm);
    }

    /**
     * Set user
     *
     * @param string $user
     * @return \brainworx\medipimsync2\Api\Data\SyncInterface
     */
    public function setUser($user)
    {
        return $this->setData(self::USER, $user);
    }
    /**
     * Set is ended
     *
     * @param int|bool $is_ended
     * @return \brainworx\medipimsync2\Api\Data\SyncInterface
     */
    public function setIsEnded($is_ended)
    {
    	return $this->setData(self::IS_ENDED, $is_ended);
    }
    public function getAvailableStatuses(){
    	return ['0'=>'N','1'=>'Y'];
    }
    /**
     * Retrieve latest sync for specific enitity
     *
     * @param string $entity
     * @param int $isEnded
     * @return latest record
     */
    public function loadLastSyncByEntity($entity)
    {
    	return $this->_getResource()->loadBySyncedEntity($entity,true);
    }
}
<?php
namespace Brainworx\Medipimsync2\Api\Data;

interface SyncInterface
{
	/**
	 * Constants for keys of data array. Identical to the name of the getter in snake case
	 */
// 	const SYNC_ID   	= 'sync_id';
// 	const ENTITY    	= 'entity';
// 	const SYNC_DTTM 	= 'sync_dttm';
// 	const UPDATE_DTTM 	= 'update_dttm';
// 	const USER      	= 'user';
// 	const QTY_UPDT		= 'qty_updt';
// 	const QTY_INSRT		= 'qty_insrt';
// 	const QTY_TOT		= 'qty_tot';
// 	const IS_ENDED		= 'is_ended';

	/**
	 * Get ID	 *
	 * @return int|null
	 */
	public function getId();
	/**
	 * Set ID
	 *
	 * @param int $id
	 * @return \brainworx\medipimsync\Api\Data\SyncInterface
	 */
	public function setId($id);
	
	/**
	 * Get ENTITY
	 * @return string|null 
	 */
	public function getEntity();
	/**
	 * 
	 * @param string entity
	 * @return \brainworx\medipimsync\Api\Data\SyncInterface
	 */
	public function setEntity($entity);
	
	/**
	 * Get QTY_TOT
	 * @return int
	 */
	public function getQtyTot();
	/**
	 *
	 * @param int qty_tot
	 * @return \brainworx\medipimsync\Api\Data\SyncInterface
	*/
	public function setQtyTot($qty_tot);
	/**
	 * Get QTY_UPDT
	 * @return int
	 */
	public function getQtyUpdt();
	/**
	 *
	 * @param int qty_updt
	 * @return \brainworx\medipimsync\Api\Data\SyncInterface
	 */
	public function setQtyUpdt($qty_updt);
	/**
	 * Get QTY_INSRT
	 * @return int
	 */
	public function getQtyInsrt();
	/**
	 *
	 * @param int qty_insrt
	 * @return \brainworx\medipimsync\Api\Data\SyncInterface
	*/
	public function setQtyInsrt($qty_insrt);	
	/**
	 * Get SYNC_DTTM
	 * @return Datetime|null
	 */
	public function getSyncDttm();
	/**
	 *
	 * @param Datetime sync_dttm
	 * @return \brainworx\medipimsync\Api\Data\SyncInterface
	*/
	public function setSyncDttm($sync_dttm);
	/**
	 * Get UPDATE_DTTM
	 * @return Datetime|null
	 */
	public function getUpdateDttm();
	/**
	 *
	 * @param Datetime update_dttm
	 * @return \brainworx\medipimsync\Api\Data\SyncInterface
	*/
	public function setUpdateDttm($update_dttm);
	
	/**
	 * Get USER
	 * @return string|null
	 */
	public function getUser();
	/**
	 *
	 * @param string user
	 * @return \brainworx\medipimsync\Api\Data\SyncInterface
	*/
	public function setUser($user);
	/**
	 * Is ended
	 *
	 * @return bool|null
	 */
	public function isEnded();
	/**
	 * Set is ended
	 *
	 * @param int|bool $is_ended
	 * @return \brainworx\medipimsync\Api\Data\SyncInterface
	 */
	public function setIsEnded($is_ended);
}
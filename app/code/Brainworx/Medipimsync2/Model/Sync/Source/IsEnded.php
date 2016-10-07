<?php
namespace Brainworx\Medipimsync2\Model\Sync\Source;

class IsEnded implements \Magento\Framework\Data\OptionSourceInterface
{
	/**
	 * @var \Brainworx\Medipimsync2\Model\Sync
	 */
	protected $sync;

	/**
	 * Constructor
	 *
	 * @param \brainworx\medipimsync\Model\Sync $sync
	 */
	public function __construct(\Brainworx\Medipimsync2\Model\Sync $sync)
	{
		$this->sync = $sync;
	}

	/**
	 * Get options
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		$options[] = ['label' => '', 'value' => ''];
		$availableOptions = $this->sync->getAvailableStatuses();
		foreach ($availableOptions as $key => $value) {
			$options[] = [
					'label' => $value,
					'value' => $key,
			];
		}
		return $options;
	}
}
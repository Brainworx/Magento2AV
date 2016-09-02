<?php
namespace brainworx\medipimsync\Model\Sync\Source;

class IsEnded implements \Magento\Framework\Data\OptionSourceInterface
{
	/**
	 * @var \brainworx\medipimsync\Model\Sync
	 */
	protected $sync;

	/**
	 * Constructor
	 *
	 * @param \brainworx\medipimsync\Model\Sync $sync
	 */
	public function __construct(\brainworx\medipimsync\Model\Sync $sync)
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
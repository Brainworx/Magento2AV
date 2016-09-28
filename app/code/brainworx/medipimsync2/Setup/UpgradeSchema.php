<?php
namespace Brainworx\Medipimsync2\Setup;

use Magento\Framework\Module\Setup\Migration;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\DB\Ddl\Table;

use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
	public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
		
		{
			$installer = $setup;
			$installer->startSetup();
	
			//handle all possible upgrade versions
	
			if(!$context->getVersion()) {
				//no previous version found, installation, InstallSchema was just executed
				//be careful, since everything below is true for installation !
			}
	
			if (version_compare($context->getVersion(), '1.0.1') < 0) {
				//code to upgrade to 1.0.1
			
				$tableName = $setup->getTable('catalog_category_entity');
				$setup->getConnection()
				->addColumn($tableName, 'consumer_category_id',[
						'type' => Table::TYPE_INTEGER,
						'nullable' => false, 
						'default' => '0',
						'comment' => 'Consumer Category id',
				]
				);
				$setup->getConnection()
				->addColumn($tableName, 'consumer_cat_parent_id',[
						'type' => Table::TYPE_INTEGER,
						'nullable' => false,
						'default' => '0',
						'comment' => 'Parent Consumer Category id',
				]
				);
				$setup->getConnection()
				->addColumn($tableName, 'last_updated_at',[
						'type' => Table::TYPE_TIMESTAMP,
						'nullable' => true,
						'default' => null,
						'comment' => 'Last update from Medipim',
				]
				);
				
				$tableName2 = $setup->getTable('catalog_product_entity');
				$setup->getConnection()
				->addColumn($tableName2, 'last_updated_at',[
						'type' => Table::TYPE_TIMESTAMP,
						'nullable' => true,
						'default' => null,
						'comment' => 'Last update from Medipim',
				]
				);
			}
			if (version_compare($context->getVersion(), '1.0.2') < 0) {
				//code to upgrade to 1.0.2

				
			}
			if (version_compare($context->getVersion(), '1.0.3') < 0) {
				//code to upgrade to 1.0.3
					
				
			
			}
			if (version_compare($context->getVersion(), '1.0.4') < 0) {
				//code to upgrade to 1.0.2
					
			}
			
		}
}
<?php 
namespace brainworx\medipimsync\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * Installs DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        $table = $installer->getConnection()
            ->newTable($installer->getTable('brainworx_medipimsync_sync'))
            ->addColumn(
                'sync_id',Table::TYPE_INTEGER, null,
                ['identity' => true, 'nullable' => false, 'primary' => true],
                'Sync ID'
            )
        	->addColumn('entity', Table::TYPE_TEXT, 200, ['nullable' => true], 'entity')
        	->addColumn('sync_dttm', Table::TYPE_TIMESTAMP, null, 
        		['default' => Table::TIMESTAMP_INIT], 'Sync time')
        	->addColumn('update_time', Table::TYPE_TIMESTAMP, null,
        		['default' => Table::TIMESTAMP_INIT_UPDATE], 'Update Time')
        	->addColumn('user', Table::TYPE_TEXT, 200,['nullable' => true], 'user')
        	->addColumn('qty_tot', Table::TYPE_INTEGER, null,
        			['nullable' => false, 'default' => '0'], 'Total quantity')
        	->addColumn('qty_updt', Table::TYPE_INTEGER, null,
        			['nullable' => false, 'default' => '0'], 'Updated quantity')
	        ->addColumn('is_ended', Table::TYPE_SMALLINT, null, 
	        		['nullable' => false, 'default' => '0'], 'Is sync ended?')        		 
        	->setComment('Sync with Medipim');

        $installer->getConnection()->createTable($table);

        $installer->endSetup();
    }

}